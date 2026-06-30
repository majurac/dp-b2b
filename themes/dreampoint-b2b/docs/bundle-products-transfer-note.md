# Bundle Products — Transfer Note

**Datum:** 2026-06-09
**Status:** Out of ERP scope — transferred

---

## Requirement

Display stand for glasses cannot be purchased independently. It can only be ordered together with a predefined quantity of glasses.

This pattern was identified during the June 2026 stakeholder workshop.

---

## Scope Classification

**Owner:** `uncledev-product-bundles`

**Not part of:**
- ERP integration (`dp-b2b-erp-importer`)
- Apros validation or API questions
- ERP architecture (pricing, stock, order export, customer sync)

**Reason:** Bundle composition is a WooCommerce business rule. ERP remains unaware of bundle construction. Apros receives the resulting order line items — it does not define or enforce which products must be purchased together.

---

## ERP Boundary

The ERP layer is responsible only for:
- product import
- pricing import
- stock import
- customer import / synchronization
- order export

Bundle product rules are applied before the order payload is assembled. From Apros's perspective, a completed order arrives with its final line items — the bundling constraint is enforced in WooCommerce, not in Apros.

---

## Open Questions (for uncledev-product-bundles scope)

- How many bundle rules exist? Is the glasses + stand case isolated or part of a wider pattern?
- Is the bundle rule defined in CMS by an admin, or does it originate from Apros (e.g. via a product attribute)?
- What is the expected UX when a bundled item is added to cart independently?
- Is quantity proportionality enforced (e.g. 1 stand requires exactly N glasses)?

These questions are not ERP validation items. They belong to the `uncledev-product-bundles` feature specification.

---

## Removed From

| Document | What was removed |
|----------|-----------------|
| `docs/erp-discovery-findings.md` | Question #11 — bundle products as ERP open question |
| `docs/project-status-matrix.md` | DP-D3 as ERP/Dream Point blocker |
| `docs/b2b-erp-migration-plan.md` | DP-D3 row in BLOCKED BY DREAM POINT table |
