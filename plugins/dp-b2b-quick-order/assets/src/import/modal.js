'use strict';

import { QuickOrderState } from '../quick-order-state.js';
import { CartSubmit } from '../cart-submit.js';
import { ImportParseError } from './common.js';
import { deriveResult, classifyCartOutcome, plural, fill } from './session.js';

/**
 * Excel/CSV import modal: File upload → Validacija → Rezultat → Dodavanje u košaricu.
 *
 * Architecture:
 *  - Fully PRIVATE state: nothing is written to the visible QuickOrderState, localStorage, sessionStorage or
 *    the DB. Closing resets everything; opening again starts a fresh session.
 *  - Parsing/limits are the Gate 1 parser's job (injected); identity, authorization and the stock clamp are the
 *    Gate 1 server validator's job (injected). This module only presents them.
 *  - The cart step reuses CartSubmit (the existing /cart/sync client: additive, 50-item chunks, typed failures,
 *    ambiguous-outcome handling) through a PRIVATE QuickOrderState. Progress = real chunk completion only.
 *  - Every string is rendered with textContent (never HTML); copy comes from config.i18n.import (PHP).
 */

const SVG_NS = 'http://www.w3.org/2000/svg';

const ICONS = {
    check: [['circle', { cx: 12, cy: 12, r: 9 }], ['path', { d: 'm8 12.5 2.8 2.8L16 9.5' }]],
    close: [['path', { d: 'M6 6l12 12M18 6 6 18' }]],
    download: [['path', { d: 'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z' }], ['path', { d: 'M14 3v5h5' }], ['path', { d: 'M12 11v6' }], ['path', { d: 'm9.5 14.5 2.5 2.5 2.5-2.5' }]],
    plus: [['path', { d: 'M12 5v14M5 12h14' }]],
    info: [['circle', { cx: 12, cy: 12, r: 9 }], ['path', { d: 'M12 8v5' }], ['path', { d: 'M12 16.5v.01' }]],
    basket: [['path', { d: 'M3 9h18l-1.7 9.2a2 2 0 0 1-2 1.8H6.7a2 2 0 0 1-2-1.8z' }], ['path', { d: 'M8 9l3-5M16 9l-3-5' }], ['path', { d: 'm9.5 14.2 2 2 3.5-3.7' }]],
    warning: [['path', { d: 'M12 4 3 20h18z' }], ['path', { d: 'M12 10v5' }], ['path', { d: 'M12 17.5v.01' }]],
};

function svg(name, size = 20, cls = '') {
    const el = document.createElementNS(SVG_NS, 'svg');
    el.setAttribute('viewBox', '0 0 24 24');
    el.setAttribute('width', String(size));
    el.setAttribute('height', String(size));
    el.setAttribute('fill', 'none');
    el.setAttribute('stroke', 'currentColor');
    el.setAttribute('stroke-width', '1.6');
    el.setAttribute('stroke-linecap', 'round');
    el.setAttribute('stroke-linejoin', 'round');
    el.setAttribute('aria-hidden', 'true');
    el.setAttribute('focusable', 'false');
    if (cls) el.setAttribute('class', cls);
    for (const [tag, attrs] of ICONS[name]) {
        const n = document.createElementNS(SVG_NS, tag);
        for (const [k, v] of Object.entries(attrs)) n.setAttribute(k, String(v));
        el.appendChild(n);
    }
    return el;
}

