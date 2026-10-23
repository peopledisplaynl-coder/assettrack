// Fresh installation from the release zip (npm run package), like on a new
// Strato webspace: wizard -> login -> installer is disabled afterwards.
import { test, expect } from '@playwright/test';
import { execFileSync, spawn } from 'node:child_process';
import { rmSync, mkdirSync, existsSync, readdirSync } from 'node:fs';

const SITE = 'tests/.tmp/install-site';
const PORT = 8090;
let server;

test.beforeAll(async () => {
  execFileSync('node', ['tools/package.mjs']);
  const zip = readdirSync('dist').filter((f) => f.endsWith('.zip')).sort().pop();
  rmSync(SITE, { recursive: true, force: true });
  mkdirSync(SITE, { recursive: true });
  execFileSync('unzip', ['-q', `dist/${zip}`, '-d', SITE]);
  execFileSync('mariadb', ['--protocol=tcp', '-h127.0.0.1', '-P13306', '-uroot', '-e',
    'DROP DATABASE IF EXISTS assettrack_test; CREATE DATABASE assettrack_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;']);
  const env = { ...process.env };
  delete env.ASSETTRACK_CONFIG;
  server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', SITE], { env, stdio: 'ignore' });
  await new Promise((r) => setTimeout(r, 800));
});

test.afterAll(() => server?.kill());

test('fresh install via the wizard, then the installer locks itself', async ({ page }) => {
  const base = `http://127.0.0.1:${PORT}`;
  await page.goto(`${base}/index.php`);
  await expect(page).toHaveURL(/install\/index\.php/);

  await page.fill('input[name="db_host"]', '127.0.0.1;port=13306');
  await page.fill('input[name="db_name"]', 'assettrack_test');
  await page.fill('input[name="db_user"]', 'assettrack');
  await page.fill('input[name="db_pass"]', 'assettrack');
  await page.fill('input[name="base_url"]', '');
  await page.locator('button[type="submit"]').click();

  await expect(page).toHaveURL(/step=2/);
  await page.fill('input[name="org_name"]', 'Testorganisatie');
  await page.fill('input[name="full_name"]', 'Test Beheerder');
  await page.fill('input[name="username"]', 'beheer');
  await page.fill('input[name="email"]', 'beheer@test.example');
  await page.fill('input[name="password"]', 'Welkom-12345');
  await page.fill('input[name="password_confirm"]', 'Welkom-12345');
  await page.locator('button[type="submit"]').click();

  await expect(page).toHaveURL(/step=3/);
  await page.locator('button[type="submit"]').click();
  await expect(page.locator('body')).toContainText(/voltooid|gereed|klaar/i);

  // Installer disabled: config exists and the wizard file is gone or renamed.
  expect(existsSync(`${SITE}/includes/config.php`)).toBe(true);
  expect(existsSync(`${SITE}/install/index.php`)).toBe(false);
  const again = await page.goto(`${base}/install/index.php?reinstall=1`);
  expect(again.status()).toBe(404);
  expect(existsSync(`${SITE}/includes/config.php`)).toBe(true);

  // Log in with the new account.
  await page.goto(`${base}/index.php`);
  await page.fill('input[name="username"]', 'beheer');
  await page.fill('input[name="password"]', 'Welkom-12345');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/dashboard\.php|select_location\.php/);
  const migrations = execFileSync('mariadb', ['--protocol=tcp', '-h127.0.0.1', '-P13306', '-uroot', '-N', 'assettrack_test',
    '-e', 'SELECT COUNT(*) FROM schema_migrations']).toString().trim();
  expect(Number(migrations)).toBeGreaterThanOrEqual(3);
});
