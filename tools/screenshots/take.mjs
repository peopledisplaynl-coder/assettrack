#!/usr/bin/env node
/**
 * Makes all screenshots for the user guide from the demo school.
 *
 *   AT_DEMO_MODE=false tools/dev/stack.sh start   # demo data, without the demo banner
 *   npm run screenshots                           # -> docs/handleiding/img/*.png
 *
 * Every screenshot has a stable name; docs/handleiding/handleiding.md refers to them.
 */
import { mkdirSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { openBrowser, login } from './browser.mjs';

const OUT = fileURLToPath(new URL('../../docs/handleiding/img/', import.meta.url));
mkdirSync(OUT, { recursive: true });
const only = process.argv[2];   // optional: only shots whose name contains this text
let count = 0;

async function shot(page, name, opts = {}) {
  if (only && !name.includes(only)) return;
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(250);
  const path = `${OUT}${name}.png`;
  if (opts.element) {
    await page.locator(opts.element).first().scrollIntoViewIfNeeded();
    await page.locator(opts.element).first().screenshot({ path });
  } else {
    await page.screenshot({ path, fullPage: !!opts.fullPage, clip: opts.clip });
  }
  count++;
  console.log(`  ${name}.png`);
}

const go = async (page, path) => { await page.goto(path); await page.waitForLoadState('networkidle'); };
const openAsset = async (page, search) => {
  await go(page, `/modules/assets/index.php?search=${encodeURIComponent(search)}`);
  await page.locator('a[href*="view.php?id="]').first().click();
  await page.waitForLoadState('networkidle');
};

// ---------- Desktop (1440 wide: full navigation bar visible) ----------
{
  const { browser, context } = await openBrowser({ width: 1440, height: 900 });
  const page = await context.newPage();

  await go(page, '/index.php');
  await shot(page, '01-inloggen');
  await login(page, null);
  await shot(page, '02-locatie-kiezen');
  await page.locator('.location-card', { hasText: 'OBS De Zonnewijzer' }).locator('button.btn-choose').click();
  await shot(page, '03-dashboard');
  await page.locator('[onclick^="toggleLocDropdown"]').first().click();
  await shot(page, '04-locatiemenu', { clip: { x: 0, y: 0, width: 720, height: 420 } });
  await page.keyboard.press('Escape'); await go(page, '/dashboard.php');
  await page.locator('[onclick^="toggleUserDropdown"]').first().click();
  await shot(page, '05-gebruikersmenu', { clip: { x: 1040, y: 0, width: 400, height: 260 } });

  await go(page, '/modules/assets/index.php');
  await shot(page, '06-assets-overzicht');
  await go(page, '/modules/assets/index.php?search=Groep+5');
  await shot(page, '07-assets-zoeken');
  await openAsset(page, 'ThinkPad');
  await shot(page, '08-asset-detail');
  await openAsset(page, 'OptiPlex 7010');
  await page.locator('h3:has-text("Gekoppelde assets")').first().evaluate((el) => window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - 120));
  await shot(page, '10-gekoppelde-assets');
  await go(page, '/modules/assets/add.php');
  await shot(page, '09-asset-toevoegen');
  await go(page, '/modules/assets/search_all.php?search=ThinkPad');
  await shot(page, '11-alle-locaties');

  await go(page, '/modules/assets/bulk_edit.php?filter_type=Chromebook&filter_room=Chromebookkar+2');
  await shot(page, '12-bulk-bewerken', { fullPage: true });

  // Import with the fictional CSV
  await go(page, '/modules/assets/import.php');
  await shot(page, '13-import-upload');
  await page.selectOption('select[name="location_id"]', { label: 'KC Het Vlinderveld' }).catch(() => {});
  await page.setInputFiles('input[type="file"]', fileURLToPath(new URL('../demo/demo_import.csv', import.meta.url)));
  await page.locator('main form button[type="submit"]').last().click();
  // Map every CSV column to the field with the same name (what the user does by hand).
  await page.evaluate(() => {
    const norm = (t) => t.toLowerCase().replace(/[^a-z]/g, '');
    for (const sel of document.querySelectorAll('select[name^="mapping["]')) {
      const col = norm(sel.name.slice(8, -1));
      const opt = [...sel.options].find((o) => norm(o.textContent) === col || (col === 'lanip' && o.value === 'lan_ip_address')
        || (col === 'fabrikanturl' && o.value === 'manufacturer_url') || (col === 'autoupdatevervalt' && o.value === 'autoupdate_expiry')
        || (col === 'opmerking' && o.value === 'notes'));
      if (opt) sel.value = opt.value;
    }
  });
  await shot(page, '14-import-koppelen');
  await page.locator('main form button[type="submit"]').last().click();
  await shot(page, '15-import-controle');

  await go(page, '/modules/assets/templates.php');
  await shot(page, '16-templates');

  await go(page, '/modules/labels/index.php?filter_room=Chromebookkar+1');
  for (const cb of await page.locator('input.asset-cb').all()) await cb.check();
  await shot(page, '17-labels-kiezen');
  await page.check('input[name="format"][value="a4_24"]').catch(() => {});
  const [preview] = await Promise.all([context.waitForEvent('page').catch(() => null), page.locator('#labelSubmitBtn').click()]);
  const labelPage = preview || page;
  await labelPage.waitForLoadState('networkidle');
  await labelPage.waitForTimeout(800);
  await shot(labelPage, '18-labels-voorbeeld');
  if (preview) await preview.close();

  await go(page, '/modules/reports/index.php');
  await shot(page, '19-rapporten');
  await go(page, '/modules/reports/generate.php?type=per_room');
  await shot(page, '20-rapport-per-ruimte');
  await go(page, '/modules/reports/generate.php?type=warranty');
  await page.locator('#fieldPickerDetails summary').click().catch(() => {});
  await shot(page, '21-rapport-kolommen');

  await go(page, '/modules/kb/index.php');
  await shot(page, '22-kennisbank');
  await page.locator('a[href*="article.php?id="]').first().click();
  await shot(page, '23-kennisbank-artikel');

  await go(page, '/modules/users/index.php');
  await shot(page, '24-gebruikers');
  await page.locator('a[href*="edit.php?id="]').nth(1).click();
  await shot(page, '25-gebruiker-bewerken');
  await go(page, '/modules/users/permissions.php');
  await shot(page, '26-rechtenbeheer');
  await go(page, '/modules/users/profile.php');
  await shot(page, '27-mijn-profiel');

  for (const [name, path] of [
    ['28-instellingen', '/modules/settings/index.php'], ['29-organisatie-locaties', '/modules/settings/locations.php'],
    ['30-merken-ruimtes', '/modules/settings/lists.php'], ['31-custom-velden', '/modules/settings/custom_fields.php'],
    ['32-asset-soorten', '/modules/settings/asset_types.php'], ['33-weergave', '/modules/settings/appearance.php'],
    ['34-scan-domein', '/modules/settings/scan_domain.php'], ['35-systeem', '/modules/settings/system.php'],
    ['36-backup', '/modules/settings/backup.php'], ['37-scanner', '/modules/assets/scanner.php'],
  ]) { await go(page, path); await shot(page, name); }

  await browser.close();
}

// ---------- Phone ----------
{
  const { browser, context } = await openBrowser({ width: 390, height: 844, scale: 2, mobile: true });
  const page = await context.newPage();
  await login(page);
  await shot(page, '40-mobiel-dashboard');
  await page.locator('button.hamburger').click();
  await shot(page, '41-mobiel-menu');
  await go(page, '/modules/assets/index.php');
  await shot(page, '42-mobiel-assets');
  await browser.close();
}

console.log(`${count} screenshots in docs/handleiding/img/`);
