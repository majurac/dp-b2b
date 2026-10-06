// Browser parser tests (real Chromium DOMParser/TextDecoder). Run:
//
//   npm run build:import
//   py tests/import/gen-fixtures.py <outdir>
//   node tests/import/run-parser-tests.mjs <outdir>
//
// Uses the persistent local Playwright install (%USERPROFILE%\.qa\playwright) — no per-project dependency.
import { createRequire } from 'node:module';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(path.join(process.env.QA_PLAYWRIGHT ?? path.join(os.homedir(), '.qa', 'playwright'), 'x.js'));
const { chromium } = require('playwright');

const fixtures = path.resolve(process.argv[2] ?? '');
const bundle = path.resolve(here, '../../assets/dist/quick-order-import.js');
const enc = new TextEncoder();
const csv = (s) => Buffer.from(s, 'utf8');
const bom = (s) => Buffer.concat([Buffer.from([0xEF, 0xBB, 0xBF]), Buffer.from(s, 'utf8')]);
const lines = (n) => Array.from({ length: n }, (_, i) => `ID${String(i).padStart(4, '0')};1`).join('\r\n');

// expect: { rows?: n, first?: {identifier, quantity}, rejected?: n, warnings?: [codes], error?: code }
const cases = [
  // ---- XLSX
  ['xlsx valid template-shaped', 'valid.xlsx', { rows: 3, first: { identifier: 'JS/C/B/+0', quantity: '3' }, row3: { identifier: 'Kod, s zarezom & razmakom' } }],
  ['xlsx leading-zero text preserved', 'leading-zero.xlsx', { rows: 2, first: { identifier: '000046', quantity: '2' }, row2: { identifier: '46' } }],
  ['xlsx 500 rows accepted', 'rows-500.xlsx', { rows: 500 }],
  ['xlsx 501 rows rejected', 'rows-501.xlsx', { error: 'too_many_rows' }],
  ['xlsx header aliases (Šifra artikla / KOLICINA)', 'header-alias.xlsx', { rows: 1 }],
  ['xlsx EAN header NOT accepted', 'header-ean.xlsx', { error: 'invalid_header' }],
  ['xlsx no header', 'no-header.xlsx', { error: 'invalid_header' }],
  ['xlsx header only', 'empty.xlsx', { error: 'empty_file' }],
  ['xlsx inline strings', 'inline-strings.xlsx', { rows: 1, first: { identifier: 'INL-1', quantity: '7' } }],
  ['xlsx blank rows / extra columns', 'blank-rows-and-extra-cols.xlsx', { rows: 2, first: { row: 2 }, row2: { row: 9 }, warnings: ['extra_columns_ignored'] }],
  ['xlsx hidden first sheet → visible one used', 'hidden-first.xlsx', { rows: 1, first: { identifier: 'VISIBLE-1' }, warnings: [] }],
  ['xlsx only hidden sheets', 'very-hidden-only.xlsx', { error: 'xlsx_no_visible_sheet' }],
  ['xlsx multiple visible → first used + warning', 'multi-visible.xlsx', { rows: 1, first: { identifier: 'FIRST-1' }, warnings: ['multiple_sheets'] }],
  ['xlsx hidden rows reported', 'hidden-rows.xlsx', { rows: 2, warnings: ['hidden_rows_included'] }],
  ['xlsx formula with cached value (inert)', 'formula-cached.xlsx', { rows: 1, first: { quantity: '6' }, warnings: ['formula_cached_values_used'] }],
  ['xlsx formula without cached value', 'formula-novalue.xlsx', { rows: 1, first: { quantity: '' }, warnings: ['formula_without_value'] }],
  ['xlsx formula string identifier (cached)', 'formula-string-id.xlsx', { rows: 1, first: { identifier: 'CONCAT1' } }],
  ['xlsx boolean quantity is not "1"', 'boolean-quantity.xlsx', { rows: 1, first: { quantity: 'TRUE' } }],
  ['xlsx VBA part rejected', 'vba.xlsx', { error: 'xlsx_macro_content' }],
  ['xlsx macro content-type rejected', 'macro-content-type.xlsx', { error: 'xlsx_macro_content' }],
  ['xlsx macroEnabled text in content types rejected', 'macro-wb-type.xlsx', { error: 'xlsx_macro_content' }],
  ['xlsx external links ignored safely', 'external-links.xlsx', { rows: 1, warnings: ['external_links_ignored'] }],
  ['xlsx huge sparse dimension', 'sparse-dimension.xlsx', { rows: 3, first: { row: 1000000 }, row2: { row: 1048575 } }],
  ['xlsx excessive ZIP entries', 'many-entries.xlsx', { error: 'xlsx_too_many_entries' }],
  ['xlsx DOCTYPE/entity rejected', 'doctype.xlsx', { error: 'invalid_file' }],
  ['xlsx malformed XML', 'malformed-xml.xlsx', { error: 'invalid_file' }],
  ['xlsx malformed ZIP (truncated)', 'malformed-zip.xlsx', { error: 'invalid_file' }],
  ['xlsx not a ZIP at all', 'not-a-zip.xlsx', { error: 'invalid_file' }],
  ['xlsx > 2 MB compressed', 'over-2mb.xlsx', { error: 'file_too_large' }],
  ['xlsx worksheet over uncompressed cap', 'sheet-over-cap.xlsx', { error: 'xlsx_too_large_uncompressed' }],
  ['xlsx total uncompressed over cap (35 MB zeros)', 'total-over-cap.xlsx', { error: 'xlsx_too_large_uncompressed' }],
  ['xlsx LYING declared size (no expansion)', 'lying-declared-size.xlsx', { error: 'invalid_file', maxMs: 2000 }],
  ['committed xlsx template: header valid, no data rows', () => ['t.xlsx', fs.readFileSync(path.resolve(here, '../../assets/templates/dp-quick-order-import-template.xlsx'))], { error: 'empty_file' }],
  ['committed csv template: header valid, no data rows', () => ['t.csv', fs.readFileSync(path.resolve(here, '../../assets/templates/dp-quick-order-import-template.csv'))], { error: 'empty_file' }],
  ['extension .xls rejected', ['x.xls', Buffer.from('abc')], { error: 'unsupported_format' }],
  ['extension .xlsm rejected', ['x.xlsm', Buffer.from('abc')], { error: 'unsupported_format' }],
  ['empty file', ['x.csv', Buffer.alloc(0)], { error: 'empty_file' }],
  ['xlsx container named .csv rejected', () => ['x.csv', fs.readFileSync(path.join(fixtures, 'valid.xlsx'))], { error: 'invalid_file' }],

  // ---- CSV
  ['csv utf-8 semicolon', ['a.csv', csv('SKU;Količina\r\nAB-1;5\r\nCD 2;7\r\n')], { rows: 2, first: { identifier: 'AB-1', quantity: '5', row: 2 } }],
  ['csv utf-8 BOM', ['a.csv', bom('SKU;Količina\nAB-1;5\n')], { rows: 1, first: { identifier: 'AB-1' } }],
  ['csv comma delimiter', ['a.csv', csv('SKU,Količina\nAB-1,5\n')], { rows: 1, first: { quantity: '5' } }],
  ['csv quoted comma inside identifier (comma file)', ['a.csv', csv('SKU,Količina\n"JS/C, B",3\n')], { rows: 1, first: { identifier: 'JS/C, B', quantity: '3' } }],
  ['csv quoted semicolon inside identifier', ['a.csv', csv('SKU;Količina\n"A;B";3\n')], { rows: 1, first: { identifier: 'A;B' } }],
  ['csv UNQUOTED comma in identifier (semicolon file) is data', ['a.csv', csv('SKU;Količina\nA,B;3\n')], { rows: 1, first: { identifier: 'A,B' } }],
  ['csv escaped quote', ['a.csv', csv('SKU;Količina\n"A ""B"" C";3\n')], { rows: 1, first: { identifier: 'A "B" C' } }],
  ['csv LF only / CRLF / lone CR', ['a.csv', csv('SKU;Količina\rA;1\r\nB;2\nC;3')], { rows: 3 }],
  ['csv blank lines skipped', ['a.csv', csv('SKU;Količina\n\nA;1\n\n\nB;2\n\n')], { rows: 2, row2: { row: 6 } }],
  ['csv stray quote → malformed', ['a.csv', csv('SKU;Količina\nA"B;1\n')], { error: 'malformed_csv' }],
  ['csv unterminated quote → malformed', ['a.csv', csv('SKU;Količina\n"AB;1\nC;2\n')], { error: 'malformed_csv' }],
  ['csv text after closing quote → malformed', ['a.csv', csv('SKU;Količina\n"A"B;1\n')], { error: 'malformed_csv' }],
  ['csv wrong header', ['a.csv', csv('Code;Qty\nA;1\n')], { error: 'invalid_header' }],
  ['csv missing header', ['a.csv', csv('A;1\nB;2\n')], { error: 'invalid_header' }],
  ['csv EAN header NOT accepted', ['a.csv', csv('EAN;Količina\n1;1\n')], { error: 'invalid_header' }],
  ['csv extra columns → rejected row, not repaired', ['a.csv', csv('SKU;Količina\nA;1;zz\nB;2\n')], { rows: 1, rejected: 1, first: { identifier: 'B' } }],
  ['csv missing column → rejected row', ['a.csv', csv('SKU;Količina\nA\nB;2\n')], { rows: 1, rejected: 1 }],
  ['csv header with extra column', ['a.csv', csv('SKU;Količina;Opis\nA;1;x\n')], { error: 'invalid_header' }],
  ['csv 500 rows', ['a.csv', csv('SKU;Količina\r\n' + lines(500))], { rows: 500 }],
  ['csv 501 rows', ['a.csv', csv('SKU;Količina\r\n' + lines(501))], { error: 'too_many_rows' }],
  ['csv windows-1250 fallback (Količina as 0xE8)', ['a.csv', Buffer.concat([Buffer.from('SKU;Koli'), Buffer.from([0xE8]), Buffer.from('ina\nA;1\n')])], { rows: 1 }],
  ['csv leading zeros preserved as text', ['a.csv', csv('SKU;Količina\n000046;2\n')], { rows: 1, first: { identifier: '000046' } }],
  ['csv NUL bytes rejected', ['a.csv', Buffer.from([0x53, 0x00, 0x4B, 0x00])], { error: 'invalid_file' }],
  ['csv formula-looking cells stay inert text', ['a.csv', csv('SKU;Količina\n=1+1;+5\n')], { rows: 1, first: { identifier: '=1+1', quantity: '+5' } }],
  ['csv oversize identifier is bounded', ['a.csv', csv('SKU;Količina\n' + 'X'.repeat(5000) + ';1\n')], { rows: 1, lenId: 65 }],
  ['csv boundary normalization: tab/NBSP trimmed, internal whitespace kept', ['a.csv', csv('SKU;Koli\u010dina\n\t AB  1\u00A0;\u00A07\t\n')], { rows: 1, first: { identifier: 'AB  1', quantity: '7' } }],
  ['csv boundary normalization: only the boundary class (em-space is NOT trimmed)', ['a.csv', csv('SKU;Koli\u010dina\n\u2003AB;1\n')], { rows: 1, lenId: 3 }],
  ['csv header only', ['a.csv', csv('SKU;Količina\n')], { error: 'empty_file' }],
];

