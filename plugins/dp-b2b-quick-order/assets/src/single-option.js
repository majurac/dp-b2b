'use strict';

/** Colour is product information and stays visible even when single-valued (ADR-026 addendum 2). */
const ALWAYS_VISIBLE = new Set(['pa_boja']);

/**
 * Keys of attributes that every variation of one product shares with exactly ONE value —
 * the Quick Order counterpart of the PDP rule (ADR-026: values actually used by variations,
 * never all configured terms). An attribute missing on some variation ("any" value) is
 * not single. Display-only: callers hide the text, the variation_id keeps the identity.
 *
 * @param {{attributes?: {key?: string, value: string}[]}[]} variations
 * @returns {Set<string>}
 */
export function singleValueAttributeKeys(variations) {
    const seen = new Map(); // key -> { values:Set, count:number }
    for (const v of variations) {
        for (const a of v.attributes ?? []) {
            if (!a.key) continue;
            const entry = seen.get(a.key) ?? { values: new Set(), count: 0 };
            entry.values.add(a.value);
            entry.count++;
            seen.set(a.key, entry);
        }
    }
    const hidden = new Set();
    for (const [key, { values, count }] of seen) {
        if (values.size === 1 && count === variations.length && !ALWAYS_VISIBLE.has(key)) hidden.add(key);
    }
    return hidden;
}
