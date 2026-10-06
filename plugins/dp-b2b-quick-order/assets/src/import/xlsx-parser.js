'use strict';

import { unzipSync, strFromU8 } from 'fflate';
import { ImportParseError, trimText, boundText, isValidHeader, MAX_QUANTITY_TEXT } from './common.js';

/**
 * Minimal .xlsx reader — NOT a general Excel engine. It understands exactly what the import workflow
 * needs: the first VISIBLE worksheet, columns A (identifier) and B (quantity), shared/inline/plain cell
 * values. Everything else in the workbook is ignored (never followed, never evaluated).
 *
 * Safety model (fflate 0.8.3 `unzipSync` + browser DOMParser):
 *  - The ZIP central directory is read first with a filter that rejects every entry, so entry count,
 *    total DECLARED uncompressed size, names (VBA/macro/embedded parts) and duplicate names are all
 *    checked BEFORE a single byte is inflated. fflate then inflates into a buffer of exactly the declared
 *    size, so a ZIP whose real data is larger than declared is truncated (→ malformed XML → rejected),
 *    never expanded.
 *  - Per-part uncompressed caps for the three XML parts that are actually read.
 *  - XML containing a DOCTYPE/ENTITY declaration is rejected (no entity-expansion games).
 *  - Formulas are never evaluated: a cached scalar `<v>` is used as inert text; a formula with no cached
 *    value yields an empty cell (the server rejects the row) plus a warning.
 *  - External links, hidden sheets, further sheets and every non-A/B column are ignored.
 */

const LIMITS = Object.freeze({
    maxEntries: 100,
    maxTotalUncompressed: 30 * 1024 * 1024,
    maxSheetXml: 8 * 1024 * 1024,
    maxSharedStringsXml: 8 * 1024 * 1024,
    maxSmallXml: 1024 * 1024,
});

const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

const FORBIDDEN_PART = /(^|\/)vbaProject\.bin$|^xl\/(macrosheets|dialogsheets|embeddings|activeX)\//i;

const err = (code, detail) => new ImportParseError(code, detail);

/** Read the central directory only: nothing is inflated (the filter always answers "skip"). */
function listEntries(bytes) {
    const entries = [];
    const names = new Set();
    let total = 0;
    try {
        unzipSync(bytes, {
            filter(file) {
                if (entries.length >= LIMITS.maxEntries) throw err('xlsx_too_many_entries');
                if (FORBIDDEN_PART.test(file.name)) throw err('xlsx_macro_content');
                if (names.has(file.name)) throw err('invalid_file'); // ambiguous duplicate part
                names.add(file.name);
                total += file.originalSize;
                if (!Number.isFinite(total) || total > LIMITS.maxTotalUncompressed) throw err('xlsx_too_large_uncompressed');
                entries.push({ name: file.name, originalSize: file.originalSize });
                return false;
            },
        });
    } catch (e) {
        throw e instanceof ImportParseError ? e : err('invalid_file');
    }
    return entries;
}

/** Inflate only the named parts, each bounded by its own cap (checked against the declared size first). */
function readParts(bytes, wanted) {
    let out;
    try {
        out = unzipSync(bytes, {
            filter(file) {
                const cap = wanted.get(file.name);
                if (cap === undefined) return false;
                if (file.originalSize > cap) throw err('xlsx_too_large_uncompressed', { part: file.name });
                return true;
            },
        });
    } catch (e) {
        throw e instanceof ImportParseError ? e : err('invalid_file');
    }
    return out;
}

function parseXml(u8) {
    const text = strFromU8(u8);
    if (/<!DOCTYPE|<!ENTITY/i.test(text)) throw err('invalid_file');
    const doc = new DOMParser().parseFromString(text, 'application/xml');
    if (doc.getElementsByTagName('parsererror').length) throw err('invalid_file');
    return doc;
}

const kids = (el, local) => Array.from(el.children).filter(n => n.localName === local);
const firstKid = (el, local) => Array.from(el.children).find(n => n.localName === local) ?? null;

