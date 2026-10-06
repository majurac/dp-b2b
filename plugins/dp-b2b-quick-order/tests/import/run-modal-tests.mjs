// Gate 2 modal tests (real Chromium, REAL Quick Order markup + REAL localized config/copy, mocked REST).
//
//   npm run build
//   php wp-cli.phar eval-file wp-content/plugins/dp-b2b-quick-order/tests/import/render-harness.php
//   py tests/import/gen-fixtures.py <fixtures>
//   node tests/import/run-modal-tests.mjs <fixtures> [screenshotDir]
//
// The REST server (validate + cart/sync + products) is mocked per test so that every status/outcome is deterministic
// and every request can be counted. The real server contract is covered by validator-test.php (Gate 1).
import { createRequire } from 'node:module';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const require = createRequire(path.join(process.env.QA_PLAYWRIGHT ?? path.join(os.homedir(), '.qa', 'playwright'), 'x.js'));
const { chromium } = require('playwright');

const fixtures = path.resolve(process.argv[2] ?? '');
const shotDir = process.argv[3] ? path.resolve(process.argv[3]) : null;
if (shotDir) fs.mkdirSync(shotDir, { recursive: true });

const BASE = 'http://localhost:8080/dp-b2b/wp-content/plugins/dp-b2b-quick-order';
const HARNESS = `${BASE}/tests/import/.generated/harness.html`;
const REST = 'http://localhost:8080/dp-b2b/wp-json/dreampoint-b2b/v1/quick-order';

const results = [];
let currentGroup = '';
const check = (label, cond, detail) => {
  results.push({ ok: !!cond, label: `${currentGroup} · ${label}`, detail });
  console.log(cond ? `ok    ${currentGroup} · ${label}` : `FAIL  ${currentGroup} · ${label}\n      ${JSON.stringify(detail)?.slice(0, 1500)}`);
};

const browser = await chromium.launch({ channel: process.env.QA_CHANNEL ?? 'chrome' });

// ───────────────────────────────────────────────────────────────────────────────────────────────── harness helpers
const PRODUCTS = [
  { id: 101, name: 'Ledlenser P7R Core', sku: 'P-1', catalog_number: 'LL-502181', type: 'simple', price: '9.5', price_html: '9,50 €', stock: { status: 'instock' }, image: '', permalink: '#' },
  { id: 102, name: 'Ledlenser H19R Signature', sku: 'P-2', catalog_number: 'LL-502182', type: 'simple', price: '9.5', price_html: '9,50 €', stock: { status: 'instock' }, image: '', permalink: '#' },
];

async function newPage({ width = 1440, height = 1024, mock = {} } = {}) {
  const ctx = await browser.newContext({ viewport: { width, height } });
  const page = await ctx.newPage();
  const log = { validate: [], sync: [], errors: [], consoleErrors: [] };
  page.on('console', (m) => { if (m.type() === 'error') log.consoleErrors.push(m.text().slice(0, 200)); });
  page.on('pageerror', (e) => log.errors.push(String(e.message).slice(0, 200)));

  await page.route(`${REST}/products*`, (r) => r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ products: PRODUCTS, total: 2, total_pages: 1 }) }));
  await page.route(`${REST}/import/validate`, async (r) => {
    const body = r.request().postDataJSON();
    log.validate.push({ body, headers: r.request().headers(), t: Date.now() });
    const out = mock.validate ? await mock.validate(body, log.validate.length - 1) : { status: 200, json: { rows: [], summary: {} } };
    if (out === 'hang') return; // never answered
    if (out === 'abort') return r.abort();
    return r.fulfill({ status: out.status ?? 200, contentType: 'application/json', body: JSON.stringify(out.json ?? {}) });
  });
  await page.route(`${REST}/cart/sync`, async (r) => {
    const body = r.request().postDataJSON();
    log.sync.push({ items: body.items, t: Date.now() });
    const out = mock.sync ? await mock.sync(body.items, log.sync.length - 1) : { json: { synced: body.items.map((i) => ({ product_id: i.product_id, variation_id: i.variation_id, action: 'added', quantity: i.quantity })) } };
    if (out === 'hang') return;
    if (out === 'abort') return r.abort();
    return r.fulfill({ status: out.status ?? 200, contentType: 'application/json', body: JSON.stringify(out.json ?? {}) });
  });

  await page.goto(HARNESS, { waitUntil: 'load' });
  await page.addScriptTag({ url: `${BASE}/assets/dist/quick-order.js` });
  await page.addScriptTag({ url: `${BASE}/assets/dist/quick-order-import.js` });
  await page.waitForSelector('.dp-qo-card', { timeout: 15000 });
  await page.waitForSelector('[data-dp-qo-import]:not([hidden])', { timeout: 5000 });
  return { ctx, page, log };
}

const openModal = async (page) => { await page.click('[data-dp-qo-import]'); await page.waitForSelector('.dp-qo-import__dialog'); };
const hasModal = (page) => page.locator('.dp-qo-import').count().then((n) => n > 0);
const csvFile = (text, name = 'order.csv') => ({ name, mimeType: 'text/csv', buffer: Buffer.from('﻿' + text, 'utf8') });
const pickFile = async (page, file) => { await page.setInputFiles('.dp-qo-import__file', file); };
const R = (rows, identifier, status, code, pid, vid, name, requested, quantity) => ({ rows, identifier, status, code, product_id: pid, variation_id: vid, name, requested, quantity });
const summaryOf = (rows) => ({ ready: rows.filter((r) => r.status === 'ready').length, adjusted: rows.filter((r) => r.status === 'adjusted').length, error: rows.filter((r) => r.status === 'error').length, input_rows: rows.length });
const ok = (rows) => ({ json: { rows, summary: summaryOf(rows) } });
const text = (page, sel) => page.locator(sel).first().innerText();
const waitPhase = (page, sel, timeout = 8000) => page.waitForSelector(sel, { timeout });

// The design's own example: 4 fine rows + 2 problems.
const DESIGN_ROWS = [
  R([2], 'LL-502181', 'ready', null, 101, 0, 'Ledlenser P7R Core', 2, 2),
  R([3], 'LL-502182', 'ready', null, 102, 0, 'Ledlenser H19R Signature', 2, 2),
  R([4], 'LL-502183', 'ready', null, 103, 0, 'Ledlenser HF6R Core', 2, 2),
  R([5], 'LL-502184', 'adjusted', null, 104, 0, 'Ledlenser ML4 Warm Light', 15, 8),
  R([6], 'XX-000001', 'error', 'identifier_not_found', null, null, null, 4, 0),
  R([7], 'LL-502186', 'error', 'unavailable', null, null, 'Ledlenser MT14', 15, 0),
];
const DESIGN_CSV = 'SKU;Količina\r\nLL-502181;2\r\nLL-502182;2\r\nLL-502183;2\r\nLL-502184;15\r\nXX-000001;4\r\nLL-502186;15\r\n';

