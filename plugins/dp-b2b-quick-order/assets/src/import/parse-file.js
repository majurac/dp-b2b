'use strict';

import { DEFAULT_LIMITS, ImportParseError } from './common.js';
import { parseCsv } from './csv-parser.js';
import { parseXlsx } from './xlsx-parser.js';

/**
 * Parse an uploaded spreadsheet LOCALLY into minimal untrusted rows.
 *
 * Accepts `.xlsx` and `.csv` only (no legacy `.xls`, no macro-enabled `.xlsm`). The file itself is never
 * uploaded or persisted — only the parsed `{row, identifier, quantity}` triples ever leave the browser.
 *
 * @param {File|Blob} file
 * @param {{maxFileBytes?:number, maxRows?:number, maxIdentifierLength?:number}} [overrides]
 * @returns {Promise<{format:'xlsx'|'csv', rows:{row:number,identifier:string,quantity:string}[], rejected:{row:number,code:string}[], warnings:object[]}>}
 * @throws {ImportParseError}
 */
export async function parseFile(file, overrides = {}) {
    const limits = { ...DEFAULT_LIMITS, ...overrides };
    const name = String(file?.name ?? '').toLowerCase();

    let kind;
    if (name.endsWith('.xlsx')) kind = 'xlsx';
    else if (name.endsWith('.csv')) kind = 'csv';
    else throw new ImportParseError('unsupported_format');

    if (!file.size) throw new ImportParseError('empty_file');
    if (file.size > limits.maxFileBytes) throw new ImportParseError('file_too_large');

    const bytes = new Uint8Array(await file.arrayBuffer());
    if (bytes.length > limits.maxFileBytes) throw new ImportParseError('file_too_large');

    // A ZIP container is never a CSV, whatever the extension claims.
    const isZip = bytes.length >= 4 && bytes[0] === 0x50 && bytes[1] === 0x4B && bytes[2] === 3 && bytes[3] === 4;
    if (kind === 'csv' && isZip) throw new ImportParseError('invalid_file');

    return kind === 'xlsx' ? parseXlsx(bytes, limits) : parseCsv(bytes, limits);
}
