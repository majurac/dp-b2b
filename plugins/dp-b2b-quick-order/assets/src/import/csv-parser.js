'use strict';

import { ImportParseError, trimText, boundText, isValidHeader, MAX_QUANTITY_TEXT } from './common.js';

/**
 * CSV parsing without a dependency.
 *
 * - UTF-8 (BOM-safe); Windows-1250 ONLY as a deterministic fallback when the bytes are not valid UTF-8.
 * - Delimiter (`;` or `,`) is decided by the HEADER: the first candidate whose first record is exactly
 *   `<identifier alias>, Količina`. Real identifiers contain commas and spaces, so the body is never split
 *   naively — the tokenizer is a strict, quote-aware RFC 4180 state machine.
 * - A wrong column count is a rejected ROW (reported, never repaired). A structural quoting error
 *   (stray/unterminated quote) makes the whole file untrustworthy and fails it.
 * - Cell contents are inert text: nothing is evaluated, nothing is rendered as HTML.
 */

function decode(bytes) {
    if (bytes.includes(0)) {
        // NUL bytes: binary or UTF-16 content — not a CSV we can read safely.
        throw new ImportParseError('invalid_file');
    }
    let text;
    try {
        text = new TextDecoder('utf-8', { fatal: true }).decode(bytes); // strips a UTF-8 BOM
    } catch {
        try {
            text = new TextDecoder('windows-1250').decode(bytes);
        } catch {
            throw new ImportParseError('unsupported_encoding');
        }
    }
    return text.replace(/^﻿/, '');
}

/**
 * @param {string} text
 * @param {string} delim
 * @param {number} maxRecords  non-blank record cap (header + data); exceeded → too_many_rows
 * @returns {{line:number, fields:string[]}[]}
 */
function tokenize(text, delim, maxRecords) {
    const records = [];
    let nonBlank = 0;
    let fields = [];
    let field = '';
    let state = 'start'; // start | unquoted | quoted | afterQuote
    let line = 1;
    let recLine = 1;

    const endField = () => { fields.push(field); field = ''; };
    const endRecord = () => {
        endField();
        const blank = fields.length === 1 && trimText(fields[0]) === '';
        if (!blank) {
            if (++nonBlank > maxRecords) throw new ImportParseError('too_many_rows');
            records.push({ line: recLine, fields });
        }
        fields = [];
    };
    const newline = (i) => {
        if (text[i] === '\r' && text[i + 1] === '\n') i++;
        line++;
        endRecord();
        recLine = line;
        state = 'start';
        return i;
    };

    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        const isNl = c === '\n' || c === '\r';

        if (state === 'start') {
            if (c === '"') state = 'quoted';
            else if (c === delim) endField();
            else if (isNl) i = newline(i);
            else { field += c; state = 'unquoted'; }
        } else if (state === 'unquoted') {
            if (c === delim) { endField(); state = 'start'; }
            else if (isNl) i = newline(i);
            else if (c === '"') throw new ImportParseError('malformed_csv', { line });
            else field += c;
        } else if (state === 'quoted') {
            if (c === '"') {
                if (text[i + 1] === '"') { field += '"'; i++; } else state = 'afterQuote';
            } else {
                if (c === '\n' || (c === '\r' && text[i + 1] !== '\n')) line++;
                field += c;
            }
        } else { // afterQuote
            if (c === delim) { endField(); state = 'start'; }
            else if (isNl) i = newline(i);
            else throw new ImportParseError('malformed_csv', { line });
        }
    }

    if (state === 'quoted') throw new ImportParseError('malformed_csv', { line }); // unterminated quote
    if (state !== 'start' || fields.length > 0) endRecord();

    return records;
}

/**
 * @param {Uint8Array} bytes
 * @param {{maxRows:number, maxIdentifierLength:number}} limits
 */
export function parseCsv(bytes, limits) {
    const text = decode(bytes);

    let records = null;
    let lastError = null;
    let tokenized = false;
    for (const delim of [';', ',']) {
        let candidate;
        try {
            candidate = tokenize(text, delim, limits.maxRows + 1);
            tokenized = true;
        } catch (e) {
            if (e instanceof ImportParseError && e.code === 'too_many_rows') throw e;
            lastError = e;
            continue;
        }
        const head = candidate[0]?.fields;
        if (head && head.length === 2 && isValidHeader(head[0], head[1])) {
            records = candidate;
            break;
        }
    }

    if (!records) {
        if (!text.trim()) throw new ImportParseError('empty_file');
        // A quoting error only matters when no delimiter produced a readable table at all.
        throw tokenized ? new ImportParseError('invalid_header') : lastError;
    }

    const rows = [];
    const rejected = [];
    for (const record of records.slice(1)) {
        if (record.fields.length !== 2) {
            // Never silently repaired (e.g. an unquoted comma inside an identifier shifts the columns).
            rejected.push({ row: record.line, code: 'malformed_row' });
            continue;
        }
        rows.push({
            row: record.line,
            identifier: boundText(trimText(record.fields[0]), limits.maxIdentifierLength),
            quantity: boundText(trimText(record.fields[1]), MAX_QUANTITY_TEXT),
        });
    }

    if (!rows.length && !rejected.length) throw new ImportParseError('empty_file');

    return { format: 'csv', rows, rejected, warnings: [] };
}
