#!/usr/bin/env node
/**
 * AssetTrack deploy: uploads changed app files to a server over SFTP.
 *
 *   npm run deploy                 deploy to the default target (deploy.config.json)
 *   npm run deploy -- productie    deploy to a named target
 *   npm run deploy -- test --dry-run   show what would be uploaded, upload nothing
 *   npm run deploy -- test --full      ignore the state file, compare everything again
 *   npm run deploy -- test --stale     also list server files that no longer exist locally
 *   npm run deploy -- test --compare   download and compare every app file with the server,
 *                                      report differences, upload nothing (also fills the
 *                                      state file, so the next deploy only sends real changes)
 *
 * Safety rules (hard-coded, not configurable):
 *  - includes/config.php is never uploaded or overwritten.
 *  - assets/uploads/** (photos, avatars, logos) is never uploaded or overwritten;
 *    only missing upload folders get created, with their empty index.php guard.
 *  - install/ is never uploaded to an existing server (use "npm run package" for
 *    a fresh install).
 *  - Nothing is ever deleted on the server; --stale only reports.
 *  - The password is asked for every run (or read from AT_DEPLOY_PASSWORD) and
 *    never written to disk.
 *
 * Only changed files are uploaded: .deploy-state-<target>.json remembers the
 * hash of every file that was uploaded successfully (saved after each file, so
 * an interrupted deploy simply continues next time). Uploads run one at a time
 * and folders are created one level at a time (Strato SFTP does not like
 * parallel transfers or recursive mkdir).
 */
import { createHash } from 'node:crypto';
import { existsSync, readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createInterface } from 'node:readline';

export const ROOT = fileURLToPath(new URL('../../', import.meta.url));

/** Top-level entries that make up the app on the server. */
export const APP_ENTRIES = [
  'assets', 'includes', 'migrations', 'modules', 'templates',
  'dashboard.php', 'favicon.ico', 'index.php', 'logout.php', 'manifest.json',
  'select_location.php', 'switch_location.php',
];

/** Paths (relative, forward slashes) that must never be uploaded. */
export function isProtected(rel) {
  return rel === 'includes/config.php'
    || rel.startsWith('assets/uploads/')
    || rel.startsWith('install/')
    || /(^|\/)\.(?!htaccess$)/.test(rel)          // dotfiles, except .htaccess
    || /\.(md|log|bak|tmp|swp|zip)$/i.test(rel);
}

/** Upload folders that must exist on the server (created when missing, never overwritten). */
export const ENSURE_DIRS = ['assets/uploads', 'assets/uploads/asset_images', 'assets/uploads/user_avatars'];

export function listLocalFiles(root = ROOT) {
  const out = [];
  const walk = (abs) => {
    const rel = relative(root, abs).replaceAll('\\', '/');
    const st = statSync(abs);
    if (st.isDirectory()) {
      for (const name of readdirSync(abs).sort()) walk(join(abs, name));
    } else if (!isProtected(rel)) {
      out.push(rel);
    }
  };
  for (const entry of APP_ENTRIES) {
    if (existsSync(join(root, entry))) walk(join(root, entry));
  }
  return out;
}

export function hashFile(abs) {
  return createHash('sha1').update(readFileSync(abs)).digest('hex');
}

/** Files whose hash differs from the state file. */
export function changedFiles(files, state, root = ROOT) {
  return files.filter((rel) => state[rel] !== hashFile(join(root, rel)));
}

function loadConfig() {
  const file = join(ROOT, 'deploy.config.json');
  if (!existsSync(file)) {
    console.error('deploy.config.json ontbreekt. Kopieer deploy.config.example.json naar deploy.config.json en vul de gegevens in.');
    process.exit(1);
  }
  return JSON.parse(readFileSync(file, 'utf8'));
}

function askHidden(question) {
  return new Promise((resolve) => {
    const rl = createInterface({ input: process.stdin, output: process.stdout, terminal: true });
    rl._writeToOutput = (s) => { if (s.includes(question)) rl.output.write(s); };
    rl.question(question, (answer) => { rl.close(); process.stdout.write('\n'); resolve(answer); });
  });
}

async function remoteExists(sftp, path) {
  try { return await sftp.exists(path); } catch { return false; }
}

async function ensureRemoteDir(sftp, base, relDir, known) {
  let cur = base;
  for (const part of relDir.split('/').filter(Boolean)) {
    cur = `${cur}/${part}`;
    if (known.has(cur)) continue;
    if (!(await remoteExists(sftp, cur))) {
      await sftp.mkdir(cur, false);
      console.log(`  map aangemaakt: ${cur}`);
    }
    known.add(cur);
  }
}

async function listRemoteFiles(sftp, base, rel = '') {
  const out = [];
  for (const item of await sftp.list(rel ? `${base}/${rel}` : base)) {
    const childRel = rel ? `${rel}/${item.name}` : item.name;
    if (item.type === 'd') out.push(...await listRemoteFiles(sftp, base, childRel));
    else out.push(childRel);
  }
  return out;
}

