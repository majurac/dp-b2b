# Customer-to-Bucket Assignment Plan

**Status:** PREPARATION ONLY — nothing matched, assigned or created. Awaiting client answers and APROS identifier verification.
**Confidentiality:** this document contains no customer-level data. The source workbook lives in `docs/client-input/customer-buckets/` (excluded from Git in this local clone only via `.git/info/exclude`). Any customer-level artifact must stay in that directory; prefer not to create copies.
**Related:** ADR-018 (approval/activation, Apros source of truth), ADR-036 N3 (existing-customer migration plan), ADR-013, `customer-bucket-configuration-matrix.md`.

---

## 1. Facts

- Workbook: 96 customers, 96 unique four-digit identifiers, none malformed or duplicate. Buckets: FULL-RANGE 59, SUN ADULT & CHILD 18, CHILD 11, CUSTOM OFFER 7, SPORT 1.
- The workbook holds only country, name with identifier, historical purchase counts per product line, bucket and a free-text note. It has **no** OIB, address, e-mail, phone or contact person.
- **The identifiers are NOT verified as APROS `partnerCode`.** The APROS TEST `partnerList/get` returned a single sample partner that is not in the workbook (2026-11-09), so TEST cannot verify or refute them. Absence from TEST means nothing.
- Existing state (ADR-018): accounts originate from self-registration (pending, `approved=''`); activation is intended to come from APROS (`B2B KUPAC = DA`), which is **not implemented** (blocked on two ZGData questions). The manual `wp importer partners` sync links by e-mail or creates bare `customer` accounts (no company/OIB/phone, no `approved`, no bucket) and must not be used as a shortcut. `dp_bucket_id` assignment has no UI; `approved` ordering relative to bucket is unresolved.
- Excel purchase-history columns are history, not access rules; do not derive buckets from them.

## 2. Decisions required before any assignment

1. All 96 customers or only an active subset (ADR-036 earlier notes: ~60 customers, ~10 active).
2. Self-registration or pre-created accounts (determines whether contact details must be collected).
3. Production verification of the identifiers (APROS export with partner code and name only, or an authorised production read).
4. Completion of the approval/linking workflow (ADR-018) or an explicit owner decision to link manually in the interim.
5. Bucket-definition decisions in `customer-bucket-configuration-matrix.md` §3 (a bucket must exist and be final before anyone is assigned to it).

## 3. Mapping strategy

Chain: **workbook identifier → confirmed APROS partner → existing WordPress user → `dp_bucket_id`.** Every link must be established independently; a missing link leaves the customer unassigned.

Preconditions for a safe match:
- Identifier verified against production APROS data by code **and** name (conservative normalization: case, whitespace, Unicode; keep legal-form differences visible). OIB/e-mail used only as a secondary key (ADR-036 order: `taxId`, then `partnerCode`, then e-mail) and only if the client supplies them.
- The WordPress user exists, has `apros_partner_code` set to that confirmed code, and is activated per ADR-018 (not merely registered).
- The target bucket record exists, is published and final.

Account creation and bucket assignment are separate operations: this plan covers **only** assigning buckets to existing, approved, linked accounts. Account creation follows ADR-018/ADR-036 and is not triggered by the workbook.

## 4. Dry-run procedure (to be built, read-only)

1. Input: workbook (read locally, never copied to the server unredacted) and the verified partner list (code and name).
2. Classify each row: exact code + matching name; exact code + plausible name variation (manual review); code exists but name conflicts; code not found; duplicate/ambiguous.
3. Join to WordPress users by `apros_partner_code`: 0 users, exactly 1 user, or >1 users (conflict). Check activation state.
4. Output aggregate counts plus a customer-level report written only under `docs/client-input/customer-buckets/` (never in tracked docs, never in chat). Report per row: status, proposed bucket, blocking reason.
5. Zero writes. Repeatable and idempotent.

## 5. Handling of problem cases (fail-closed)

| Case | Action |
|---|---|
| Code not found in APROS | No assignment; send to client for correction |
| Name conflicts with code | No assignment; manual review with client |
| Several users share a code | No assignment; resolve manually (ADR-018/DP-01 cardinality) |
| No WordPress user yet | Wait for registration/activation; assignment deferred |
| User not activated | No assignment (a bucket must not precede approval until the ordering decision exists) |
| Bucket definition pending | Customer stays unassigned (engine default: no access) |
| CUSTOM OFFER customer | Bucket plus per-user product rows; without the agreed list the customer stays on no access |

An unresolved assignment must always mean "sees nothing", never a fallback to FULL-RANGE.

## 6. Execution (future, after approval)

- Test first on a 3–5 customer sample on staging; owner-approved.
- Apply via a script that only sets `dp_bucket_id` (no role, no `approved`, no other meta), logs user IDs and previous values to a private location, and refuses rows not marked "ready" in the dry-run report.
- Rollback: restore previous `dp_bucket_id` values from the log (or delete the meta key if previously absent). Cache needs no purge: the visibility context key contains the bucket ID.
- CUSTOM OFFER product rows are rolled back by deleting the recorded `wp_dp_product_access` rows for the listed users.
- Production only after staging sign-off; follow `docs/operational/` deploy discipline.

## 7. Out of scope

Creating accounts, sending activation/reset e-mails, running `wp importer partners`, approving users, changing pricing, touching `apros-pricing`, `uncle-dev-importer` or `b2b-partner-importer`.