/** Visible text of a <si>/<is> node: all <t> (also inside rich-text <r>), never phonetic runs. */
function richText(node) {
    let out = '';
    for (const child of node.children) {
        if (child.localName === 't') out += child.textContent;
        else if (child.localName === 'r') out += kids(child, 't').map(t => t.textContent).join('');
    }
    return out;
}

function resolveSheetPath(target) {
    const parts = [];
    const segments = (target.startsWith('/') ? target.slice(1) : 'xl/' + target).split('/');
    for (const seg of segments) {
        if (seg === '' || seg === '.') continue;
        if (seg === '..') parts.pop(); else parts.push(seg);
    }
    const path = parts.join('/');
    if (!/^xl\/worksheets\/[^/]+\.xml$/i.test(path)) throw err('invalid_file');
    return path;
}

function columnIndex(ref) {
    const letters = /^([A-Z]+)/i.exec(ref ?? '');
    if (!letters) return -1;
    let n = 0;
    for (const ch of letters[1].toUpperCase()) n = n * 26 + (ch.charCodeAt(0) - 64);
    return n - 1;
}

/**
 * @param {Uint8Array} bytes
 * @param {{maxRows:number, maxIdentifierLength:number}} limits
 */
export function parseXlsx(bytes, limits) {
    if (bytes.length < 4 || bytes[0] !== 0x50 || bytes[1] !== 0x4B || bytes[2] !== 3 || bytes[3] !== 4) {
        throw err('invalid_file'); // not a ZIP container
    }

    const warnings = [];
    const entries = listEntries(bytes);
    const present = new Set(entries.map(e => e.name));

    // Phase A: workbook structure (small parts only).
    const need = new Map([
        ['[Content_Types].xml', LIMITS.maxSmallXml],
        ['xl/workbook.xml', LIMITS.maxSmallXml],
        ['xl/_rels/workbook.xml.rels', LIMITS.maxSmallXml],
    ]);
    if (!present.has('xl/workbook.xml') || !present.has('xl/_rels/workbook.xml.rels')) throw err('invalid_file');

    const a = readParts(bytes, need);
    if (a['[Content_Types].xml'] && /macroEnabled|vbaProject/i.test(strFromU8(a['[Content_Types].xml']))) {
        throw err('xlsx_macro_content');
    }
    if (!a['xl/workbook.xml'] || !a['xl/_rels/workbook.xml.rels']) throw err('invalid_file');

    const workbook = parseXml(a['xl/workbook.xml']);
    const rels     = parseXml(a['xl/_rels/workbook.xml.rels']);

    const sheetsEl = workbook.getElementsByTagNameNS('*', 'sheets')[0];
    if (!sheetsEl) throw err('invalid_file');
    const visible = kids(sheetsEl, 'sheet').filter(s => {
        const state = s.getAttribute('state');
        return !state || state === 'visible';
    });
    if (!visible.length) throw err('xlsx_no_visible_sheet');
    if (visible.length > 1) warnings.push({ code: 'multiple_sheets', sheet: visible[0].getAttribute('name') ?? '' });

    const relId = visible[0].getAttributeNS(NS_R, 'id') ?? visible[0].getAttribute('r:id');
    const relMap = new Map();
    for (const rel of rels.getElementsByTagNameNS('*', 'Relationship')) {
        relMap.set(rel.getAttribute('Id'), {
            target: rel.getAttribute('Target') ?? '',
            type: rel.getAttribute('Type') ?? '',
            external: (rel.getAttribute('TargetMode') ?? '').toLowerCase() === 'external',
        });
    }
    const sheetRel = relMap.get(relId);
    if (!sheetRel || sheetRel.external || !sheetRel.target) throw err('invalid_file');

    const sheetPath = resolveSheetPath(sheetRel.target);
    if (!present.has(sheetPath)) throw err('invalid_file');

    const sstRel  = [...relMap.values()].find(r => /\/sharedStrings$/i.test(r.type) && !r.external);
    const sstPath = sstRel ? sstRel.target.replace(/^\/?(xl\/)?/, 'xl/') : 'xl/sharedStrings.xml';
    if ([...present].some(n => /^xl\/externalLinks\//i.test(n))) warnings.push({ code: 'external_links_ignored' });

    // Phase B: the worksheet and (if any) shared strings.
    const need2 = new Map([[sheetPath, LIMITS.maxSheetXml]]);
    if (present.has(sstPath)) need2.set(sstPath, LIMITS.maxSharedStringsXml);
    const b = readParts(bytes, need2);
    if (!b[sheetPath]) throw err('invalid_file');

    const shared = [];
    if (b[sstPath]) {
        const sstDoc = parseXml(b[sstPath]);
        for (const si of sstDoc.getElementsByTagNameNS('*', 'si')) shared.push(si); // resolved lazily
    }
    const sharedCache = new Map();
    const sharedText = (idx) => {
        if (!Number.isInteger(idx) || idx < 0 || idx >= shared.length) return '';
        if (!sharedCache.has(idx)) sharedCache.set(idx, richText(shared[idx]));
        return sharedCache.get(idx);
    };

    const sheet = parseXml(b[sheetPath]);
    const sheetData = sheet.getElementsByTagNameNS('*', 'sheetData')[0];
    if (!sheetData) throw err('empty_file');

    let formulaNoValue = 0;
    let formulaCached  = 0;
    let hiddenRows     = 0;
    let extraColumns   = false;

    /** Text value of a cell as inert text. Never evaluates anything. */
    const cellText = (c) => {
        const type = c.getAttribute('t');
        const f = firstKid(c, 'f');
        const v = firstKid(c, 'v');
        if (f && type !== 'inlineStr') {
            if (!v || v.textContent === '') { formulaNoValue++; return ''; }
            formulaCached++;
        }
        if (type === 'inlineStr') {
            const is = firstKid(c, 'is');
            return is ? richText(is) : '';
        }
        if (!v) return '';
        const raw = v.textContent;
        if (type === 's') return sharedText(Number.parseInt(raw, 10));
        if (type === 'b') return raw === '1' ? 'TRUE' : 'FALSE'; // never leak "1" as a quantity
        return raw; // 'str', 'e', 'd' and numeric cells: the stored text, unmodified
    };

    const rows = [];
    let header = null;
    let prevRowNo = 0;

    for (const rowEl of kids(sheetData, 'row')) {
        const rowNo = Number.parseInt(rowEl.getAttribute('r') ?? '', 10) || prevRowNo + 1;
        prevRowNo = rowNo;

        let idText = '';
        let qtyText = '';
        let pos = -1;
        let rowExtra = false;
        for (const c of kids(rowEl, 'c')) {
            const ref = c.getAttribute('r');
            const col = ref ? columnIndex(ref) : pos + 1;
            pos = col;
            if (col === 0) idText = cellText(c);
            else if (col === 1) qtyText = cellText(c);
            else if (col > 1 && !rowExtra && trimText(cellText(c)) !== '') rowExtra = true;
        }
        idText = trimText(idText);
        qtyText = trimText(qtyText);

        if (idText === '' && qtyText === '') continue; // empty/styled-only row

        if (!header) {
            if (!isValidHeader(idText, qtyText)) throw err('invalid_header', { row: rowNo });
            header = { row: rowNo };
            continue;
        }

        if (rowExtra) extraColumns = true; // notes/instructions beside the header or empty rows are not data
        if (rowEl.getAttribute('hidden') === '1') hiddenRows++;
        if (rows.length >= limits.maxRows) throw err('too_many_rows');
        rows.push({
            row: rowNo,
            identifier: boundText(idText, limits.maxIdentifierLength),
            quantity: boundText(qtyText, MAX_QUANTITY_TEXT),
        });
    }

    if (!header) throw err('empty_file');
    if (!rows.length) throw err('empty_file');
    if (formulaNoValue) warnings.push({ code: 'formula_without_value', count: formulaNoValue });
    if (formulaCached) warnings.push({ code: 'formula_cached_values_used', count: formulaCached });
    if (hiddenRows) warnings.push({ code: 'hidden_rows_included', count: hiddenRows });
    if (extraColumns) warnings.push({ code: 'extra_columns_ignored' });

    return { format: 'xlsx', rows, rejected: [], warnings };
}