// ═══════════════════════════════════════════════════════════════════════════════════════════════════ A. upload
currentGroup = 'A upload';
{
  const { ctx, page, log } = await newPage();
  const before = await page.evaluate(() => ({ url: location.href, ls: JSON.stringify({ ...localStorage }), ss: JSON.stringify({ ...sessionStorage }) }));
  await openModal(page);
  const d = await page.evaluate(() => {
    const dlg = document.querySelector('.dp-qo-import__dialog');
    const q = (s) => document.querySelector(s);
    return {
      role: dlg.getAttribute('role'), modal: dlg.getAttribute('aria-modal'), labelled: document.getElementById(dlg.getAttribute('aria-labelledby'))?.textContent,
      steps: !!q('.dp-qo-import__steps'), dropzone: !!q('.dp-qo-import__drop'), pick: q('.dp-qo-import__add-file')?.textContent.trim(),
      xlsx: q('a[download][href$=".xlsx"]')?.getAttribute('href'), csv: q('a[download][href$=".csv"]')?.getAttribute('href'),
      cols: [...document.querySelectorAll('.dp-qo-import__required li')].map((l) => l.textContent.trim()),
      example: [...document.querySelectorAll('.dp-qo-import__example-table td, .dp-qo-import__example-table th')].map((c) => c.textContent),
      h3: [...document.querySelectorAll('.dp-qo-import__h3')].map((h) => h.textContent),
      focusInside: dlg.contains(document.activeElement), inertSiblings: [...document.body.children].filter((e) => !e.matches('.dp-qo-import')).every((e) => e.hasAttribute('inert')),
      closeLabel: q('.dp-qo-import__close')?.getAttribute('aria-label'), inputLabel: q('.dp-qo-import__file')?.getAttribute('aria-label'), accept: q('.dp-qo-import__file')?.getAttribute('accept'),
    };
  });
  if (shotDir) await page.screenshot({ path: path.join(shotDir, 'upload-1440.png') });
  check('opens a labelled modal dialog (role=dialog, aria-modal, title)', d.role === 'dialog' && d.modal === 'true' && d.labelled === 'Uvoz narudžbe iz Excel fajla', d);
  check('initial state: no stepper, drop zone, "Dodaj file", both step headings, required columns, example row', !d.steps && d.dropzone && d.pick === 'Dodaj file' && d.h3.join('|') === '1. Preuzmite template|2. Popunite i uvezite file' && d.cols.join() === 'Količina,SKU' && d.example.join() === 'SKU,Količina,LL-502181,5', d);
  check('template download: committed .xlsx (primary) and .csv links', /dp-quick-order-import-template\.xlsx/.test(d.xlsx) && /dp-quick-order-import-template\.csv/.test(d.csv), d);
  check('keyboard/AT: initial focus inside, background inert, labelled close + file input, accept .xlsx,.csv', d.focusInside && d.inertSiblings && /Zatvori/.test(d.closeLabel) && /\.xlsx\) ili CSV/.test(d.inputLabel) && d.accept === '.xlsx,.csv', d);
  check('opening does not clear/alter anything: no request, same URL, no storage writes', log.validate.length === 0 && log.sync.length === 0 && (await page.evaluate(() => location.href)) === before.url && (await page.evaluate(() => JSON.stringify({ ...localStorage }))) === before.ls, log);
  await ctx.close();
}

async function uploadCase(label, file, expectMsg, extra = {}) {
  const { ctx, page, log } = await newPage();
  await openModal(page);
  await pickFile(page, file);
  await page.waitForTimeout(400);
  const msg = await page.evaluate(() => document.querySelector('.dp-qo-import__error')?.textContent ?? null);
  const stillUpload = await page.locator('.dp-qo-import__drop').count();
  check(`${label} → modal-level Croatian error, stays in upload state, nothing sent to server`, msg === expectMsg && stillUpload === 1 && log.validate.length === 0 && (extra.after ? await extra.after(page) : true), { msg, stillUpload, calls: log.validate.length });
  await ctx.close();
}
await uploadCase('invalid extension (.txt)', { name: 'x.txt', mimeType: 'text/plain', buffer: Buffer.from('SKU;Količina\nA;1') }, 'Podržani su samo formati .xlsx i .csv.');
await uploadCase('legacy .xls', { name: 'x.xls', mimeType: 'application/vnd.ms-excel', buffer: Buffer.from('abc') }, 'Podržani su samo formati .xlsx i .csv.');
await uploadCase('malformed workbook (truncated ZIP)', path.join(fixtures, 'malformed-zip.xlsx'), 'Datoteku nije moguće pročitati.');
await uploadCase('not a ZIP', path.join(fixtures, 'not-a-zip.xlsx'), 'Datoteku nije moguće pročitati.');
await uploadCase('empty file', { name: 'e.csv', mimeType: 'text/csv', buffer: Buffer.alloc(0) }, 'Datoteka ne sadrži nijedan redak za uvoz.');
await uploadCase('header only', csvFile('SKU;Količina\r\n'), 'Datoteka ne sadrži nijedan redak za uvoz.');
await uploadCase('wrong headers', csvFile('Code;Qty\nA;1\n'), 'Zaglavlje nije prepoznato. Prvi redak mora sadržavati stupce SKU i Količina.');
await uploadCase('> 500 rows (csv)', csvFile('SKU;Količina\r\n' + Array.from({ length: 501 }, (_, i) => `ID${i};1`).join('\r\n')), 'Datoteka sadrži više od 500 redaka.');
await uploadCase('> 500 rows (xlsx)', path.join(fixtures, 'rows-501.xlsx'), 'Datoteka sadrži više od 500 redaka.');
await uploadCase('oversized file (> 2 MB)', path.join(fixtures, 'over-2mb.xlsx'), 'Datoteka je prevelika (najviše 2 MB).');
await uploadCase('macro workbook', path.join(fixtures, 'vba.xlsx'), 'Datoteke s makroima nisu podržane.');
await uploadCase('malformed CSV quoting', csvFile('SKU;Količina\nA"B;1\n'), 'CSV datoteka nije ispravno oblikovana (provjerite navodnike).');
{
  // recover: after a bad file a good one works from the same modal
  const { ctx, page, log } = await newPage({ mock: { validate: (b) => ok(b.rows.map((r) => R([r.row], r.identifier, 'ready', null, 101, 0, 'Ledlenser P7R Core', Number(r.quantity), Number(r.quantity)))) } });
  await openModal(page);
  await pickFile(page, { name: 'x.txt', mimeType: 'text/plain', buffer: Buffer.from('x') });
  await page.waitForSelector('.dp-qo-import__error');
  await pickFile(page, csvFile('SKU;Količina\nLL-502181;2\n'));
  await waitPhase(page, '.dp-qo-import__table');
  check('recovers from a file error: next valid file proceeds to the result', log.validate.length === 1 && (await page.locator('.dp-qo-import__error').count()) === 0);
  await ctx.close();
}

