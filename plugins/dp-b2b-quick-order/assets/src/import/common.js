'use strict';

/**
 * Shared constants/helpers for the Excel/CSV import parsers.
 *
 * The parsers produce MINIMAL UNTRUSTED data — `{row, identifier, quantity}` as plain text — and nothing
 * else. They never infer product IDs or any catalog metadata; the server owns identity (see
 * inc/class-import-validator.php). Parsed text is only ever rendered with textContent, never as HTML.
 */

export const DEFAULT_LIMITS = Object.freeze({
    maxFileBytes: 2 * 1024 * 1024,
    maxRows: 500,
    maxIdentifierLength: 64,
});

/** Quantity cells are kept as raw text for the server's strict check; this only bounds hostile cells. */
export const MAX_QUANTITY_TEXT = 40;

export class ImportParseError extends Error {
    /**
     * @param {string} code  stable machine-readable code (never parsed from the message)
     * @param {object} [detail]
     */
    constructor(code, detail = {}) {
        super(code);
        this.name = 'ImportParseError';
        this.code = code;
        this.detail = detail;
    }
}

/** Trim surrounding whitespace incl. NBSP and a stray BOM. Nothing else (no case/zero/numeric changes). */
export function trimText(value) {
    return String(value ?? '').replace(/^[\s ﻿]+|[\s ﻿]+$/g, '');
}

/** Bound hostile cell sizes without silently shortening a legitimate value: cut at limit + 1. */
export function boundText(value, limit) {
    return value.length > limit ? value.slice(0, limit + 1) : value;
}

const ID_HEADERS  = new Set(['sku', 'kataloski broj', 'sifra artikla']);
const QTY_HEADERS = new Set(['kolicina']);

function normHeader(value) {
    return trimText(value)
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/đ/gi, 'd')
        .replace(/\s+/g, ' ')
        .toLowerCase();
}

/**
 * Accepted headers: identifier = SKU | Kataloški broj | Šifra artikla ; quantity = Količina.
 * (EAN is deliberately NOT an alias.)
 */
export function isValidHeader(idCell, qtyCell) {
    return ID_HEADERS.has(normHeader(idCell)) && QTY_HEADERS.has(normHeader(qtyCell));
}
