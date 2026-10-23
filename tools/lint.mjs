// "php -l" on every PHP file (needs PHP on the PATH).
import { readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
const root = fileURLToPath(new URL('../', import.meta.url));
const skip = new Set(['.git', 'node_modules', 'tests/.tmp', 'test-results', 'playwright-report', 'dist']);
const files = [];
(function walk(dir) {
  for (const name of readdirSync(dir)) {
    const full = join(dir, name);
    const rel = relative(root, full).replaceAll('\\', '/');
    if (skip.has(rel)) continue;
    if (statSync(full).isDirectory()) walk(full);
    else if (name.endsWith('.php')) files.push(full);
  }
})(root);
let failed = 0;
for (const f of files) {
  const r = spawnSync('php', ['-l', f], { encoding: 'utf8' });
  if (r.error) { console.error('PHP not found on PATH: ' + r.error.message); process.exit(2); }
  if (r.status !== 0) { failed++; console.error(r.stdout + r.stderr); }
}
console.log(`php -l: ${files.length - failed}/${files.length} files OK`);
process.exit(failed ? 1 : 0);
