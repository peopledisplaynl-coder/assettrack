import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

test('dashboard and asset list fit a phone screen', async ({ page }) => {
  await login(page);
  for (const path of ['/dashboard.php', '/modules/assets/index.php']) {
    await page.goto(path);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, path).toBeLessThanOrEqual(2);
  }
});