const browser = await chromium.launch({ channel: process.env.QA_CHANNEL ?? 'chrome' });
const page = await browser.newPage();
await page.setContent('<!doctype html><html><body></body></html>');
await page.addScriptTag({ path: bundle });
await page.evaluate(() => { window.dpQuickOrder = {}; });

let failed = 0;
const times = [];
for (const [title, input, expect] of cases) {
  let name, buf;
  const resolved = typeof input === 'function' ? input() : input;
  if (typeof resolved === 'string') { name = resolved; buf = fs.readFileSync(path.join(fixtures, resolved)); }
  else { [name, buf] = resolved; }

  const t0 = Date.now();
  const res = await page.evaluate(async ([fname, b64]) => {
    const bytes = Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
    const file = new File([bytes], fname);
    const s = performance.now();
    try {
      const r = await window.dpQuickOrderImport.parseFile(file);
      return { ok: true, r, ms: performance.now() - s };
    } catch (e) {
      return { ok: false, code: e.code ?? `UNEXPECTED:${e.message}`, ms: performance.now() - s };
    }
  }, [name, buf.toString('base64')]);
  const wall = Date.now() - t0;
  times.push([title, res.ms]);

  const problems = [];
  if (expect.error) {
    if (res.ok) problems.push(`expected error ${expect.error}, got ok (${res.r.rows.length} rows)`);
    else if (res.code !== expect.error) problems.push(`expected error ${expect.error}, got ${res.code}`);
  } else if (!res.ok) {
    problems.push(`unexpected error ${res.code}`);
  } else {
    const { rows, rejected, warnings } = res.r;
    if (expect.rows !== undefined && rows.length !== expect.rows) problems.push(`rows ${rows.length} != ${expect.rows}`);
    if (expect.rejected !== undefined && rejected.length !== expect.rejected) problems.push(`rejected ${rejected.length} != ${expect.rejected}`);
    for (const [k, idx] of [['first', 0], ['row2', 1], ['row3', 2]]) {
      for (const [f, v] of Object.entries(expect[k] ?? {})) if (rows[idx]?.[f] !== v && String(rows[idx]?.[f]) !== String(v)) problems.push(`${k}.${f} = ${JSON.stringify(rows[idx]?.[f])} != ${JSON.stringify(v)}`);
    }
    if (expect.warnings) {
      const got = warnings.map((w) => w.code).sort().join(',');
      if (got !== [...expect.warnings].sort().join(',')) problems.push(`warnings [${got}] != [${expect.warnings}]`);
    }
    if (expect.lenId && rows[0].identifier.length !== expect.lenId) problems.push(`identifier length ${rows[0].identifier.length} != ${expect.lenId}`);
  }
  if (expect.maxMs && wall > expect.maxMs) problems.push(`took ${wall} ms > ${expect.maxMs}`);

  if (problems.length) { failed++; console.log(`FAIL  ${title}\n      ${problems.join('\n      ')}`); }
  else console.log(`ok    ${title}${res.ms > 50 ? `  (${res.ms.toFixed(0)} ms)` : ''}`);
}


