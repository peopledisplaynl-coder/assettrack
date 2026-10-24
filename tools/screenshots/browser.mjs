// Shared Playwright setup for screenshots: serves the CDN libraries and web fonts
// from node_modules, so screenshots also work without internet access.
import { chromium } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath(new URL('../../', import.meta.url));
const nm = (p) => `${ROOT}node_modules/${p}`;

const LIBS = {
  'qrcode.min.js': nm('qrcodejs/qrcode.min.js'),
  'JsBarcode.all.min.js': nm('jsbarcode/dist/JsBarcode.all.min.js'),
  'html5-qrcode.min.js': nm('html5-qrcode/html5-qrcode.min.js'),
};

const FONT_CSS = [400, 500, 600, 700].map((w) => `@font-face{font-family:'DM Sans';font-style:normal;font-weight:${w};font-display:swap;src:url(https://fonts.gstatic.com/local/dm-sans-${w}.woff2) format('woff2');}`).join('\n');

export const BASE_URL = process.env.AT_BASE_URL || 'http://127.0.0.1:8080';

export async function openBrowser({ width = 1280, height = 800, scale = 1.5, mobile = false } = {}) {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width, height }, deviceScaleFactor: scale, isMobile: mobile, hasTouch: mobile,
    locale: 'nl-NL', baseURL: BASE_URL,
  });
  await context.route(/cdnjs\.cloudflare\.com|cdn\.jsdelivr\.net|unpkg\.com/, (route) => {
    const file = Object.keys(LIBS).find((k) => route.request().url().endsWith(k));
    if (!file) return route.fulfill({ status: 404, body: '' });
    return route.fulfill({ contentType: 'application/javascript', body: readFileSync(LIBS[file]) });
  });
  await context.route(/fonts\.googleapis\.com/, (route) => route.fulfill({ contentType: 'text/css', body: FONT_CSS }));
  await context.route(/fonts\.gstatic\.com/, (route) => {
    const w = route.request().url().match(/dm-sans-(\d+)\.woff2/)?.[1];
    if (!w) return route.fulfill({ status: 404, body: '' });
    return route.fulfill({ contentType: 'font/woff2', body: readFileSync(nm(`@fontsource/dm-sans/files/dm-sans-latin-${w}-normal.woff2`)) });
  });
  return { browser, context };
}

export async function login(page, location = 'OBS De Zonnewijzer', user = 'demo', password = 'demo1234') {
  await page.goto('/index.php');
  await page.fill('input[name="username"]', user);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  if (page.url().includes('select_location.php') && location) {
    await page.locator('.location-card', { hasText: location }).locator('button.btn-choose').click();
    await page.waitForLoadState('networkidle');
  }
}