/** The loader artwork of the design: a dial with a hand, surrounded by six dots. */
function spinner() {
    const el = document.createElementNS(SVG_NS, 'svg');
    el.setAttribute('class', 'dp-qo-import__spinner');
    el.setAttribute('viewBox', '0 0 140 140');
    el.setAttribute('width', '140');
    el.setAttribute('height', '140');
    el.setAttribute('aria-hidden', 'true');
    el.setAttribute('focusable', 'false');
    const g = document.createElementNS(SVG_NS, 'g');
    g.setAttribute('class', 'dp-qo-import__spinner-dots');
    for (let i = 0; i < 6; i++) {
        const a = (i * Math.PI * 2) / 6 - Math.PI / 2;
        const c = document.createElementNS(SVG_NS, 'circle');
        c.setAttribute('cx', String(70 + Math.cos(a) * 58));
        c.setAttribute('cy', String(70 + Math.sin(a) * 58));
        c.setAttribute('r', '4');
        c.setAttribute('fill', 'currentColor');
        g.appendChild(c);
    }
    el.appendChild(g);
    const ring = document.createElementNS(SVG_NS, 'circle');
    for (const [k, v] of Object.entries({ cx: 70, cy: 70, r: 36, fill: 'none', stroke: 'currentColor', 'stroke-width': 4 })) ring.setAttribute(k, String(v));
    el.appendChild(ring);
    const hand = document.createElementNS(SVG_NS, 'path');
    for (const [k, v] of Object.entries({ d: 'M60 80l12-12', stroke: 'currentColor', 'stroke-width': 4, 'stroke-linecap': 'round', fill: 'none' })) hand.setAttribute(k, String(v));
    el.appendChild(hand);
    return el;
}

/** Tiny element builder. Text is always a text node; never innerHTML. */
function h(tag, props = {}, ...kids) {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(props)) {
        if (v === undefined || v === null || v === false) continue;
        if (k === 'class') el.className = v;
        else if (k === 'text') el.textContent = v;
        else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
        else el.setAttribute(k, v === true ? '' : String(v));
    }
    for (const kid of kids.flat()) {
        if (kid === null || kid === undefined || kid === false) continue;
        el.appendChild(typeof kid === 'string' || typeof kid === 'number' ? document.createTextNode(String(kid)) : kid);
    }
    return el;
}

const FOCUSABLE = 'a[href]:not([tabindex="-1"]), button:not([disabled]):not([tabindex="-1"]), input:not([disabled]):not([type="hidden"]):not([tabindex="-1"]), [tabindex]:not([tabindex="-1"])';

let titleSeq = 0;

export class ImportModal {
    #config;
    #t;
    #parse;
    #validate;
    #root = null;
    #dialog = null;
    #titleEl = null;
    #live = null;
    #stepsHost = null;
    #bodyHost = null;
    #footHost = null;
    #closeBtn = null;
    #trigger = null;
    #inerted = [];
    #phase = 'upload';
    #abort = null;
    #parsed = null;
    #result = null;
    #outcome = null;
    #uploadError = '';
    #prevHtmlOverflow = '';
    #onKeydown = (e) => this.#keydown(e);
    #swallowDrop = (e) => { e.preventDefault(); };

    /**
     * @param {{config:object, parse:(file:File)=>Promise<object>, validate:(rows:object[], o:{signal:AbortSignal})=>Promise<object>}} deps
     */
    constructor({ config, parse, validate }) {
        this.#config = config;
        this.#t = config.i18n?.import ?? {};
        this.#parse = parse;
        this.#validate = validate;
    }