// file pickers: xlsx, csv, drag & drop
for (const [label, mkFile, how] of [
  ['XLSX via file picker', () => path.join(fixtures, 'valid.xlsx'), 'pick'],
  ['CSV via file picker', () => csvFile('SKU;Količina\r\nLL-502181;2\r\n'), 'pick'],
  ['CSV via drag & drop', () => csvFile('SKU;Količina\r\nLL-502181;2\r\n'), 'drop'],
  ['XLSX via drag & drop', () => path.join(fixtures, 'valid.xlsx'), 'drop'],
]) {
  const { ctx, page, log } = await newPage({ mock: { validate: (b) => ok(b.rows.map((r) => R([r.row], r.identifier, 'ready', null, 101, 0, 'X', Number(r.quantity) || 1, Number(r.quantity) || 1))) } });
  await openModal(page);
  const f = mkFile();
  if (how === 'pick') await pickFile(page, f);
  else {
    const buf = typeof f === 'string' ? fs.readFileSync(f) : f.buffer;
    const name = typeof f === 'string' ? path.basename(f) : f.name;
    await page.evaluate(async ([b64, name]) => {
      const bytes = Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
      const dt = new DataTransfer(); dt.items.add(new File([bytes], name));
      const zone = document.querySelector('.dp-qo-import__drop');
      zone.dispatchEvent(new DragEvent('dragenter', { bubbles: true, cancelable: true, dataTransfer: dt }));
      zone.dispatchEvent(new DragEvent('dragover', { bubbles: true, cancelable: true, dataTransfer: dt }));
      zone.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: dt }));
    }, [buf.toString('base64'), name]);
  }
  await waitPhase(page, '.dp-qo-import__table');
  const sent = log.validate[0]?.body;
  check(`${label} → parsed locally and validated (rows only: row/identifier/quantity)`, sent && Object.keys(sent).join() === 'rows' && sent.rows.every((r) => Object.keys(r).join() === 'row,identifier,quantity'), sent);
  await ctx.close();
}

