// Verifies that the version is the same everywhere it is written down.
import { readFileSync } from 'node:fs';
const root = new URL('../', import.meta.url);
const pkg = JSON.parse(readFileSync(new URL('package.json', root), 'utf8')).version;
const php = readFileSync(new URL('includes/version.php', root), 'utf8').match(/APP_VERSION',\s*'([^']+)'/)?.[1];
const changelog = readFileSync(new URL('CHANGELOG.md', root), 'utf8');
const readme = readFileSync(new URL('README.md', root), 'utf8');
const errors = [];
if (php !== pkg) errors.push(`includes/version.php (${php}) != package.json (${pkg})`);
if (!changelog.includes(`## [${pkg}]`)) errors.push(`CHANGELOG.md has no "## [${pkg}]" section`);
if (!readme.includes(`versie-${pkg}-`)) errors.push(`README.md badge is not ${pkg}`);
if (errors.length) { console.error('Version check failed:\n  ' + errors.join('\n  ')); process.exit(1); }
console.log(`Version ${pkg} consistent (package.json, includes/version.php, CHANGELOG.md, README.md)`);