async function main() {
  const args = process.argv.slice(2);
  const flags = new Set(args.filter((a) => a.startsWith('--')));
  const config = loadConfig();
  const targetName = args.find((a) => !a.startsWith('--')) || config.default;
  const target = config.targets?.[targetName];
  if (!target) {
    console.error(`Onbekend doel "${targetName}". Beschikbaar: ${Object.keys(config.targets || {}).join(', ')}`);
    process.exit(1);
  }
  const base = target.remotePath.replace(/\/+$/, '');
  const stateFile = join(ROOT, `.deploy-state-${targetName}.json`);
  const state = !flags.has('--full') && existsSync(stateFile) ? JSON.parse(readFileSync(stateFile, 'utf8')) : {};

  const files = listLocalFiles();
  const todo = changedFiles(files, state);
  const version = readFileSync(join(ROOT, 'includes/version.php'), 'utf8').match(/APP_VERSION',\s*'([^']+)'/)?.[1];
  console.log(`AssetTrack ${version} -> ${targetName} (${target.username}@${target.host}:${base})`);
  console.log(`${files.length} app-bestanden, ${todo.length} gewijzigd sinds de vorige deploy naar ${targetName}.`);

  if (flags.has('--dry-run') && !flags.has('--compare')) {
    todo.forEach((f) => console.log(`  zou uploaden: ${f}`));
    console.log('Droogtest: er is niets geüpload.');
    if (!flags.has('--stale')) return;
  }

  const { default: SftpClient } = await import('ssh2-sftp-client');
  const sftp = new SftpClient();
  const password = process.env.AT_DEPLOY_PASSWORD || (target.privateKey ? undefined : await askHidden(`Wachtwoord voor ${target.username}@${target.host}: `));
  await sftp.connect({
    host: target.host, port: target.port || 22, username: target.username, password,
    privateKey: target.privateKey ? readFileSync(target.privateKey) : undefined,
    readyTimeout: 30000,
  });

  try {
    if (!(await remoteExists(sftp, `${base}/includes/config.php`))) {
      console.error(`Op ${base} staat geen includes/config.php. Is dit wel een bestaande AssetTrack-installatie? Deploy afgebroken.`);
      process.exitCode = 1;
      return;
    }

    if (flags.has('--compare')) {
      const differs = [];
      const missing = [];
      for (const rel of files) {
        let remote;
        try { remote = await sftp.get(`${base}/${rel}`); } catch { missing.push(rel); continue; }
        const remoteHash = createHash('sha1').update(remote).digest('hex');
        const localHash = hashFile(join(ROOT, rel));
        // Ignore pure line-ending differences (CRLF vs LF) for text files.
        const norm = (b) => createHash('sha1').update(b.toString('latin1').replace(/\r\n/g, '\n')).digest('hex');
        if (remoteHash === localHash || (!/\.(png|ico|jpg|gif|pdf)$/i.test(rel) && norm(remote) === norm(readFileSync(join(ROOT, rel))))) {
          state[rel] = localHash;
        } else {
          differs.push(rel);
          delete state[rel];
        }
      }
      writeFileSync(stateFile, JSON.stringify(state, null, 1));
      console.log(`Vergelijking met ${targetName}: ${files.length - differs.length - missing.length} gelijk, ${differs.length} anders, ${missing.length} ontbreken op de server.`);
      differs.forEach((f) => console.log(`  anders:     ${f}`));
      missing.forEach((f) => console.log(`  ontbreekt:  ${f}`));
      console.log('Er is niets geüpload. Een volgende "npm run deploy" stuurt alleen de afwijkende en ontbrekende bestanden.');
    }

    if (!flags.has('--dry-run') && !flags.has('--compare')) {
      const knownDirs = new Set();
      for (const dir of ENSURE_DIRS) {
        await ensureRemoteDir(sftp, base, dir, knownDirs);
        const guard = `${base}/${dir}/index.php`;
        if (!(await remoteExists(sftp, guard))) await sftp.put(Buffer.from('<?php // Beveiligd'), guard);
      }
      let n = 0;
      for (const rel of todo) {
        const dir = rel.includes('/') ? rel.slice(0, rel.lastIndexOf('/')) : '';
        if (dir) await ensureRemoteDir(sftp, base, dir, knownDirs);
        await sftp.put(join(ROOT, rel), `${base}/${rel}`);
        state[rel] = hashFile(join(ROOT, rel));
        writeFileSync(stateFile, JSON.stringify(state, null, 1));
        console.log(`  [${++n}/${todo.length}] ${rel}`);
      }
      writeFileSync(stateFile, JSON.stringify(state, null, 1));
      console.log(todo.length ? `${todo.length} bestand(en) geüpload.` : 'Alles was al bijgewerkt.');
    }

    if (flags.has('--stale')) {
      const local = new Set(files);
      const stale = [];
      for (const entry of APP_ENTRIES) {
        const st = await sftp.stat(`${base}/${entry}`).catch(() => null);
        if (!st) continue;
        const remote = st.isDirectory ? (await listRemoteFiles(sftp, `${base}/${entry}`)).map((f) => `${entry}/${f}`) : [entry];
        stale.push(...remote.filter((f) => !local.has(f) && !isProtected(f)));
      }
      console.log(stale.length ? 'Op de server, maar niet (meer) in het project (niet verwijderd):' : 'Geen verouderde bestanden gevonden.');
      stale.forEach((f) => console.log(`  ${f}`));
    }
  } finally {
    await sftp.end();
  }

  if (target.url && !flags.has('--dry-run') && !flags.has('--compare')) {
    // The first request after a deploy runs pending database migrations.
    try {
      const res = await fetch(`${target.url.replace(/\/+$/, '')}/index.php`, { redirect: 'manual' });
      console.log(`Controle ${target.url}: HTTP ${res.status} (database-updates worden bij dit eerste verzoek uitgevoerd).`);
    } catch (e) {
      console.log(`Kon ${target.url} niet controleren: ${e.message}`);
    }
  }
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  main().catch((e) => { console.error(`Deploy mislukt: ${e.message}`); process.exit(1); });
}