// ═══════════════════════════════════════════════════════════════════════════════════════════════ B. validation + result
currentGroup = 'B result';
{
  // progress state: truthful (indeterminate), explanatory checks, cancel aborts the request
  const { ctx, page, log } = await newPage({ mock: { validate: () => 'hang' } });
  await openModal(page);
  await pickFile(page, csvFile(DESIGN_CSV));
  await waitPhase(page, '.dp-qo-import__spinner');
  const v = await page.evaluate(() => {
    const pb = document.querySelector('[role=progressbar]');
    return {
      headline: document.querySelector('.dp-qo-import__headline')?.textContent, sub: document.querySelector('.dp-qo-import__sub')?.textContent,
      steps: [...document.querySelectorAll('.dp-qo-import__step')].map((s) => `${s.className.match(/--(done|current|todo)/)[1]}:${s.firstElementChild.nextSibling.textContent.split(' — ')[0]}`),
      indeterminate: pb.classList.contains('is-indeterminate') && !pb.hasAttribute('aria-valuenow'), pct: /\d+\s*%/.test(document.querySelector('.dp-qo-import__dialog').innerText),
      checks: [...document.querySelectorAll('.dp-qo-import__checks li')].map((l) => l.textContent), cancel: [...document.querySelectorAll('.dp-qo-import__foot button')].map((b) => b.textContent),
      ariaCurrent: document.querySelector('[aria-current=step]')?.textContent.split(' — ')[0],
    };
  });
  if (shotDir) await page.screenshot({ path: path.join(shotDir, 'validating-1440.png') });
  check('validation state: headline/sub, stepper (upload done, Validacija current), no fake percentage, indeterminate progressbar', v.headline === 'Provjeravamo vaš excel file' && v.steps.join() === 'done:File upload,current:Validacija,todo:Rezultat,todo:Dodavanje u košaricu' && v.indeterminate && !v.pct && v.ariaCurrent === 'Validacija', v);
  check('validation state: 4 explanatory checks (not ticked off one by one) + "Otkaži uvoz"', v.checks.length === 4 && v.cancel.join() === 'Otkaži uvoz', v);
  const aborted = page.waitForEvent('requestfailed', { timeout: 3000 }).then(() => true).catch(() => false);
  await page.click('.dp-qo-import__foot button');
  check('"Otkaži uvoz" during validation aborts the browser request and closes without any cart call', (await aborted) && !(await hasModal(page)) && log.sync.length === 0);
  await ctx.close();
}
{
  // the design's example result
  const { ctx, page, log } = await newPage({ mock: { validate: () => ok(DESIGN_ROWS) } });
  await openModal(page);
  await pickFile(page, csvFile(DESIGN_CSV));
  await waitPhase(page, '.dp-qo-import__table');
  const d = await page.evaluate(() => {
    const rows = [...document.querySelectorAll('.dp-qo-import__table tbody tr')].map((tr) => [...tr.children].map((td) => td.innerText.replace(/\s+/g, ' ').trim()));
    return {
      banner: document.querySelector('.dp-qo-import__banner')?.innerText.replace(/\s+/g, ' '), rows,
      heads: [...document.querySelectorAll('.dp-qo-import__table thead th')].map((t) => `${t.textContent}:${t.getAttribute('scope')}`),
      caption: document.querySelector('.dp-qo-import__table caption')?.textContent,
      classes: [...document.querySelectorAll('.dp-qo-import__table tbody tr')].map((tr) => tr.className.match(/--(\w+)$/)[1]),
      buttons: [...document.querySelectorAll('.dp-qo-import__foot > *')].map((b) => b.textContent), live: document.querySelector('.dp-qo-import [role=status]')?.textContent,
      stepCur: document.querySelector('[aria-current=step]')?.textContent.split(' — ')[0], region: document.querySelector('.dp-qo-import__table-wrap')?.getAttribute('role'),
    };
  });
  check('result banner: resulting units counted, spreadsheet rows stated, no raw stock anywhere', /4 artikla spremno za dodavanje u košaricu\. 2 artikla imaju greške\./.test(d.banner) && /Učitano redaka: 6\./.test(d.banner), d.banner);
  check('result table: SKU / Naziv proizvoda / Kol. / Status / Poruka headers with scope=col + caption', d.heads.join() === 'SKU:col,Naziv proizvoda:col,Kol.:col,Status:col,Poruka:col' && !!d.caption, d);
  check('ready row: final quantity, "Spremno", "/" message', JSON.stringify(d.rows[0]) === JSON.stringify(['LL-502181', 'Ledlenser P7R Core', '2', 'Spremno', '/']), d.rows[0]);
  check('adjusted row: "15 → 8", "Prilagođeno", adjusted message (not a hard error)', /^LL-502184 Ledlenser ML4 Warm Light traženo 15, prilagođeno na 8 15 → 8 Prilagođeno Količina je prilagođena dostupnoj zalihi\.$/.test(d.rows[3].join(' ')) && d.classes[3] === 'adjusted', d.rows[3]);
  check('error rows: safe copy; unresolved row shows submitted identifier + "—" name, no product info', d.rows[4][0] === 'XX-000001' && d.rows[4][1] === '—' && d.rows[4][3] === 'Greška' && d.rows[4][4] === 'Artikl nije pronađen ili nije dostupan.' && d.rows[5][4] === 'Artikl trenutno nije dostupan.', d.rows.slice(4));
  check('status never by colour alone (text label per row) + keyboard-reachable scroll region', d.rows.every((r) => ['Spremno', 'Prilagođeno', 'Greška'].includes(r[3])) && d.region === 'region', d.rows.map((r) => r[3]));
  check('footer: "Odaberi drugi file", "Otkaži uvoz", "Dodaj ispravne artikle"; stepper on Rezultat; polite status announced', d.buttons.join() === 'Odaberi drugi file,Otkaži uvoz,Dodaj ispravne artikle' && d.stepCur === 'Rezultat' && /Provjera je završena/.test(d.live), d);
  if (shotDir) { await page.screenshot({ path: path.join(shotDir, 'result-1440.png') }); }
  await ctx.close();
}
{
  // all error → no cart CTA
  const rows = [R([2], 'NOPE-1', 'error', 'identifier_not_found', null, null, null, 1, 0), R([3], 'PARENT', 'error', 'variable_parent', null, null, 'Boca Urban', 1, 0), R([4], 'DUP', 'error', 'ambiguous_identifier', null, null, null, 1, 0), R([5], 'BADQ', 'error', 'invalid_quantity', null, null, null, null, 0)];
  const { ctx, page } = await newPage({ mock: { validate: () => ok(rows) } });
  await openModal(page);
  await pickFile(page, csvFile('SKU;Količina\nNOPE-1;1\nPARENT;1\nDUP;1\nBADQ;x\n'));
  await waitPhase(page, '.dp-qo-import__table');
  const d = await page.evaluate(() => ({ buttons: [...document.querySelectorAll('.dp-qo-import__foot > *')].map((b) => b.textContent), msgs: [...document.querySelectorAll('.dp-qo-import__cell-msg')].map((c) => c.textContent), banner: document.querySelector('.dp-qo-import__banner-title').textContent }));
  check('all rows invalid: no enabled "Dodaj ispravne artikle"; path to choose another file / cancel', !d.buttons.includes('Dodaj ispravne artikle') && d.buttons.includes('Odaberi drugi file') && d.buttons.includes('Otkaži uvoz') && d.banner === 'Nijedan artikl nije moguće dodati.', d);
  check('Croatian copy: variable_parent / ambiguous / invalid_quantity / not found', d.msgs.join('|') === 'Artikl nije pronađen ili nije dostupan.|Proizvod ima varijacije.|Artikl nije moguće jednoznačno prepoznati.|Količina nije ispravna.', d.msgs);
  await page.click('text=Odaberi drugi file');
  check('"Odaberi drugi file" returns to a fresh upload state', (await page.locator('.dp-qo-import__drop').count()) === 1);
  await ctx.close();
}
{
  // duplicates merged + leading zero identifier sent verbatim + parser rejected rows shown + warnings
  const rows = [R([2, 5], 'LE304792', 'adjusted', null, 6194, 0, 'LaCoqueFrancaise Lara', 7, 5), R([3], '000046', 'ready', null, 12803, 12806, 'Boca Urban Basic', 2, 2)];
  const { ctx, page, log } = await newPage({ mock: { validate: () => ok(rows) } });
  await openModal(page);
  await pickFile(page, csvFile('SKU;Količina\r\nLE304792;3\r\n000046;2\r\nA;1;extra\r\nP-51651;4\r\n'));
  await waitPhase(page, '.dp-qo-import__table');
  const d = await page.evaluate(() => ({ rows: [...document.querySelectorAll('.dp-qo-import__table tbody tr')].map((tr) => tr.innerText.replace(/\s+/g, ' ').trim()), banner: document.querySelector('.dp-qo-import__banner').innerText.replace(/\s+/g, ' ') }));
  check('leading-zero identifier "000046" is submitted verbatim', log.validate[0].body.rows.some((r) => r.identifier === '000046'), log.validate[0].body.rows);
  check('merged duplicates are explained (rows listed) and the summary says duplicates were merged', /Spojeni duplikati \(retci: 2, 5\)/.test(d.rows[0]) && /Duplikati su spojeni po artiklu/.test(d.banner), d);
  check('structurally malformed CSV row is shown as an error (never sent to the server)', d.rows.some((r) => /Redak ima neispravan broj stupaca/.test(r)) && !log.validate[0].body.rows.some((r) => r.identifier === 'A'), d.rows);
  await ctx.close();
}
{
  // validation failure → error with SAFE retry (read-only)
  const { ctx, page, log } = await newPage({ mock: { validate: (b, i) => (i === 0 ? { status: 500, json: {} } : ok(b.rows.map((r) => R([r.row], r.identifier, 'ready', null, 101, 0, 'X', 1, 1)))) } });
  await openModal(page);
  await pickFile(page, csvFile('SKU;Količina\nLL-502181;1\n'));
  await waitPhase(page, '.dp-qo-import__box--warn');
  const msg = await text(page, '.dp-qo-import__box--warn');
  check('validation request failure → clear message, no cart call, buttons "Otkaži uvoz" + "Pokušaj ponovno"', /Provjera nije uspjela/.test(msg) && log.sync.length === 0 && (await page.locator('.dp-qo-import__foot button').allInnerTexts()).join() === 'Otkaži uvoz,Pokušaj ponovno');
  await page.click('text=Pokušaj ponovno');
  await waitPhase(page, '.dp-qo-import__table');
  check('retry (validation is read-only) re-validates and shows the result; exactly 2 validation requests', log.validate.length === 2 && log.sync.length === 0);
  await ctx.close();
}

