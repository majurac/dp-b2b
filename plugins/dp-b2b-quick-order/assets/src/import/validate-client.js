'use strict';

/**
 * POST the parsed rows to the read-only validation endpoint.
 *
 * Validation changes nothing on the server, so — unlike the cart submit — a timeout or network failure is
 * not "ambiguous" and may simply be retried by the caller.
 *
 * @param {{row:number, identifier:string, quantity:string}[]} rows
 * @param {object} config  window.dpQuickOrder
 * @param {{signal?:AbortSignal}} [options]
 * @returns {Promise<{ok:true, data:object} | {ok:false, kind:'network'|'timeout'|'http'|'malformed'|'aborted', status?:number}>}
 */
export async function validateRows(rows, config, options = {}) {
    const controller = new AbortController();
    const timeoutMs  = Number(config.importTimeoutMs) > 0 ? Number(config.importTimeoutMs) : 30000;
    let timedOut = false;
    const timer = setTimeout(() => { timedOut = true; controller.abort(); }, timeoutMs);
    options.signal?.addEventListener('abort', () => controller.abort(), { once: true });

    try {
        const res = await fetch(config.importValidateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.wpNonce,
            },
            body: JSON.stringify({ rows }),
            signal: controller.signal,
            cache: 'no-store',
        });
        if (!res.ok) return { ok: false, kind: 'http', status: res.status };
        const data = await res.json();
        if (!data || !Array.isArray(data.rows) || typeof data.summary !== 'object') {
            return { ok: false, kind: 'malformed' };
        }
        return { ok: true, data };
    } catch {
        if (timedOut) return { ok: false, kind: 'timeout' };
        return { ok: false, kind: options.signal?.aborted ? 'aborted' : 'network' };
    } finally {
        clearTimeout(timer);
    }
}
