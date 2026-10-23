import { execFileSync } from 'node:child_process';

export default async function globalSetup() {
  if (process.env.AT_BASE_URL) return;
  // Fresh demo data for every run, so tests are repeatable.
  execFileSync('bash', ['tools/dev/stack.sh', 'start'], { stdio: 'inherit' });
}