// ═══════════════════════════════════════════════════════════════════════════════════════════════════ C. cart
currentGroup = 'C cart';
const unitRows = (n, startPid = 1000) => Array.from({ length: n }, (_, i) => R([i + 2], `SKU-${i}`, 'ready', null, startPid + i, 0, `Artikl ${i}`, 1, 1));
const bigCsv = (n) => csvFile('SKU;Količina\r\n' + Array.from({ length: n }, (_, i) => `SKU-${i};1`).join('\r\n'));
const addAll = (items) => ({ json: { synced: items.map((i) => ({ product_id: i.product_id, variation_id: i.variation_id, action: 'added', quantity: i.quantity })), totals: { total: 1 } } });
async function runImport(page, csv) { await openModal(page); await pickFile(page, csv); await waitPhase(page, '.dp-qo-import__table'); await page.click('.dp-qo-import__btn--primary'); }

{
  // all success
  const { ctx, page, log } = await newPage({ mock: { validate: () => ok(DESIGN_ROWS), sync: (items) => addAll(items) } });
  await runImport(page, csvFile(DESIGN_CSV));
  await waitPhase(page, '.dp-qo-import__bigicon--ok');
  const d = await page.evaluate(() => ({ head: document.querySelector('.dp-qo-import__headline').textContent, box: document.querySelector('.dp-qo-import__banner').innerText.replace(/\s+/g, ' '), buttons: [...document.querySelectorAll('.dp-qo-import__foot > *')].map((b) => `${b.textContent}|${b.getAttribute('href') ?? ''}`), steps: [...document.querySelectorAll('.dp-qo-import__step')].map((s) => s.className.match(/--(done|current|todo)/)[1]).join(), live: document.querySelector('.dp-qo-import [role=status]').textContent, pb: document.querySelector('[role=progressbar]')?.getAttribute('aria-valuenow') }));
  check('submits ONLY validated ready/adjusted units (normalized quantities) to /cart/sync', log.sync.length === 1 && JSON.stringify(log.sync[0].items) === JSON.stringify([{ product_id: 101, variation_id: 0, quantity: 2 }, { product_id: 102, variation_id: 0, quantity: 2 }, { product_id: 103, variation_id: 0, quantity: 2 }, { product_id: 104, variation_id: 0, quantity: 8 }]), log.sync);
  check('final success: all steps done, "Artikli uspješno dodani u košaricu", count of units, Zatvori + Pogledaj košaricu → cart URL', d.steps === 'done,done,done,done' && /Artikli uspješno dodani u košaricu 4 artikla uspješno dodano!/.test(d.box) && d.buttons.join() === 'Zatvori|,Pogledaj košaricu|http://localhost:8080/dp-b2b/cart/', d);
  check('success announced politely; bar at 100%', /Artikli uspješno dodani/.test(d.live) && d.pb === '100', d);
  if (shotDir) await page.screenshot({ path: path.join(shotDir, 'success-1440.png') });
  await page.click('.dp-qo-import__foot button');
  check('Zatvori closes; reopening starts a FRESH session (upload state, no old result)', !(await hasModal(page)) && (await (async () => { await openModal(page); return (await page.locator('.dp-qo-import__drop').count()) === 1 && (await page.locator('.dp-qo-import__table').count()) === 0; })()));
  await ctx.close();
}
{
  // > 50 → chunks, sequential, REAL progress
  const { ctx, page, log } = await newPage({ mock: { validate: () => ok(unitRows(120)), sync: async (items) => { await new Promise((r) => setTimeout(r, 350)); return addAll(items); } } });
  await openModal(page);
  await pickFile(page, bigCsv(120));
  await waitPhase(page, '.dp-qo-import__table');
  await page.evaluate(() => { window.__pb = []; new MutationObserver(() => { const p = document.querySelector('[role=progressbar]'); if (p) window.__pb.push(`${p.getAttribute('aria-valuenow')}|${p.className.includes('indeterminate') ? 'ind' : 'det'}`); }).observe(document.querySelector('.dp-qo-import__dialog'), { subtree: true, attributes: true, childList: true }); });
  await page.click('.dp-qo-import__btn--primary');
  await waitPhase(page, '.dp-qo-import__bigicon--ok', 15000);
  const seen = await page.evaluate(() => [...new Set(window.__pb)]);
  const sizes = log.sync.map((s) => s.items.length);
  check('120 units → 3 sequential /cart/sync requests of 50 + 50 + 20', sizes.join() === '50,50,20' && (log.sync[1]?.t ?? 0) - (log.sync[0]?.t ?? 0) >= 300 && (log.sync[2]?.t ?? 0) - (log.sync[1]?.t ?? 0) >= 300, { sizes, cfg: await page.evaluate(() => window.dpQuickOrder.cartSyncMaxBatch) });
  check('progress = REAL chunk completion (33 → 67 → 100), starting indeterminate-free after first chunk', ['33|det', '67|det', '100|det'].every((p) => seen.includes(p)), seen);
  const all = log.sync.flatMap((s) => s.items.map((i) => i.product_id));
  check('all 120 units sent exactly once (no duplicates)', all.length === 120 && new Set(all).size === 120);
  await ctx.close();
}
{
  // partial failure in the final cart step (stock changed after validation)
  const fail = { 103: 'quantity_unavailable', 104: 'out_of_stock' };
  const { ctx, page, log } = await newPage({ mock: { validate: () => ok(DESIGN_ROWS), sync: (items) => ({ json: { synced: items.map((i) => (fail[i.product_id] ? { product_id: i.product_id, variation_id: i.variation_id, action: 'failed', error: fail[i.product_id] } : { product_id: i.product_id, variation_id: i.variation_id, action: 'added', quantity: i.quantity })) } }) } });
  await runImport(page, csvFile(DESIGN_CSV));
  await waitPhase(page, '.dp-qo-import__bigicon--warn');
  const d = await page.evaluate(() => ({ head: document.querySelector('.dp-qo-import__headline').textContent, box: document.querySelector('.dp-qo-import__banner').innerText.replace(/\s+/g, ' '), failed: [...document.querySelectorAll('.dp-qo-import__failed li')].map((l) => l.textContent.replace(/\s+/g, ' ')), ok: !!document.querySelector('.dp-qo-import__bigicon--ok'), buttons: [...document.querySelectorAll('.dp-qo-import__foot > *')].map((b) => b.textContent) }));
  check('partial cart failure: NOT the success state; added/failed counts; per-unit Slice 5 reasons; no stock figure', !d.ok && d.head === 'Dodavanje u košaricu je završeno' && /^Dio artikala nije dodan u košaricu Dodano: 2\. Nije dodano: 2\./.test(d.box) && /Količina nije dostupna\./.test(d.failed[0]) && /Trenutno nije na stanju\./.test(d.failed[1]) && !/\d+\s*(kom|komada|na stanju|u zalihi)/i.test(d.failed.join()), d);
  check('Slice 5 final cart authority wins: the normalized quantity was attempted once, no hidden second clamp / retry', log.sync.length === 1 && log.sync[0].items.find((i) => i.product_id === 104).quantity === 8, log.sync);
  if (shotDir) await page.screenshot({ path: path.join(shotDir, 'partial-1440.png') });
  await ctx.close();
}
{
  // failure only in a LATER chunk
  const { ctx, page, log } = await newPage({ mock: { validate: () => ok(unitRows(120)), sync: (items, i) => (i === 1 ? { json: { synced: items.map((x) => ({ product_id: x.product_id, variation_id: x.variation_id, action: 'failed', error: 'product_unavailable' })) } } : addAll(items)) } });
  await openModal(page); await pickFile(page, bigCsv(120)); await waitPhase(page, '.dp-qo-import__table'); await page.click('.dp-qo-import__btn--primary');
  await waitPhase(page, '.dp-qo-import__bigicon--warn', 15000);
  const box = await text(page, '.dp-qo-import__banner');
  check('failure in a later chunk: continues, final counts exact (added 70, not added 50), all 3 chunks sent', log.sync.length === 3 && /Dodano: 70\. Nije dodano: 50\./.test(box.replace(/\s+/g, ' ')), box);
  await ctx.close();
}
for (const [label, kind] of [['network failure', 'abort'], ['HTTP 500', { status: 500, json: {} }]]) {
  // ambiguous: chunk 2 of 3 lost → stop at the boundary, no retry, no further chunk
  const { ctx, page, log } = await newPage({ mock: { validate: () => ok(unitRows(120)), sync: (items, i) => (i === 1 ? kind : addAll(items)) } });
  await openModal(page); await pickFile(page, bigCsv(120)); await waitPhase(page, '.dp-qo-import__table'); await page.click('.dp-qo-import__btn--primary');
  await waitPhase(page, '.dp-qo-import__bigicon--warn', 15000);
  await page.waitForTimeout(600);
  const d = await page.evaluate(() => ({ head: document.querySelector('.dp-qo-import__headline').textContent, box: document.querySelector('.dp-qo-import__banner').innerText.replace(/\s+/g, ' '), buttons: [...document.querySelectorAll('.dp-qo-import__foot > *')].map((b) => `${b.textContent}|${b.getAttribute('href') ?? ''}`), ok: !!document.querySelector('.dp-qo-import__bigicon--ok') }));
  check(`ambiguous (${label} on chunk 2): stops at the boundary — chunk 3 NOT sent, chunk 2 NOT retried (exactly 2 requests)`, log.sync.length === 2, log.sync.map((s) => s.items.length));
  check(`ambiguous (${label}): approved message, no success state, honest counts (added 50 / unknown 50 / not sent 20), cart review link, no retry`, !d.ok && /Nismo mogli potvrditi je li dodano\. Provjerite košaricu prije ponovnog pokušaja\./.test(d.box) && /Dodano: 50\. Ishod nepoznat: 50\. Nije poslano: 20\./.test(d.box) && d.buttons.join() === 'Zatvori|,Pregled košarice|http://localhost:8080/dp-b2b/cart/', d);
  await ctx.close();
}
{
  // single chunk: no fake percentage while the one request is in flight
  const { ctx, page } = await newPage({ mock: { validate: () => ok(unitRows(3)), sync: async (items) => { await new Promise((r) => setTimeout(r, 900)); return addAll(items); } } });
  await openModal(page); await pickFile(page, bigCsv(3)); await waitPhase(page, '.dp-qo-import__table'); await page.click('.dp-qo-import__btn--primary');
  await waitPhase(page, '.dp-qo-import__headline');
  const d = await page.evaluate(() => ({ head: document.querySelector('.dp-qo-import__headline').textContent, sub: document.querySelector('.dp-qo-import__sub').textContent, ind: document.querySelector('[role=progressbar]').className.includes('is-indeterminate'), hasNow: document.querySelector('[role=progressbar]').hasAttribute('aria-valuenow'), pct: /\d+\s*%/.test(document.querySelector('.dp-qo-import__dialog').innerText), steps: [...document.querySelectorAll('.dp-qo-import__step')].map((s) => s.className.match(/--(done|current|todo)/)[1]).join(), btns: document.querySelectorAll('.dp-qo-import__foot > *').length, closeHidden: document.querySelector('.dp-qo-import__close').hidden }));
  check('single request: indeterminate (no invented percentage); "Dodajemo 3 ispravna artikla…"; stepper on step 4', d.head === 'Dodavanje u košaricu je u toku' && d.sub === 'Dodajemo 3 ispravna artikla u vašu košaricu' && d.ind && !d.hasNow && !d.pct && d.steps === 'done,done,done,current', d);
  check('while the cart request is in flight there is NO cancel/close control (no false cancellation)', d.btns === 0 && d.closeHidden, d);
  await page.keyboard.press('Escape');
  await page.waitForTimeout(150);
  check('Escape during the in-flight cart request does NOT close the modal', await hasModal(page));
  if (shotDir) await page.screenshot({ path: path.join(shotDir, 'adding-1440.png') });
  await waitPhase(page, '.dp-qo-import__bigicon--ok');
  await ctx.close();
}

