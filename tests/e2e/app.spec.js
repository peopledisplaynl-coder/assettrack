import { test, expect } from '@playwright/test';
import { login, sql } from './helpers.js';
import { readFileSync } from 'node:fs';

const VERSION = readFileSync('includes/version.php', 'utf8').match(/APP_VERSION',\s*'([^']+)'/)[1];

test.beforeEach(({ page }) => {
  page.on('pageerror', (err) => { throw err; });
});

test('login page shows the demo hint and rejects a wrong password', async ({ page }) => {
  await page.goto('/index.php');
  await expect(page.getByText('Demomodus: log in met')).toBeVisible();
  await page.fill('input[name="username"]', 'demo');
  await page.fill('input[name="password"]', 'fout');
  await page.click('button[type="submit"]');
  await expect(page.locator('.alert-error')).toBeVisible();
});

test('superadmin logs in, picks a school and sees the dashboard', async ({ page }) => {
  await login(page);
  await expect(page.locator('.stat-card').first()).toBeVisible();
  await expect(page.getByText('Demomodus — alle gegevens zijn verzonnen')).toBeVisible();
  await expect(page.locator('footer')).toContainText(VERSION);
});

test('search finds Chromebooks and opens an asset', async ({ page }) => {
  await login(page);
  await page.goto('/modules/assets/index.php?search=Chromebook');
  const firstLink = page.locator('a[href*="view.php?id="]').first();
  await expect(firstLink).toBeVisible();
  await firstLink.click();
  await expect(page).toHaveURL(/view\.php\?id=\d+/);
  await expect(page.locator('body')).toContainText('ChromeOS');
});

test('adding an asset normalises a MAC address without separators', async ({ page }) => {
  await login(page);
  await page.goto('/modules/assets/add.php');
  await page.fill('input[name="brand"]', 'Lenovo');
  await page.fill('input[name="model"]', 'E2E Testlaptop');
  await page.fill('input[name="mac_address"]', '025a00aabbcc');
  await page.getByRole('button', { name: 'Asset opslaan' }).click();
  await expect(page.locator('body')).toContainText('Asset succesvol toegevoegd');
  expect(sql("SELECT mac_address FROM assets WHERE model='E2E Testlaptop'")).toBe('02:5A:00:AA:BB:CC');
});

test('reports: full overview renders and CSV export downloads', async ({ page }) => {
  await login(page);
  await page.goto('/modules/reports/generate.php?type=all');
  await expect(page.locator('table').first()).toBeVisible();
  const download = page.waitForEvent('download');
  await page.locator('a[href*="export=csv"]').first().click();
  expect((await download).suggestedFilename()).toMatch(/\.csv$/);
});

test('main pages load without PHP errors', async ({ page }) => {
  await login(page);
  for (const path of ['/modules/assets/index.php', '/modules/assets/search_all.php', '/modules/assets/bulk_edit.php',
    '/modules/labels/index.php', '/modules/reports/index.php', '/modules/kb/index.php', '/modules/settings/index.php',
    '/modules/settings/locations.php', '/modules/settings/scan_domain.php', '/modules/users/index.php',
    '/modules/users/profile.php', '/modules/assets/import.php', '/modules/assets/templates.php', '/select_location.php']) {
    const res = await page.goto(path);
    expect(res.status(), path).toBe(200);
    await expect(page.locator('body'), path).not.toContainText(/Fatal error|Warning:|Notice:|Deprecated:/);
  }
});

test('a pending migration runs automatically on the next request', async ({ page }) => {
  sql("ALTER TABLE companies DROP COLUMN scan_base_url; DELETE FROM schema_migrations WHERE version='0003'");
  await page.goto('/index.php');
  expect(sql("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='assettrack_demo' AND TABLE_NAME='companies' AND COLUMN_NAME='scan_base_url'")).toBe('1');
  expect(sql("SELECT COUNT(*) FROM schema_migrations WHERE version='0003'")).toBe('1');
});

test('logout ends the session', async ({ page }) => {
  await login(page);
  await page.goto('/logout.php');
  await page.goto('/dashboard.php');
  await expect(page).toHaveURL(/index\.php/);
});

test('templates: "+ Nieuw asset" opens the form pre-filled', async ({ page }) => {
  await login(page);
  await page.goto('/modules/assets/templates.php');
  await page.getByRole('link', { name: '+ Nieuw asset' }).first().click();
  await expect(page).toHaveURL(/add\.php\?template_id=\d+/);
  await expect(page.locator('input[name="brand"]')).not.toHaveValue('');
});

test('import: a CSV with byte-order mark (Excel "CSV UTF-8") shows clean column names', async ({ page }) => {
  await login(page);
  await page.goto('/modules/assets/import.php');
  await page.selectOption('select[name="location_id"]', { label: 'KC Het Vlinderveld' });
  await page.setInputFiles('input[type="file"]', 'tools/demo/demo_import.csv');
  await page.locator('main form button[type="submit"]').last().click();
  await expect(page.locator('select[name="mapping[Assetnummer]"]')).toHaveCount(1);
});