// ---- file → parse → validation request (client glue; server answers are mocked here, the real server
// ---- contract is covered by validator-test.php)
async function glue(title, setup, check) {
  const ctx = await browser.newContext();
  const pg = await ctx.newPage();
  await pg.setContent('<!doctype html><html><body></body></html>');
  await pg.addScriptTag({ path: bundle });
  let seen = null;
  await setup(pg, (req) => { seen = req; });
  const res = await pg.evaluate(async ([b64]) => {
    window.dpQuickOrder = { importValidateUrl: 'http://localhost/validate', wpNonce: 'NONCE123', importTimeoutMs: 400, importMaxRows: 500 };
    const bytes = Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
    const file = new File([bytes], 'order.csv');
    try { return await window.dpQuickOrderImport.importFile(file); } catch (e) { return { thrown: e.code ?? e.message }; }
  }, [Buffer.from('SKU;Količina\r\nAB-1;5\r\n"C, D";7\r\n', 'utf8').toString('base64')]);
  const problems = check(res, seen);
  await ctx.close();
  if (problems.length) { failed++; total++; console.log(`FAIL  ${title}\n      ${problems.join('\n      ')}`); }
  else { total++; console.log(`ok    ${title}`); }
}
let total = cases.length;
const okBody = { rows: [{ rows: [2], identifier: 'AB-1', status: 'ready', code: null, product_id: 1, variation_id: 0, name: 'X', requested: 5, quantity: 5 }], summary: { ready: 1, adjusted: 0, error: 0, input_rows: 2 } };
await glue('glue: posts ONLY {row, identifier, quantity} (no ids/names), with REST nonce', async (pg, rec) => {
  await pg.route('**/validate', (route) => { rec({ body: route.request().postDataJSON(), headers: route.request().headers() }); route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(okBody) }); });
}, (res, seen) => {
  const p = [];
  if (!res.validation?.ok) p.push('validation not ok ' + JSON.stringify(res));
  const rows = seen?.body?.rows;
  if (JSON.stringify(Object.keys(seen?.body ?? {})) !== '["rows"]') p.push('body keys ' + JSON.stringify(Object.keys(seen?.body ?? {})));
  if (!rows || rows.length !== 2 || JSON.stringify(Object.keys(rows[0])) !== '["row","identifier","quantity"]') p.push('row shape ' + JSON.stringify(rows));
  if (rows?.[1]?.identifier !== 'C, D' || rows?.[0]?.row !== 2) p.push('row values ' + JSON.stringify(rows));
  if (seen?.headers['x-wp-nonce'] !== 'NONCE123') p.push('nonce header missing');
  return p;
});
await glue('glue: HTTP 500 → {ok:false, kind:http}', async (pg) => { await pg.route('**/validate', (r) => r.fulfill({ status: 500, body: 'x' })); },
  (res) => (res.validation?.ok === false && res.validation.kind === 'http' && res.validation.status === 500) ? [] : ['got ' + JSON.stringify(res.validation)]);
await glue('glue: malformed body → kind:malformed', async (pg) => { await pg.route('**/validate', (r) => r.fulfill({ status: 200, contentType: 'application/json', body: '{"nope":1}' })); },
  (res) => (res.validation?.ok === false && res.validation.kind === 'malformed') ? [] : ['got ' + JSON.stringify(res.validation)]);
await glue('glue: stalled server → kind:timeout (read-only, safe to retry)', async (pg) => { await pg.route('**/validate', () => { /* never fulfilled */ }); },
  (res) => (res.validation?.ok === false && res.validation.kind === 'timeout') ? [] : ['got ' + JSON.stringify(res.validation)]);
await glue('glue: network failure → kind:network', async (pg) => { await pg.route('**/validate', (r) => r.abort()); },
  (res) => (res.validation?.ok === false && res.validation.kind === 'network') ? [] : ['got ' + JSON.stringify(res.validation)]);

await browser.close();
const slow = times.sort((a, b) => b[1] - a[1]).slice(0, 4).map(([t, ms]) => `${t}: ${ms.toFixed(0)} ms`);
console.log(`\n${total - failed}/${total} passed. Slowest parses: ${slow.join(' | ')}`);
process.exit(failed ? 1 : 0);