// ═══════════════════════════════════════════════════════════════════════════════════════════════════ D. state
currentGroup = 'D state';
{
  const mock = { validate: () => ok([R([2], 'LL-502181', 'ready', null, 101, 0, 'Ledlenser P7R Core', 5, 5), R([3], 'LL-502182', 'ready', null, 102, 0, 'Ledlenser H19R Signature', 1, 1)]), sync: (items) => addAll(items) };
  const { ctx, page, log } = await newPage({ mock });
  // manual, UNSENT selection in the visible Quick Order list: product 101 × 3
  await page.evaluate(() => { const i = document.querySelector('.dp-qo-card .dp-qo-qty'); i.value = '3'; i.dispatchEvent(new Event('input', { bubbles: true })); i.dispatchEvent(new Event('change', { bubbles: true })); });
  const before = await page.evaluate(() => ({ rows: window.dpQuickOrder.state.getRowCount(), items: window.dpQuickOrder.state.getItemCount(), footer: document.querySelector('.dp-qo-footer__items').textContent, ls: JSON.stringify({ ...localStorage }), ss: JSON.stringify({ ...sessionStorage }) }));
  await runImport(page, csvFile('SKU;Količina\nLL-502181;5\nLL-502182;1\n'));
  await waitPhase(page, '.dp-qo-import__bigicon--ok');
  const warn = await page.evaluate(() => document.querySelector('.dp-qo-import__overlap')?.textContent);
  check('overlap (unit also selected manually in QO) → informational warning, success not blocked', warn === 'Neki artikli i dalje imaju unesene količine u Quick Order popisu.', warn);
  await page.click('.dp-qo-import__foot button');
  const after = await page.evaluate(() => ({ rows: window.dpQuickOrder.state.getRowCount(), items: window.dpQuickOrder.state.getItemCount(), qty: document.querySelector('.dp-qo-card .dp-qo-qty').value, footer: document.querySelector('.dp-qo-footer__items').textContent, ls: JSON.stringify({ ...localStorage }), ss: JSON.stringify({ ...sessionStorage }) }));
  check('existing unsent QO selection is untouched (3 stays 3; row/item counts and footer unchanged)', after.rows === before.rows && after.items === before.items && after.qty === '3' && after.footer === before.footer, { before, after });
  check('private import state: nothing written to localStorage/sessionStorage', after.ls === before.ls && after.ss === before.ss);
  check('only the import units reached /cart/sync — the manual QO selection was NOT submitted by the import', log.sync.length === 1 && log.sync[0].items.length === 2);
  await ctx.close();
}
{
  const { ctx, page } = await newPage({ mock: { validate: () => ok([R([2], 'LL-502181', 'ready', null, 101, 0, 'Ledlenser P7R Core', 1, 1)]), sync: (items) => addAll(items) } });
  await page.evaluate(() => { const i = document.querySelectorAll('.dp-qo-card .dp-qo-qty')[1]; i.value = '2'; i.dispatchEvent(new Event('input', { bubbles: true })); i.dispatchEvent(new Event('change', { bubbles: true })); });
  await runImport(page, csvFile('SKU;Króvina\nLL-502181;1\n'.replace('Króvina', 'Količina')));
  await waitPhase(page, '.dp-qo-import__bigicon--ok');
  check('no overlap (different unit selected in QO) → no warning', (await page.locator('.dp-qo-import__overlap').count()) === 0);
  await ctx.close();
}
{
  // close semantics + reset
  const { ctx, page, log } = await newPage({ mock: { validate: () => ok(DESIGN_ROWS) } });
  await openModal(page);
  await pickFile(page, csvFile(DESIGN_CSV));
  await waitPhase(page, '.dp-qo-import__table');
  await page.click('.dp-qo-import__close');
  check('closing in the result review: safe, no cart mutation happened', !(await hasModal(page)) && log.sync.length === 0);
  await openModal(page);
  check('reopening after close: fresh upload state (previous result discarded)', (await page.locator('.dp-qo-import__table').count()) === 0 && (await page.locator('.dp-qo-import__drop').count()) === 1);
  await ctx.close();
}

