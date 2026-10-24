// HTML -> PDF with Chromium (Playwright), with page numbers in the footer.
import { chromium } from '@playwright/test';
import { pathToFileURL } from 'node:url';
const [, , html, pdf, footer = 'AssetTrack — Gebruikershandleiding'] = process.argv;
const browser = await chromium.launch();
const page = await browser.newPage();
await page.goto(pathToFileURL(html).href, { waitUntil: 'networkidle' });
await page.pdf({
  path: pdf, format: 'A4', printBackground: true, displayHeaderFooter: true,
  headerTemplate: '<span></span>',
  footerTemplate: `<div style="width:100%;font-size:7.5pt;color:#94a3b8;padding:0 16mm;display:flex;justify-content:space-between;font-family:Arial,sans-serif"><span>${footer}</span><span>Pagina <span class="pageNumber"></span> van <span class="totalPages"></span></span></div>`,
  margin: { top: '16mm', bottom: '18mm', left: '0', right: '0' },
});
await browser.close();
console.log(pdf);
