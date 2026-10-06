'use strict';

import { parseFile } from './parse-file.js';
import { validateRows } from './validate-client.js';
import { ImportParseError } from './common.js';
import { ImportModal } from './modal.js';

/**
 * Excel/CSV import: parser + validation client (Gate 1) and the modal UI (Gate 2).
 *
 * Exposed as `window.dpQuickOrderImport` so tests can drive the parser/validator directly. The main Quick Order
 * bundle is untouched by this file; the modal never reads or mutates the visible QuickOrderState.
 */
(function () {
    const config = () => window.dpQuickOrder ?? {};

    const limitsFromConfig = () => {
        const c = config();
        const out = {};
        if (c.importMaxFileBytes) out.maxFileBytes = Number(c.importMaxFileBytes);
        if (c.importMaxRows) out.maxRows = Number(c.importMaxRows);
        if (c.importMaxIdentifierLength) out.maxIdentifierLength = Number(c.importMaxIdentifierLength);
        return out;
    };

    window.dpQuickOrderImport = Object.freeze({
        ImportParseError,
        parseFile: (file) => parseFile(file, limitsFromConfig()),
        validateRows: (rows, options) => validateRows(rows, config(), options),
        /** file → parse → server validation. */
        async importFile(file, options) {
            const parsed = await parseFile(file, limitsFromConfig());
            const validation = await validateRows(parsed.rows, config(), options);
            return { parsed, validation };
        },
    });

    // ── Modal wiring ───────────────────────────────────────────────────────────────────────────────────────
    const boot = () => {
        const c = config();
        const triggers = [...document.querySelectorAll('[data-dp-qo-import]')];
        if (!triggers.length || !c.importValidateUrl || !c.cartSyncUrl || !c.i18n?.import) return;

        let modal = null;
        const open = (trigger) => {
            modal ??= new ImportModal({
                config: c,
                parse: (file) => parseFile(file, limitsFromConfig()),
                validate: (rows, options) => validateRows(rows, c, options),
            });
            modal.open(trigger);
        };

        for (const btn of triggers) {
            btn.hidden = false; // revealed only once the bundle is alive
            btn.addEventListener('click', () => open(btn));
        }
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
})();
