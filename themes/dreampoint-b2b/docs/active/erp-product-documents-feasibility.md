# APROS/ZGData product documents — feasibility audit

**Status: RESEARCH ONLY / NOT APPROVED FOR IMPLEMENTATION.** The client has not requested a PDP "Dokumenti" tab. Date: 2026-10-19. Baseline Git: `7b5ea10` (clean tree). Staging only; no production environment exists. No API calls were made, nothing was imported, no code/DB/ERP state was changed.

Extends ADR-028 (which classified Dokumenti as NOT AVAILABLE for the *current payload*). This report adds what the official API documentation and the importer code say, and what remains unknown.

## Evidence

| Source | Finding |
|---|---|
| Official ZGData "API DOKUMENTACIJA — Dreampoint - B2B integracija" v1.0 (PDF, received 2026-09-22) | Documents exactly 14 GET endpoints: `classificationList`, `brandList`, `articleList`, `articleImageList`, `articleVariationList`, `articleVariationImageList`, `attributeList`, `attributeItemList`, `articleAttributeList`, `articleVariationAttributeList`, `partnerBrandDiscountList`, `countryPriceList`, `partnerDeliveryLocationList`, `partnerLegalFormCodes` (+ `partnerList` per ADR-008). No document/attachment/file/datasheet endpoint or field. |
| `articleList/get` (doc + ADR-011 live observation, 10,186 articles) | Fields: `articleId, code, title, barcode, wholesalePrice, vatRate, stock, classification, virtualArticle, visible, brandId, isNew, isSpecialOffer, description`. No document field. |
| `articleImageList/get` | `articleId, Url, position`. Image URLs are on `apross.blob.core.windows.net/tocka/<guid>.<ext>`; the importer fetches them with a plain unauthenticated `wp_remote_get`, so ERP *images* are public blob URLs. This says nothing proven about documents. |
| `uncle-dev-importer` (staging, read-only) | `AprosProvider::fetch()` calls 9 endpoints only (articleList, variation list, 2 image lists, brands, 3 attribute lists + variation attributes). `map_fields()` reads only the fields above. `ProductToImport::$attachments` exists but is never assigned or consumed; `Importer::import_image()` is image-specific. No document code path. |
| Staging data | 0 non-image attachments in Media Library; only 5 ERP attributes (model, boja, dob, dioptrija, volumen), none document-like (ADR-028). |
| Live probing | NOT performed: no documented endpoint exists to probe, and speculative requests are prohibited. |

## Answers

1. `articleList/get` includes documents? **NOT EXPOSED BY CURRENT INTEGRATION** (verified absent from documented and observed payload).
2. Verified alternative document endpoint? **None in the v1.0 documentation.** Whether ZGData/Apros has one outside v1.0: **UNKNOWN — VENDOR CONFIRMATION REQUIRED** (absence from a document is not proof of absence in the API).
3. Actual document records found? **No.** Availability: **UNKNOWN** (not AVAILABLE).
4. Public or protected links? **UNKNOWN for documents.** Images are public blob URLs; documents, if they exist, may use the same store, but that is an inference.
5. Current importer supports documents without structural changes? **No.** Needs a new endpoint call in `AprosProvider::fetch()`, mapping into `$attachments`/meta, and (Option B) a non-image download path. Protected plugin, ADR-012 applies.
6. Simplest viable approach (conditional): **Option A** — store ERP-provided document title + URL as product meta, render links in a "Dokumenti" tab; no file download. Only valid if URLs are public, stable and non-expiring. Option B (download into Media Library) if URLs are unstable/expire or must not point to the ERP blob. Option C (authenticated proxy) only if the ERP requires auth for downloads — nothing indicates that.
7. Effort: see below (conditional, whole hours).
8. Vendor questions: see below.

## Effort (CONDITIONAL, NOT A QUOTE — whole hours; valid only if the vendor confirms a document endpoint)

| Component | A: direct links | B: import & store | C: proxy |
|---|---|---|---|
| ERP API integration (endpoint fetch, parsing, ADR-012-safe change to protected importer) | 6h | 6h | 6h |
| Document sync/storage (download, MIME allow-list, dedupe of shared docs, update/remove obsolete, quota) | 0h | 10h | 8h (proxy endpoint, auth, caching) |
| WooCommerce metadata mapping | 2h | 3h | 2h |
| PDP Documents tab frontend (extend `dreampoint_b2b_product_tabs()`, template, responsive, a11y) | 5h | 5h | 5h |
| Testing + staging verification | 4h | 6h | 5h |
| **Total** | **17h** | **30h** | **26h** |

Excludes vendor coordination time and any ERP-side work to populate documents. If documents are not populated in the ERP, effort is unaffected but the tab would be empty.

## Questions for the ERP vendor (ZGData/Apros)

1. Does the B2B API (`api3`) expose product documents (datasheets, manuals, certificates)? Which endpoint, fields, and a sanitized sample response?
2. Are documents actually populated in Apros for Dreampoint's articles? Roughly how many articles have them, and which types (PDF only)?
3. How are documents linked: per article, per variation, per brand? Can one file be shared by many articles?
4. Are file URLs public and permanent (like the image blob URLs), or authenticated/expiring (SAS token)?
5. Is there a change indicator (modified date/hash) so unchanged documents can be skipped?
6. Are there licensing/confidentiality limits on exposing documents to B2B partners (all partners vs. specific ones)?

## Classification

**UNKNOWN — VENDOR CONFIRMATION REQUIRED** (not exposed by current integration; no document capability in the official v1.0 documentation; existence in the ERP unverified).

Next step if the client asks for the tab: send the questions above to the vendor, then re-estimate. Do not touch the importer before the answer.