// ═══════════════════════════════════════════════════════════════════════════════════════════ E. accessibility
currentGroup = 'E a11y';
{
  const { ctx, page } = await newPage({ mock: { validate: () => ok(DESIGN_ROWS) } });
  await page.focus('[data-dp-qo-import]');
  await page.keyboard.press('Enter');
  await page.waitForSelector('.dp-qo-import__dialog');
  check('keyboard: Enter on the trigger opens the modal and moves focus into it (heading)', await page.evaluate(() => document.activeElement === document.querySelector('.dp-qo-import__title')));
  const inside = [];
  const stops = [];
  for (let i = 0; i < 14; i++) { await page.keyboard.press('Tab'); const s = await page.evaluate(() => ({ in: !!document.activeElement.closest('.dp-qo-import__dialog'), tag: document.activeElement.tagName, label: (document.activeElement.getAttribute('aria-label') ?? document.activeElement.textContent).trim().slice(0, 24) })); inside.push(s.in); stops.push(`${s.tag}:${s.label}`); }
  check('Tab cycle: focus never leaves the dialog and wraps around (14 tabs)', inside.every(Boolean) && new Set(stops).size < 14, stops);
  await page.keyboard.press('Shift+Tab');
  check('Shift+Tab stays inside the dialog', await page.evaluate(() => !!document.activeElement.closest('.dp-qo-import__dialog')));
  await page.keyboard.press('Escape');
  check('Escape closes in a safe state (upload) and restores focus to the "Excel Import" trigger', !(await hasModal(page)) && (await page.evaluate(() => document.activeElement === document.querySelector('[data-dp-qo-import]'))));
  // result state keyboard
  await openModal(page);
  await pickFile(page, csvFile(DESIGN_CSV));
  await waitPhase(page, '.dp-qo-import__table');
  const focusInResult = await page.evaluate(() => document.activeElement === document.querySelector('.dp-qo-import__title'));
  await page.keyboard.press('Escape');
  check('result review: focus moved to the new view, Escape closes, focus restored to trigger', focusInResult && !(await hasModal(page)) && (await page.evaluate(() => document.activeElement === document.querySelector('[data-dp-qo-import]'))));
  await ctx.close();
}
{
  // browser must not navigate away when a file is dropped OUTSIDE the zone
  const { ctx, page } = await newPage();
  await openModal(page);
  const prevented = await page.evaluate(() => { const dt = new DataTransfer(); dt.items.add(new File(['x'], 'a.csv')); const e = new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: dt }); document.querySelector('.dp-qo-import__dialog').dispatchEvent(e); return e.defaultPrevented; });
  check('a file dropped outside the drop zone is swallowed (no navigation away from Quick Order)', prevented);
  await ctx.close();
}

