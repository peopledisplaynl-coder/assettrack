import { expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

export async function login(page, user = 'demo', password = 'demo1234', location = 'OBS De Zonnewijzer') {
  await page.goto('/index.php');
  await page.fill('input[name="username"]', user);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  if (page.url().includes('select_location.php')) {
    await page.locator('.location-card', { hasText: location }).locator('button.btn-choose').click();
  }
  await expect(page).toHaveURL(/dashboard\.php/);
}

/** Run SQL on the demo database (local stack only). */
export function sql(query) {
  return execFileSync('mariadb', ['--protocol=tcp', '-h127.0.0.1', `-P${process.env.AT_DB_PORT || 13306}`,
    '-uassettrack', '-passettrack', '-N', 'assettrack_demo', '-e', query], { encoding: 'utf8' }).trim();
}
