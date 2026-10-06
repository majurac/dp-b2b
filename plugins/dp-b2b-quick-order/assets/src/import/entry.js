'use strict';

import { parseFile } from './parse-file.js';
import { validateRows } from './validate-client.js';
import { ImportParseError } from './common.js';

/**
 * Excel/CSV import foundation (Gate 1): parser + validation client. No UI.
 *
 * Exposed as `window.dpQuickOrderImport` so the Gate 2 modal (and tests) can drive it. The main Quick
 * Order bundle is untouched; nothing here reads or mutates the visible QuickOrderState.
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
})();
