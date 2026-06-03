# Fancybox Binding Consolidation — Accepted Technical Debt

**Severity:** Low
**Priority:** Low
**Status:** Documented
**Confirmed:** 2026-06-03 via Playwright runtime inspection.

This is not a bug. No action is required. Document this only until a trigger condition occurs.

---

## Observation

Product inquiry buttons (`[data-fancybox].product-inquiry-btn`) match
two registered Fancybox bindings simultaneously:

1. `[data-fancybox]` — registered in `js/fancybox-init.js` (gallery binding)
2. `[data-fancybox].product-inquiry-btn` — registered in `inc/product-inquiry.php`

The inquiry button carries both the `data-fancybox` bare attribute and
the `.product-inquiry-btn` class, making it a match for both selectors.

## Why Behavior Is Currently Safe

Fancybox v6 (`fromTriggerEl`) iterates all matching selectors and
**merges their options into a single invocation** — it does not open twice.

Map insertion order is deterministic:
- Generic binding (`[data-fancybox]`) inserted first — by `fancybox-init.js`
- Specific binding (`[data-fancybox].product-inquiry-btn`) inserted second — by `product-inquiry.php`

Specific binding options always override generic options on any conflict,
because JS Maps preserve insertion order and later entries win the merge.

Runtime-verified final options for the inquiry button include all intended
values: `type: 'iframe'`, `trapFocus: true`, `autoFocus: true`,
`placeFocusBack: true`, `width: 850`. All accessibility options are applied.

Gallery elements are unaffected — they do not carry `.product-inquiry-btn`,
so only the generic binding matches them.

## Accepted Spillover (harmless)

The generic binding contributes these options to the inquiry modal as a
side effect of the merge. None conflict with intended inquiry behavior:

- `animated: true`
- `hideScrollbar: false`
- `compact: false`

## Trigger Conditions for Revisiting

Do not revisit unless ONE OR MORE of the following occurs:

- The generic binding in `fancybox-init.js` gains an option that should
  NOT apply to the inquiry modal (e.g. `type: 'image'`, custom toolbar,
  or a UI plugin option incompatible with iframe modals)
- A third `[data-fancybox]` binding is introduced, increasing merge complexity
- Fancybox is upgraded to a version that changes multi-binding resolution order
- The inquiry modal exhibits unexpected visual behavior not explained by
  its own registered options

## Recommended Resolution (when triggered)

Exclude the inquiry button from the generic selector using
`:not(.product-inquiry-btn)`, or consolidate both bindings into a single
init file with explicit selector scoping.

## Files Involved

- `js/fancybox-init.js` — gallery binding (`[data-fancybox]`)
- `inc/product-inquiry.php` — inquiry binding, function `dreampoint_b2b_product_inquiry_fancybox_init`