// ═══════════════════════════════════════════════════════════════════════════════════════════ F. 390px
currentGroup = 'F 390px';
{
  const { ctx, page } = await newPage({ width: 390, height: 844, mock: { validate: () => ok(DESIGN_ROWS), sync: (items) => addAll(items) } });
  const metrics = (extra = '') => page.evaluate(() => {
    const dlg = document.querySelector('.dp-qo-import__dialog').getBoundingClientRect();
    const vis = (el) => { if (!el) return null; const r = el.getBoundingClientRect(); return { l: Math.round(r.left), r: Math.round(r.right), t: Math.round(r.top), b: Math.round(r.bottom) }; };
    const body = document.querySelector('.dp-qo-import__body');
    return { docOverflow: document.documentElement.scrollWidth > window.innerWidth, dlg: { l: Math.round(dlg.left), r: Math.round(dlg.right), t: Math.round(dlg.top), b: Math.round(dlg.bottom) }, close: vis(document.querySelector('.dp-qo-import__close')), addFile: vis(document.querySelector('.dp-qo-import__add-file')), primary: vis(document.querySelector('.dp-qo-import__btn--primary')), foot: vis(document.querySelector('.dp-qo-import__foot')), bodyScrolls: body.scrollHeight > body.clientHeight, tableWrapScrollsX: (() => { const w = document.querySelector('.dp-qo-import__table-wrap'); return w ? w.scrollWidth > w.clientWidth : null; })(), vh: window.innerHeight, vw: window.innerWidth };
  });
  await openModal(page);
  const u = await metrics();
  check('upload @390: no page-level horizontal overflow, dialog inside viewport, close + "Dodaj file" reachable', !u.docOverflow && u.dlg.l >= 0 && u.dlg.r <= 390 && u.dlg.b <= u.vh && u.close.r <= 390 && u.close.t >= 0 && u.addFile.b <= u.vh + 1, u);
  if (shotDir) await page.screenshot({ path: path.join(shotDir, 'upload-390.png') });
  await pickFile(page, csvFile(DESIGN_CSV));
  await waitPhase(page, '.dp-qo-import__table');
  const r = await metrics();
  check('result @390: dialog fits, table scrolls horizontally inside its own container, footer + CTA reachable, no page overflow', !r.docOverflow && r.dlg.r <= 390 && r.tableWrapScrollsX === true && r.primary && r.primary.b <= r.vh && r.primary.l >= 0 && r.primary.r <= 390 && r.foot.b <= r.vh, r);
  if (shotDir) await page.screenshot({ path: path.join(shotDir, 'result-390.png') });
  await page.click('.dp-qo-import__btn--primary');
  await waitPhase(page, '.dp-qo-import__bigicon--ok');
  const s = await metrics();
  check('success @390: both buttons reachable inside the viewport', !s.docOverflow && s.primary.b <= s.vh && s.primary.r <= 390, s);
  if (shotDir) await page.screenshot({ path: path.join(shotDir, 'success-390.png') });
  await ctx.close();
}

// ═══════════════════════════════════════════════════════════════════════════════════════ G. main QO path unchanged
currentGroup = 'G main QO';
{
  // the shared CartSubmit change must not alter the visible Quick Order submit
  const { ctx, page, log } = await newPage({ mock: { sync: (items) => ({ json: { synced: items.map((i) => ({ product_id: i.product_id, variation_id: i.variation_id, action: 'failed', error: 'quantity_unavailable' })) } }) } });
  await page.evaluate(() => { const i = document.querySelector('.dp-qo-card .dp-qo-qty'); i.value = '50'; i.dispatchEvent(new Event('input', { bubbles: true })); i.dispatchEvent(new Event('change', { bubbles: true })); });
  await page.evaluate(() => document.querySelector('.dp-qo-footer__add-to-cart').click());
  await page.waitForSelector('.dp-qo-line__error');
  const d = await page.evaluate(() => ({ err: document.querySelector('.dp-qo-line__error').textContent.trim(), status: document.querySelector('.dp-qo-footer__status').textContent, kept: window.dpQuickOrder.state.getRowCount() }));
  check('main QO submit unchanged: typed row error + footer summary, quantity kept (Slice 5 behaviour)', d.err === 'Količina nije dostupna.' && /Nije dodano: 1/.test(d.status) && d.kept === 1 && log.sync.length === 1, d);
  await ctx.close();
}
{
  const { ctx, page, log } = await newPage({ mock: { sync: () => 'abort' } });
  await page.evaluate(() => { const i = document.querySelector('.dp-qo-card .dp-qo-qty'); i.value = '2'; i.dispatchEvent(new Event('input', { bubbles: true })); i.dispatchEvent(new Event('change', { bubbles: true })); });
  await page.evaluate(() => document.querySelector('.dp-qo-footer__add-to-cart').click());
  await page.waitForFunction(() => /Nismo mogli potvrditi/.test(document.querySelector('.dp-qo-footer__status')?.textContent ?? ''));
  check('main QO ambiguous failure unchanged: approved message, quantity kept, no auto-retry', log.sync.length === 1 && (await page.evaluate(() => window.dpQuickOrder.state.getRowCount())) === 1, log.sync.length);
  await ctx.close();
}

// ─────────────────────────────────────────────────────────────────────────────────────────────── console hygiene
currentGroup = 'H hygiene';
{
  const { ctx, page, log } = await newPage({ mock: { validate: () => ok(DESIGN_ROWS), sync: (items) => addAll(items) } });
  await runImport(page, csvFile(DESIGN_CSV));
  await waitPhase(page, '.dp-qo-import__bigicon--ok');
  check('no page errors / console errors across a full import', log.errors.length === 0 && log.consoleErrors.filter((e) => !/Failed to load resource/.test(e)).length === 0, { errors: log.errors, console: log.consoleErrors });
  // the modal renders every value as text: a hostile name/identifier must not become markup
  await ctx.close();
}
{
  const evil = '<img src=x onerror="window.__xss=1">';
  const { ctx, page } = await newPage({ mock: { validate: () => ok([R([2], evil, 'ready', null, 101, 0, evil, 1, 1)]) } });
  await openModal(page);
  await pickFile(page, csvFile(`SKU;Količina\n${evil.replace(/"/g, '""').replace(/^/, '"').replace(/$/, '"')};1\n`));
  await waitPhase(page, '.dp-qo-import__table');
  await page.waitForTimeout(300);
  check('hostile identifier/name is rendered as inert text (no element injected, no script ran)', (await page.evaluate(() => !window.__xss && document.querySelectorAll('.dp-qo-import__table img').length === 0)) && /<img/.test(await text(page, '.dp-qo-import__cell-name')));
  await ctx.close();
}

await browser.close();
const failed = results.filter((r) => !r.ok);
console.log(`\n${results.length - failed.length}/${results.length} passed${failed.length ? ` — ${failed.length} FAILED` : ''}`);
process.exit(failed.length ? 1 : 0);