    get isOpen() { return this.#root !== null; }

    open(trigger) {
        if (this.#root) return;
        this.#trigger = trigger ?? null;
        this.#phase = 'upload';
        this.#parsed = this.#result = this.#outcome = null;
        this.#uploadError = '';
        this.#build();
        this.#render();
    }

    // ── frame ────────────────────────────────────────────────────────────────────────────────────────────────

    #build() {
        const t = this.#t;
        const titleId = `dp-qo-import-title-${++titleSeq}`;
        this.#titleEl = h('h2', { class: 'dp-qo-import__title', id: titleId, tabindex: '-1', text: t.title });
        this.#closeBtn = h('button', { type: 'button', class: 'dp-qo-import__close', 'aria-label': t.closeLabel, onclick: () => this.#requestClose() }, svg('close', 20));
        this.#live = h('div', { class: 'dp-qo-visually-hidden', role: 'status', 'aria-live': 'polite', 'aria-atomic': 'true' });
        this.#stepsHost = h('div', { class: 'dp-qo-import__steps-host' });
        this.#bodyHost = h('div', { class: 'dp-qo-import__body' });
        this.#footHost = h('div', { class: 'dp-qo-import__foot' });

        this.#dialog = h('div', { class: 'dp-qo-import__dialog', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId },
            h('div', { class: 'dp-qo-import__head' }, this.#titleEl, this.#closeBtn),
            this.#stepsHost, this.#bodyHost, this.#footHost, this.#live);
        this.#root = h('div', { class: 'dp-qo-import', 'data-dp-qo-import-root': '' }, this.#dialog);
        // Clicking the dim backdrop is deliberately NOT a close action: a stray click must never cancel an import.
        // Keyboard handling lives on the document (capture) while the modal is open: when focus sits on <body>
        // (a click on non-focusable modal text or on the dim backdrop) key events would otherwise never reach the
        // modal, so Escape would stop working and Tab could leave the trap.
        document.addEventListener('keydown', this.#onKeydown, true);
        // A click anywhere inside the modal keeps focus inside it (dialog + backdrop are focusable containers).
        this.#dialog.setAttribute('tabindex', '-1');
        this.#root.setAttribute('tabindex', '-1');
        // A file dropped outside the drop zone must not make the browser navigate away from Quick Order.
        this.#root.addEventListener('dragover', this.#swallowDrop);
        this.#root.addEventListener('drop', this.#swallowDrop);

        document.body.appendChild(this.#root);
        this.#inerted = [];
        for (const el of document.body.children) {
            if (el === this.#root) continue;
            this.#inerted.push([el, el.hasAttribute('inert')]);
            el.setAttribute('inert', '');
        }
        this.#prevHtmlOverflow = document.documentElement.style.overflow;
        document.documentElement.style.overflow = 'hidden';
    }

    #teardown() {
        document.removeEventListener('keydown', this.#onKeydown, true);
        this.#abort?.abort();
        this.#abort = null;
        for (const [el, was] of this.#inerted) if (!was) el.removeAttribute('inert');
        this.#inerted = [];
        document.documentElement.style.overflow = this.#prevHtmlOverflow;
        this.#root?.remove();
        this.#root = this.#dialog = this.#titleEl = this.#live = this.#stepsHost = this.#bodyHost = this.#footHost = this.#closeBtn = null;
        this.#parsed = this.#result = this.#outcome = null; // private session state is discarded
        const trigger = this.#trigger;
        this.#trigger = null;
        trigger?.focus?.();
    }

    #unsafeToClose() { return this.#phase === 'adding'; }

    #requestClose() {
        // While the cart request is in flight a request may already be applied server-side: never offer a
        // "cancel" that would imply otherwise.
        if (this.#unsafeToClose()) return;
        this.#teardown();
    }

    #keydown(e) {
        if (!this.#root || e.defaultPrevented) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            this.#requestClose();
            return;
        }
        if (e.key !== 'Tab') return;
        const nodes = [...this.#dialog.querySelectorAll(FOCUSABLE)].filter((n) => n.offsetParent !== null || n === document.activeElement);
        if (!nodes.length) { e.preventDefault(); this.#titleEl.focus(); return; }
        const first = nodes[0];
        const last = nodes[nodes.length - 1];
        const active = document.activeElement;
        if (e.shiftKey && (active === first || active === this.#titleEl || !this.#dialog.contains(active))) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && (active === last || !this.#dialog.contains(active))) { e.preventDefault(); first.focus(); }
    }

    #announce(text) {
        if (this.#live) this.#live.textContent = text;
    }

    // ── phases ───────────────────────────────────────────────────────────────────────────────────────────────

    #setPhase(phase) {
        this.#phase = phase;
        this.#render();
    }

    #stepIndex() {
        switch (this.#phase) {
            case 'upload': return 0;
            case 'validating': case 'validate_error': return 1;
            case 'result': return 2;
            default: return 3;
        }
    }

    #render() {
        if (!this.#root) return;
        const t = this.#t;
        this.#stepsHost.replaceChildren();
        this.#bodyHost.replaceChildren();
        this.#footHost.replaceChildren();
        this.#dialog.classList.toggle('dp-qo-import__dialog--tall', this.#phase !== 'upload');
        this.#closeBtn.hidden = this.#unsafeToClose();
        this.#titleEl.textContent = t.title;

        if (this.#phase !== 'upload') this.#stepsHost.appendChild(this.#steps());

        switch (this.#phase) {
            case 'upload': this.#renderUpload(); break;
            case 'validating': this.#renderValidating(); break;
            case 'validate_error': this.#renderValidateError(); break;
            case 'result': this.#renderResult(); break;
            case 'adding': this.#renderAdding(); break;
            case 'finished': this.#renderFinished(); break;
            default: break;
        }
        // Move focus to the new view's heading so keyboard/screen-reader users land on the new context.
        this.#titleEl.focus();
    }

    #steps() {
        const t = this.#t;
        const current = this.#stepIndex();
        const finished = this.#phase === 'finished';
        const ol = h('ol', { class: 'dp-qo-import__steps', 'aria-label': t.title });
        (t.steps ?? []).forEach((label, i) => {
            const done = i < current || (finished && i === 3);
            const state = done ? 'done' : i === current ? 'current' : 'todo';
            ol.appendChild(h('li', { class: `dp-qo-import__step dp-qo-import__step--${state}`, 'aria-current': state === 'current' ? 'step' : null },
                h('span', { class: 'dp-qo-import__step-icon' }, svg('check', 28)),
                h('span', { class: 'dp-qo-import__step-label' }, label, h('span', { class: 'dp-qo-visually-hidden', text: ` — ${t.stepState?.[state] ?? state}` }))));
        });
        return ol;
    }

    // ── 1. upload ───────────────────────────────────────────────────────────────────────────────────────────

    #renderUpload() {
        const t = this.#t;
        const cfg = this.#config;
        const input = h('input', { type: 'file', class: 'dp-qo-import__file', id: 'dp-qo-import-file', accept: '.xlsx,.csv', 'aria-label': t.fileInputLabel, tabindex: '-1' });
        input.addEventListener('change', () => { const f = input.files?.[0]; input.value = ''; if (f) this.#handleFile(f); });
        const pick = h('button', { type: 'button', class: 'dp-qo-import__btn dp-qo-import__btn--ghost dp-qo-import__add-file', onclick: () => input.click() }, svg('plus', 16), h('span', { text: t.addFile }));
        const zone = h('div', { class: 'dp-qo-import__drop', 'aria-describedby': 'dp-qo-import-drop-hint' },
            h('p', { class: 'dp-qo-import__drop-text', id: 'dp-qo-import-drop-hint' }, t.dropText, h('br'), t.dropOr),
            pick, input);
        for (const ev of ['dragenter', 'dragover']) zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.add('is-over'); });
        zone.addEventListener('dragleave', (e) => { if (!zone.contains(e.relatedTarget)) zone.classList.remove('is-over'); });
        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('is-over');
            const f = e.dataTransfer?.files?.[0];
            if (f) this.#handleFile(f);
        });

        const dl = cfg.importTemplates ?? {};
        const body = h('div', { class: 'dp-qo-import__upload' },
            h('p', { class: 'dp-qo-import__intro', text: t.intro }),
            h('ul', { class: 'dp-qo-import__required' }, (t.requiredColumns ?? []).map((c) => h('li', {}, svg('check', 16), h('span', { text: c })))),
            this.#uploadError ? h('p', { class: 'dp-qo-import__error', text: this.#uploadError }) : null,
            h('section', { class: 'dp-qo-import__section' },
                h('div', { class: 'dp-qo-import__section-text' },
                    h('h3', { class: 'dp-qo-import__h3', text: t.step1Title }),
                    h('p', { class: 'dp-qo-import__muted', text: t.step1Text })),
                h('div', { class: 'dp-qo-import__section-action' },
                    h('a', { class: 'dp-qo-import__btn dp-qo-import__btn--primary', href: dl.xlsx ?? '#', download: '' }, svg('download', 18), h('span', { text: t.download })),
                    h('a', { class: 'dp-qo-import__link', href: dl.csv ?? '#', download: '', text: t.downloadCsv }))),
            h('section', { class: 'dp-qo-import__section dp-qo-import__section--stack' },
                h('h3', { class: 'dp-qo-import__h3', text: t.step2Title }),
                h('p', { class: 'dp-qo-import__muted', text: t.step2Text }),
                zone),
            h('div', { class: 'dp-qo-import__example' },
                svg('info', 22),
                h('div', {},
                    h('p', { class: 'dp-qo-import__example-title', text: t.exampleTitle }),
                    h('table', { class: 'dp-qo-import__example-table' },
                        h('thead', {}, h('tr', {}, h('th', { scope: 'col', text: t.colSku }), h('th', { scope: 'col', text: t.colQty }))),
                        h('tbody', {}, h('tr', {}, h('td', { text: t.exampleSku }), h('td', { text: t.exampleQty })))))));
        this.#bodyHost.appendChild(body);
        if (this.#uploadError) this.#announce(this.#uploadError);
    }

    async #handleFile(file) {
        const t = this.#t;
        this.#uploadError = '';
        let parsed;
        try {
            parsed = await this.#parse(file);
        } catch (e) {
            // The FILE is unusable: nothing is sent to the server; stay in / return to the upload state.
            const code = e instanceof ImportParseError ? e.code : 'generic';
            this.#uploadError = t.fileErrors?.[code] ?? t.fileErrors?.generic ?? '';
            if (this.#root) this.#setPhase('upload');
            return;
        }
        if (!this.#root) return; // closed while parsing
        this.#parsed = parsed;
        this.#runValidation();
    }

    // ── 2. validation ───────────────────────────────────────────────────────────────────────────────────────

    async #runValidation() {
        this.#setPhase('validating');
        this.#announce(this.#t.validatingTitle);
        this.#abort?.abort();
        const controller = new AbortController();
        this.#abort = controller;

        const res = await this.#validate(this.#parsed.rows, { signal: controller.signal });
        if (!this.#root || this.#abort !== controller) return; // closed / superseded
        this.#abort = null;

        if (!res.ok) {
            if (res.kind === 'aborted') return;
            this.#setPhase('validate_error');
            return;
        }
        this.#result = deriveResult(this.#parsed, res.data);
        this.#setPhase('result');
        this.#announce(this.#summaryLine());
    }

    #progress({ determinate, percent = 0, label }) {
        const bar = h('div', { class: 'dp-qo-import__bar' });
        if (determinate) bar.style.width = `${percent}%`;
        const track = h('div', {
            class: `dp-qo-import__progress${determinate ? '' : ' is-indeterminate'}`,
            role: 'progressbar', 'aria-label': label, 'aria-valuemin': '0', 'aria-valuemax': '100',
            'aria-valuenow': determinate ? String(percent) : null,
        }, bar);
        const text = h('p', { class: 'dp-qo-import__percent', text: determinate ? `${percent}%` : this.#t.working });
        return { wrap: h('div', { class: 'dp-qo-import__progress-wrap' }, track, text), bar, track, text };
    }

    #renderValidating() {
        const t = this.#t;
        const pg = this.#progress({ determinate: false, label: t.validatingTitle });
        this.#bodyHost.appendChild(h('div', { class: 'dp-qo-import__center' },
            spinner(),
            h('h3', { class: 'dp-qo-import__headline', text: t.validatingTitle }),
            h('p', { class: 'dp-qo-import__sub', text: t.validatingText }),
            pg.wrap,
            h('div', { class: 'dp-qo-import__checks' },
                h('p', { class: 'dp-qo-import__checks-title' }, svg('info', 20), h('span', { text: t.checksTitle })),
                // Explanation of what the server checks — deliberately NOT marked as completed one by one:
                // the validation is a single atomic request, so per-check progress would be invented.
                h('ul', {}, (t.checks ?? []).map((c) => h('li', {}, svg('check', 16), h('span', { text: c })))))));
        this.#footHost.appendChild(h('button', { type: 'button', class: 'dp-qo-import__btn dp-qo-import__btn--ghost', onclick: () => this.#teardown(), text: t.cancel }));
    }

    #renderValidateError() {
        const t = this.#t;
        this.#bodyHost.appendChild(h('div', { class: 'dp-qo-import__center' },
            svg('warning', 56, 'dp-qo-import__bigicon dp-qo-import__bigicon--warn'),
            h('h3', { class: 'dp-qo-import__headline', text: t.validatingTitle }),
            h('p', { class: 'dp-qo-import__box dp-qo-import__box--warn', text: t.validateFailed })));
        // Validation is read-only, so repeating it is safe (unlike the additive cart step).
        this.#footHost.append(
            h('button', { type: 'button', class: 'dp-qo-import__btn dp-qo-import__btn--ghost', onclick: () => this.#teardown(), text: t.cancel }),
            h('button', { type: 'button', class: 'dp-qo-import__btn dp-qo-import__btn--primary', onclick: () => this.#runValidation(), text: t.retry }));
        this.#announce(t.validateFailed);
    }

    // ── 3. result ───────────────────────────────────────────────────────────────────────────────────────────

    #summaryLine() {
        const t = this.#t;
        const c = this.#result.counts;
        const units = c.ready + c.adjusted;
        const parts = [];
        parts.push(units > 0 ? t.resultDone : t.resultNone);
        if (units > 0) parts.push(fill(t.resultReady, { ready: `${units} ${plural(units, t.itemForms)}` }));
        if (c.error > 0) parts.push(fill(c.error === 1 ? t.resultErrorsOne : t.resultErrorsMany, { errors: `${c.error} ${plural(c.error, t.itemForms)}` }));
        return parts.join(' ');
    }

    #messageFor(row) {
        const m = this.#t.messages ?? {};
        if (row.status === 'ready') return null;
        if (row.status === 'adjusted') return m.adjusted;
        return m[row.code] ?? m.identifier_not_found;
    }

    #statusCell(status) {
        const label = this.#t.status?.[status] ?? status;
        return h('span', { class: `dp-qo-import__status dp-qo-import__status--${status}` }, h('span', { class: 'dp-qo-import__dot', 'aria-hidden': 'true' }), label);
    }

    #qtyCell(row) {
        if (row.status === 'adjusted' && row.requested !== null) {
            return h('span', { class: 'dp-qo-import__qty' },
                h('span', { class: 'dp-qo-visually-hidden', text: fill(this.#t.qtyAdjusted, { requested: row.requested, final: row.quantity }) }),
                h('span', { 'aria-hidden': 'true' }, h('span', { class: 'dp-qo-import__qty-req', text: row.requested }), ' → ', h('strong', { text: row.quantity })));
        }
        if (row.status === 'error') return h('span', { class: 'dp-qo-import__qty', text: row.requested !== null ? String(row.requested) : '—' });
        return h('span', { class: 'dp-qo-import__qty', text: String(row.quantity) });
    }

    #renderResult() {
        const t = this.#t;
        const res = this.#result;
        const c = res.counts;
        const units = c.ready + c.adjusted;

        // Summary banner: counts are RESULTING UNITS; the source-row count is stated explicitly.
        const lines = [];
        if (units > 0) lines.push(fill(t.resultReady, { ready: `${units} ${plural(units, t.itemForms)}` }));
        if (c.error > 0) lines.push(fill(c.error === 1 ? t.resultErrorsOne : t.resultErrorsMany, { errors: `${c.error} ${plural(c.error, t.itemForms)}` }));
        const meta = [fill(t.resultRows, { rows: c.sourceRows })];
        if (c.merged > 0) meta.push(t.resultMerged);
        const banner = h('div', { class: `dp-qo-import__banner ${units > 0 ? 'dp-qo-import__banner--ok' : 'dp-qo-import__banner--bad'}` },
            svg(units > 0 ? 'basket' : 'warning', 40, 'dp-qo-import__banner-icon'),
            h('div', {}, h('p', { class: 'dp-qo-import__banner-title', text: units > 0 ? t.resultDone : t.resultNone }),
                h('p', { class: 'dp-qo-import__banner-text', text: lines.join(' ') }),
                h('p', { class: 'dp-qo-import__banner-text dp-qo-import__banner-text--meta', text: meta.join(' ') })));

        const notes = res.warnings.map((w) => this.#t.warnings?.[w.code]).filter(Boolean);

        const head = h('tr', {}, ...[t.colSku, t.colName, t.colQtyShort, t.colStatus, t.colMessage].map((c2) => h('th', { scope: 'col', text: c2 })));
        const bodyRows = res.display.map((row) => {
            const msg = this.#messageFor(row);
            const merged = row.rows.length > 1 ? fill(t.mergedRows, { rows: [...row.rows].sort((a, b) => a - b).join(', ') }) : null;
            return h('tr', { class: `dp-qo-import__row dp-qo-import__row--${row.status}` },
                h('td', { class: 'dp-qo-import__cell-sku', text: row.identifier || '—' }),
                h('td', { class: 'dp-qo-import__cell-name', text: row.name ?? '—' }),
                h('td', { class: 'dp-qo-import__cell-qty' }, this.#qtyCell(row)),
                h('td', {}, this.#statusCell(row.status)),
                h('td', { class: 'dp-qo-import__cell-msg' }, msg ?? '/', merged ? h('span', { class: 'dp-qo-import__merged', text: merged }) : null));
        });
        const table = h('table', { class: 'dp-qo-import__table' }, h('caption', { class: 'dp-qo-visually-hidden', text: t.tableCaption }), h('thead', {}, head), h('tbody', {}, bodyRows));

        this.#bodyHost.append(...[
            banner,
            notes.length ? h('ul', { class: 'dp-qo-import__notes' }, notes.map((n) => h('li', { text: n }))) : null,
            h('div', { class: 'dp-qo-import__table-wrap', role: 'region', tabindex: '0', 'aria-label': t.tableCaption }, table),
        ].filter(Boolean));

        this.#footHost.append(...[
            h('button', { type: 'button', class: 'dp-qo-import__btn dp-qo-import__btn--link', onclick: () => this.#setPhase('upload'), text: t.chooseAnother }),
            h('button', { type: 'button', class: 'dp-qo-import__btn dp-qo-import__btn--ghost', onclick: () => this.#teardown(), text: t.cancel }),
            units > 0 ? h('button', { type: 'button', class: 'dp-qo-import__btn dp-qo-import__btn--primary', onclick: () => this.#startCart(), text: t.addValid }) : null,
        ].filter(Boolean));
    }

    // ── 4. cart ─────────────────────────────────────────────────────────────────────────────────────────────

    #renderAdding() {
        const t = this.#t;
        const n = this.#result.units.length;
        this.#cartProgress = this.#progress({ determinate: false, label: t.addingTitle });
        this.#bodyHost.appendChild(h('div', { class: 'dp-qo-import__center' },
            spinner(),
            h('h3', { class: 'dp-qo-import__headline', text: t.addingTitle }),
            h('p', { class: 'dp-qo-import__sub', text: fill(t.addingText, { n: `${n} ${plural(n, t.validItemForms)}` }) }),
            this.#cartProgress.wrap));
        // No cancel here: the additive request may already be applied server-side.
    }

    #cartProgress = null;

    async #startCart() {
        const t = this.#t;
        const units = this.#result.units;
        this.#setPhase('adding');
        this.#announce(t.addingTitle);

        // Private cart state: the visible Quick Order selection is neither read nor written.
        const priv = new QuickOrderState();
        for (const u of units) priv.setQuantity(u.key, u.quantity, { productId: u.productId, variationId: u.variationId, unitPrice: 0 });

        const submit = new CartSubmit(priv, this.#config);
        const outcome = await submit.submit({
            stopOnAmbiguous: true,
            onProgress: ({ completed, total }) => this.#onChunk(completed, total),
        });
        if (!this.#root) return;
        this.#outcome = classifyCartOutcome(units, outcome);
        this.#setPhase('finished');
        this.#announce(this.#finishedLine());
    }

    #onChunk(completed, total) {
        const pg = this.#cartProgress;
        if (!pg || total < 2) return; // a single request has no measurable intermediate progress
        const percent = Math.round((completed / total) * 100);
        pg.track.classList.remove('is-indeterminate');
        pg.track.setAttribute('aria-valuenow', String(percent));
        pg.bar.style.width = `${percent}%`;
        pg.text.textContent = `${percent}% · ${fill(this.#t.chunkProgress, { done: completed, total })}`;
        this.#announce(fill(this.#t.chunkProgress, { done: completed, total }));
    }

    #overlap() {
        const has = this.#config.hasSelection;
        if (typeof has !== 'function' || !this.#outcome) return false;
        const touched = [...this.#outcome.added, ...this.#outcome.unknown];
        return touched.some((u) => has(u.key));
    }

    #finishedLine() {
        const t = this.#t;
        const o = this.#outcome;
        if (o.kind === 'success') return `${t.doneBoxTitle}. ${fill(t.doneBoxText, { n: `${o.added.length} ${plural(o.added.length, t.itemForms)}` })}`;
        const parts = [];
        if (o.added.length) parts.push(fill(t.addedCount, { n: o.added.length }));
        if (o.failed.length) parts.push(fill(t.failedCount, { n: o.failed.length }));
        if (o.unknown.length) parts.push(fill(t.unknownCount, { n: o.unknown.length }));
        if (o.unsent.length) parts.push(fill(t.unsentCount, { n: o.unsent.length }));
        return (o.kind === 'ambiguous' ? `${t.ambiguousBoxTitle}. ${this.#config.i18n?.requestFailed ?? ''} ` : '') + parts.join(' ');
    }

    #renderFinished() {
        const t = this.#t;
        const o = this.#outcome;
        const rowErrors = this.#config.i18n?.rowErrors ?? {};

        const counts = [];
        if (o.added.length) counts.push(fill(t.addedCount, { n: o.added.length }));
        if (o.failed.length) counts.push(fill(t.failedCount, { n: o.failed.length }));
        if (o.unknown.length) counts.push(fill(t.unknownCount, { n: o.unknown.length }));
        if (o.unsent.length) counts.push(fill(t.unsentCount, { n: o.unsent.length }));

        let headline; let boxClass; let icon; let boxTitle; let boxLines;
        if (o.kind === 'success') {
            headline = t.doneTitle; boxClass = 'ok'; icon = 'basket'; boxTitle = t.doneBoxTitle;
            boxLines = [fill(t.doneBoxText, { n: `${o.added.length} ${plural(o.added.length, t.itemForms)}` })];
        } else if (o.kind === 'ambiguous') {
            headline = t.ambiguousBoxTitle; boxClass = 'warn'; icon = 'warning'; boxTitle = null;
            boxLines = [this.#config.i18n?.requestFailed ?? '', counts.join(' ')];
        } else if (o.kind === 'partial') {
            headline = t.doneTitle; boxClass = 'warn'; icon = 'warning'; boxTitle = t.partialBoxTitle; boxLines = [counts.join(' ')];
        } else {
            headline = t.doneTitle; boxClass = 'bad'; icon = 'warning'; boxTitle = t.noneBoxTitle; boxLines = [counts.join(' ')];
        }

        const failedList = o.failed.length
            ? h('ul', { class: 'dp-qo-import__failed' }, o.failed.map((f) => h('li', {},
                h('strong', { text: f.unit.name ?? f.unit.identifier }),
                h('span', { class: 'dp-qo-import__muted', text: ` (${f.unit.identifier}) — ` }),
                h('span', { text: rowErrors[f.error] ?? rowErrors.not_addable ?? '' }))))
            : null;

        const pg = o.kind === 'success' ? this.#progress({ determinate: true, percent: 100, label: t.addingTitle }) : null;

        this.#bodyHost.appendChild(h('div', { class: 'dp-qo-import__center dp-qo-import__center--done' },
            o.kind === 'success' ? svg('check', 72, 'dp-qo-import__bigicon dp-qo-import__bigicon--ok') : svg('warning', 64, 'dp-qo-import__bigicon dp-qo-import__bigicon--warn'),
            h('h3', { class: 'dp-qo-import__headline', text: headline }),
            pg ? pg.wrap : null,
            h('div', { class: `dp-qo-import__banner dp-qo-import__banner--${boxClass}` },
                svg(icon, 40, 'dp-qo-import__banner-icon'),
                h('div', {}, boxTitle ? h('p', { class: 'dp-qo-import__banner-title', text: boxTitle }) : null, boxLines.filter(Boolean).map((l) => h('p', { class: 'dp-qo-import__banner-text', text: l })))),
            failedList,
            this.#overlap() ? h('p', { class: 'dp-qo-import__overlap', text: t.overlapWarning }) : null));

        // Close resets the private session. In the ambiguous case the only forward path is the cart: no retry.
        const cartLabel = o.kind === 'ambiguous' ? t.reviewCart : t.viewCart;
        this.#footHost.append(
            h('button', { type: 'button', class: 'dp-qo-import__btn dp-qo-import__btn--ghost', onclick: () => this.#teardown(), text: t.close }),
            h('a', { class: 'dp-qo-import__btn dp-qo-import__btn--primary', href: this.#config.cartUrl ?? '#', text: cartLabel }));
    }
}
