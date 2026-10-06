'use strict';

/**
 * Pure helpers of the Excel import modal (no DOM). The Gate 1 server validator is the ONLY authority for
 * identity, authorization and quantity; nothing here recomputes stock or resolves identifiers.
 */

/** Croatian plural form: 1 / 2–4 / 5+ (11–14 are "5+"). */
export function plural(n, forms) {
    const mod10 = n % 10;
    const mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return forms[0];
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return forms[1];
    return forms[2];
}

/** "{name}" placeholder substitution with plain values (counts / short labels only). */
export function fill(template, values) {
    return String(template ?? '').replace(/\{(\w+)\}/g, (m, k) => (k in values ? String(values[k]) : m));
}

/** Row key shared with the rest of Quick Order and the /cart/sync contract. */
export const unitKey = (productId, variationId) => `${productId}_${variationId}`;

/**
 * Merge the parser output with the server validation result into display rows + actionable units.
 *
 * Summary semantics (explicit, to avoid misleading totals):
 *  - counts.ready / adjusted / error are RESULTING ORDERABLE UNITS / error rows (one per table row), not
 *    spreadsheet rows — duplicate spreadsheet rows were already merged per unit by the server;
 *  - counts.sourceRows is the number of spreadsheet rows read (valid + structurally rejected);
 *  - counts.merged is how many spreadsheet rows were folded into another row.
 *
 * @param {{rows: object[], rejected?: {row:number, code:string}[]}} parsed
 * @param {{rows: object[]}} validation  Gate 1 response body
 */
export function deriveResult(parsed, validation) {
    const display = [];

    for (const r of validation.rows) {
        const first = Array.isArray(r.rows) && r.rows.length ? Math.min(...r.rows) : 0;
        display.push({
            first,
            rows: Array.isArray(r.rows) ? r.rows : [],
            identifier: String(r.identifier ?? ''),
            name: typeof r.name === 'string' && r.name !== '' ? r.name : null,
            requested: Number.isInteger(r.requested) ? r.requested : null,
            quantity: Number.isInteger(r.quantity) ? r.quantity : 0,
            status: ['ready', 'adjusted', 'error'].includes(r.status) ? r.status : 'error',
            code: typeof r.code === 'string' ? r.code : null,
            productId: Number.isInteger(r.product_id) ? r.product_id : null,
            variationId: Number.isInteger(r.variation_id) ? r.variation_id : null,
        });
    }

    // Structurally malformed CSV rows never reach the server; they are reported, never repaired.
    for (const rej of parsed.rejected ?? []) {
        display.push({ first: rej.row, rows: [rej.row], identifier: '', name: null, requested: null, quantity: 0, status: 'error', code: rej.code, productId: null, variationId: null });
    }

    display.sort((a, b) => a.first - b.first);

    const units = [];
    const counts = { ready: 0, adjusted: 0, error: 0, merged: 0, sourceRows: parsed.rows.length + (parsed.rejected?.length ?? 0) };
    for (const d of display) {
        counts[d.status]++;
        if (d.rows.length > 1) counts.merged += d.rows.length - 1;
        // Only an authorized, resolved unit with a positive quantity is ever sent to the cart step.
        if ((d.status === 'ready' || d.status === 'adjusted') && d.productId !== null && d.variationId !== null && d.quantity > 0) {
            units.push({ key: unitKey(d.productId, d.variationId), productId: d.productId, variationId: d.variationId, quantity: d.quantity, name: d.name, identifier: d.identifier });
        }
    }

    return { display, units, counts, warnings: parsed.warnings ?? [] };
}

/**
 * Classify the final cart outcome of an import (deterministic, keyed by unit — never by array position).
 * @returns {{kind:'success'|'partial'|'none'|'ambiguous', added:object[], failed:{unit:object,error:string}[], unknown:object[], unsent:object[]}}
 */
export function classifyCartOutcome(units, outcome) {
    const byKey = new Map(units.map((u) => [u.key, u]));
    const pick = (keys) => keys.map((k) => byKey.get(k)).filter(Boolean);
    const added = pick(outcome.addedKeys);
    const failed = outcome.failed.map((f) => ({ unit: byKey.get(f.key), error: f.error })).filter((f) => f.unit);
    const unknown = pick(outcome.ambiguousKeys);
    const unsent = pick(outcome.unsentKeys ?? []);

    let kind;
    if (unknown.length > 0 || unsent.length > 0) kind = 'ambiguous';
    else if (failed.length === 0) kind = 'success';
    else kind = added.length > 0 ? 'partial' : 'none';

    return { kind, added, failed, unknown, unsent };
}
