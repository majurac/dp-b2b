// Regression: real clicks on the +/- buttons land on the nested <svg>/<path>; RowController must still react.
//   node tests/row/run-qty-button-tests.mjs   (needs real Chromium from ~/.qa/playwright)
import { createRequire } from 'node:module';
import os from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';

const here    = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(path.join(process.env.QA_PLAYWRIGHT ?? path.join(os.homedir(), '.qa', 'playwright'), 'x.js'));
const { chromium } = require('playwright');
const esbuild = require(path.join(here, '../../node_modules/esbuild'));

const entry = `
import { RowController } from ${JSON.stringify(path.join(here, '../../assets/src/row-controller.js').split(path.sep).join('/'))};
const state = { q:{}, getQuantity(k){return this.q[k]??0;}, getError(){return null;}, setQuantity(k,n){this.q[k]=n;window.__q=this.q;} };
new RowController(state, {render(){}}, {render(){}});
`;
const { outputFiles } = await esbuild.build({ stdin: { contents: entry, resolveDir: here }, bundle: true, write: false, format: 'iife' });

const btn = (cls, dis = '') => `<button type="button" class="dp-qo-qty-btn ${cls}"${dis}><svg width="20" height="20"><path d="M0 0h20v20H0z"/></svg></button>`;
const html = `<div class="dp-qo-tbody"><div class="dp-qo-variation-row" data-row-key="1_2" data-variation-id="2" data-product-id="1">
  <div class="dp-qo-qty-wrap">${btn('dp-qo-qty-minus', ' disabled')}<input class="dp-qo-qty" data-row-key="1_2" value="0">${btn('dp-qo-qty-plus')}</div></div>
  <div class="dp-qo-variation-row" data-row-key="1_3" data-variation-id="3" data-product-id="1">
  <div class="dp-qo-qty-wrap">${btn('dp-qo-qty-minus', ' disabled')}<input class="dp-qo-qty" data-row-key="1_3" value="0" disabled>${btn('dp-qo-qty-plus', ' disabled')}</div></div></div>`;

const browser = await chromium.launch({ channel: process.env.QA_CHANNEL ?? 'chrome' });
const page = await browser.newPage();
await page.setContent(html);
await page.addScriptTag({ content: outputFiles[0].text });
const val = k => page.inputValue(`input[data-row-key="${k}"]`);
const row = '[data-row-key="1_2"]';

await page.click(`${row} .dp-qo-qty-plus svg`);               // real mouse click on the svg centre
assert.equal(await val('1_2'), '1', '+ via svg click');
await page.click(`${row} .dp-qo-qty-plus`);                   // centre of button = path
assert.equal(await val('1_2'), '2', '+ via button centre');
await page.click(`${row} .dp-qo-qty-minus`);
assert.equal(await val('1_2'), '1', '- via real click');
await page.click(`${row} .dp-qo-qty-minus`);
assert.equal(await val('1_2'), '0', '- back to 0');
assert.equal(await page.evaluate(() => window.__q['1_2']), 0, 'state follows input');
// disabled row stays inert (dispatch directly: disabled buttons receive no real click)
await page.evaluate(() => document.querySelector('[data-row-key="1_3"] .dp-qo-qty-plus svg').dispatchEvent(new MouseEvent('click', { bubbles: true })));
assert.equal(await val('1_3'), '0', 'disabled row untouched');
await browser.close();
console.log('qty button tests: OK');
