# DP-B2B ERP Migration Plan

**Datum:** 2026-06-09  
**Baseline plugin:** Uncle Dev Importer (Apros) — produkcija Jekaa  
**Companion dokumenti:**  
- `docs/b2b-erp-plugin-analysis.md` — detaljna analiza baseline plugina  
- `docs/b2b-erp-adaptation-blueprint.md` — arhitekturalni blueprint  
- `docs/apros-question-resolution-matrix.md` — status AP-01 – AP-14 blokirajućih pitanja  
- `docs/erp-discovery-findings.md` — potvrđena pravila i nevalidirane pretpostavke  

> Ovaj dokument je implementacijski plan. Sadrži konkretne korake, klasifikacije i sekvenciranje.  
> Blokeri koji sprečavaju određene zadatke su eksplicitno označeni — ali plan identificira i što može početi **odmah**.  
> Sva arhitekturalna logika i pozadina odluka je u companion dokumentima.

---

## 1. Executive Summary

### Što ostaje nepromijenjeno

- **CurlClient.php** — generički HTTP klijent, prenosi se bez izmjena
- **BaseProvider.php** — apstraktna baza s endpoint/credential upravljanjem
- **ProductAttribute.php** — DTO bez B2C logike
- **ImporterWPClient.php** — WP-CLI interface i performance optimizacije
- **hooks.php** — variation image display pattern i Redis cache sloj
- **Monitor plugin** — session tracking, alerting dashboard, historija sesija
- **Svih 5 ACF field group-a** — importer settings, product locks, category mapping, brand mapping, variation images
- **Importer core logika** (~75%) — kreiranje/ažuriranje produkata, attribute taxonomy management, image pipeline, change detection, trash-missing-products

### Što zahtijeva modifikaciju

- **AprosProvider.php** — dodati `b2bArticle` filter, mapirati `wholesalePrice`, proširiti za partner i delivery location endpointe; dodati auth header inject (AP-09)
- **ProductToImport.php** — proširiti DTO s B2B poljima: `b2b_eligible`, `wholesale_price`, `stock_by_warehouse[]`
- **Importer.php core loop** — dodati B2B article filter, per-warehouse stock storage, pricing storage hook; resetirati `_RESPONSE_HASH` verziju
- **ERP payment/shipping companion plugin** — proširiti za B2B payment metode i B2B shipping metode
- **Cron konfiguracija** — nova putanja, novi server user, prilagoditi za B2B server

### Što zahtijeva novu implementaciju

- **B2B order export** — kompletan rewrite `order.php`: dodati `sif_kup`, `billingOib`, `deliveryLocationId`, `salesLocationId`; zamijeniti inline send s Action Scheduler; dodati retry, idempotency i admin vidljivost
- **Pricing engine** — nova komponenta: WooCommerce price filter, per-partner rabat storage, PDV logika po tax profilu, Redis cache za pricing data; model ovisi o AP-01 validaciji
- **Partner sync adapter** — nova komponenta: fetch `sif_kup`, pravni oblik, ugovorni uvjeti, dostavne lokacije; vezivanje WP korisnika na Apros partnera
- **Approval webhook endpoint** — `POST /wp-json/dreampoint-b2b/v1/approve-partner`; prima Apros signal, dodjeljuje B2B rolu, povlači partner podatke
- **B2B product filter** — `b2bArticle` eligibility check u sync-u: skip non-B2B artikala
- **Multi-warehouse stock storage** — `_stock_warehouse_1/3/4/5` custom meta; brand→warehouse mapping admin UI; WC `stock_quantity` = matično skladište

---

## 2. Component Inventory

### Klasifikacija svake komponente

| Komponenta | Klasifikacija | Napomena |
|------------|---------------|----------|
| `CurlClient.php` | ✅ REUSE AS-IS | Generički HTTP klijent; auth header inject dodati kao proširenje, ne promjenu |
| `BaseProvider.php` | ✅ REUSE AS-IS | Apstraktna klasa; endpoint/credential pattern identičan |
| `ProductAttribute.php` | ✅ REUSE AS-IS | DTO bez B2C logike |
| `ImporterWPClient.php` | ✅ REUSE AS-IS | WP-CLI interface; performance optimizacije su generički korisne |
| `hooks.php` | ✅ REUSE AS-IS | Variation image display; Redis cache sloj — oba su generički |
| Monitor plugin | ✅ REUSE AS-IS | Session tracking, alerting dashboard, historija sesija, admin notices |
| ACF: importer settings | ✅ REUSE AS-IS | Provider config pattern identičan (base_endpoint, credentials) |
| ACF: product lock fields | ✅ REUSE AS-IS | `lock_price`, `lock_name` — generički override mehanizam |
| ACF: category mapping | ✅ REUSE AS-IS | Remote ID mapiranje je generički pattern |
| ACF: brand mapping | ✅ REUSE AS-IS | Remote ID mapiranje je generički pattern |
| ACF: variation images | ✅ REUSE AS-IS | Variation image repeater s hash tracking-om |
| `ProductToImport.php` | 🔧 REUSE WITH MODIFICATIONS | Dodati: `b2b_eligible` (bool), `wholesale_price` (decimal), `stock_by_warehouse` (array) |
| `AprosProvider.php` | 🔧 REUSE WITH MODIFICATIONS | `fetch()`: dodati partner + delivery location endpoint pozive; `map_fields()`: mapirati `b2bArticle`, `wholesalePrice`, per-warehouse stock; auth header inject |
| `Importer.php` | 🔧 REUSE WITH MODIFICATIONS | Core loop: dodati `b2bArticle` filter, per-warehouse stock save; resetirati `_RESPONSE_HASH` verziju za re-import trigger pri deployu |
| ERP payment/shipping plugin | 🔧 REUSE WITH MODIFICATIONS | Proširiti admin UI za B2B metode plaćanja i dostave; zadržati mapping pattern |
| Cron konfiguracija | 🔧 REUSE WITH MODIFICATIONS | Nova putanja `/home/<b2b-user>/`, novi server user; prilagoditi log rotaciju |
| `order.php` | 🔴 REWRITE | Hardkodirani engraving ID (56399) mora biti uklonjen; dodati `sif_kup`, `billingOib`, `deliveryLocationId`, `salesLocationId`; zamijeniti inline send s Action Scheduler |
| Pricing engine | 🆕 NEW COMPONENT | Ne postoji u B2C pluginu; nova WC price filter komponenta; model A/B/C ovisi o AP-01 |
| Partner sync adapter | 🆕 NEW COMPONENT | Ne postoji u B2C; fetch i storage Apros partner podataka; sif_kup binding |
| Approval webhook endpoint | 🆕 NEW COMPONENT | `POST /wp-json/dreampoint-b2b/v1/approve-partner`; onboarding flow trigger |
| B2B product filter | 🆕 NEW COMPONENT | `b2bArticle` eligibility provjera; skip non-B2B artikala iz sync-a |
| Multi-warehouse stock | 🆕 NEW COMPONENT | `_stock_warehouse_*` meta storage; brand→warehouse mapping; stock display UI na PDP |

### Komponente koje se uklanjaju (Jekaa-specifično)

| Komponenta | Razlog uklanjanja |
|------------|-------------------|
| Engraving item (`productId=56399`) | Jekaa-specifičan feature; ne postoji u B2B katalogu |
| Engraving price logic (`_personalization_price`) | Vezana uz engraving; ukloniti zajedno |
| `billingOib: ""` hardcode | Zamijeniti dinamičkim dohvatom iz `user_meta._b2b_oib` |
| Atribut 154 ("Kolekcija") skip logika | Verificirati vrijedi li ista logika za B2B; ako ne — ukloniti filter |

---

## 3. Product Import Adaptation

### Trenutno ponašanje (Jekaa B2C)

- `articleList/get` vraća sve artikle; svi se importiraju
- `virtualArticle: true` → WC Variable Product; `false` → WC Simple Product
- Jedan `stock` field po varijaciji
- `priceWithVat` → WC `regular_price`; nema per-partner pricing
- `_RESPONSE_HASH = md5(serialize($product) + business_date + 'v20260401-13')` — skip unchanged

### Zahtijevano B2B ponašanje

- Import samo artikala s `b2bArticle: true`; skip svih ostalih bez greške
- Varijantna struktura identična B2C (isti endpoint format, potvrđeno NC-02 + AP-04)
- Per-warehouse stock: `_stock_warehouse_1`, `_stock_warehouse_3`, `_stock_warehouse_4`, `_stock_warehouse_5`
- WC `stock_quantity` = vrijednost matičnog skladišta (brand → warehouse mapping)
- `wholesalePrice` storage za pricing engine; semantika ovisi o AP-01 validaciji
- Nova B2B ERP atributi na artiklu: "Novi proizvod na B2B", "Posebna ponuda"
- **Kataloški broj artikla vidljiv na PDP** — dolazi iz Apros-a; ima prioritet nad SKU-om u prikazu

### Gap analiza

| Aspekt | Postojeće | Potrebno | Gap |
|--------|-----------|----------|-----|
| B2B eligibilnost | Nema filtera | `b2bArticle: true` filter | Novi filter u `map_fields()` + `import()` |
| Stock model | Jedan `stock` field | 4 warehouse custom meta | Novi storage; `ProductToImport` proširenje |
| Cijena | `priceWithVat` → `regular_price` | `wholesalePrice` + pricing engine | Nova polja na DTO; pricing engine (nova komponenta) |
| Variable/Simple split | `virtualArticle` flag | Isti flag, isti format | Nema promjene |
| Change detection | `_RESPONSE_HASH v20260401-13` | Nova verzija (re-import trigger) | Promijeniti hash verziju pri deployu |
| B2B atributi | "Kolekcija" skip + standardni | "Novi proizvod", "Posebna ponuda" | Mapiranje novih atributa u `map_fields()` |
| Kataloški broj | Pohranjen u `_ARTICLE_CODE` | Prikazan na PDP | Frontend template izmjena |

### Implementacijski pristup

**`b2bArticle` handling:**
```php
// U AprosProvider::map_fields() — skip non-B2B artikala
if ( empty( $raw['b2bArticle'] ) || ! $raw['b2bArticle'] ) {
    return null; // Importer::import() preskače null DTO
}
```

**Warehouse stock handling:**
```
ProductToImport::$stock_by_warehouse = [
    1 => int,   // Glavno
    3 => int,   // Igračke
    4 => int,   // Naočale
    5 => int,   // Lifestyle
]
```
Mapping `brandId` → `warehouseId` iz `dp_b2b_brand_warehouse_map` WP option. WC `stock_quantity` = `stock_by_warehouse[$primary_warehouse_id]`.

**Visibility handling:**
Vidljivost artikala ostaje u vlasništvu B2B visibility sustava (potvrđeno NC-01). `b2bArticle` filter u sync-u određuje koji artikli **ulaze** u bazu — visibility sustav određuje koji su **vidljivi** pojedinim partnerima. Ovo su ortogonalni sustavi; ne smije se miješati logika.

**Variable product handling:**
Isti `virtualArticle` split kao B2C. `/articleVariationList/get` endpoint se koristi identično. `detect_varying_attributes()` i `update_variation_images()` metode se prenose bez izmjena.

---

## 4. Customer / Partner Layer

### Trenutna implementacija (Jekaa B2C)

B2C plugin nema customer/partner sync. Jedina veza kupca i ERP-a je kroz order export — billing podaci iz WC narudžbe. `billingOib` je hardkodirano prazan string. Nema `sif_kup` nigdje u kodu.

### Zahtijevana implementacija

#### sif_kup storage

```
WP User meta
  _sif_kup                 — Apros partner ID (string, vlasništvo Apros-a)
  _b2b_status              — enum: pending | approved | suspended
  _b2b_tax_profile         — enum: domestic | foreign | tax_exempt
  _b2b_oib                 — OIB/VAT ID (unosi kupac pri registraciji)
  _b2b_company_name        — naziv tvrtke
  _b2b_advance_only        — bool (payment restriction)
  _b2b_free_shipping       — bool
  _b2b_approved_at         — datetime
  _b2b_delivery_locations  — JSON array (format ovisi o AP-07)
```

#### Partner metadata

Izvor istine za `sif_kup` i ugovornih uvjete je Apros. WC čuva kopiju. Rabat po brandu (Rabat 1) pohranjen kao `_b2b_brand_rabat` user meta — JSON: `{ brandId: percentage, ... }`. Cjenici po državama pohranjen kao product meta (vidi Sekciju 5 — Model C).

#### Approval workflow

```
[1] Kupac popunjava web formu → WP kreira pending WP User
[2] Email notifikacija → Dream Point admin
[3] Dream Point ručno otvara partnera u Apros-u
[4] Apros postavlja B2B atribute; šalje webhook
[5] POST /wp-json/dreampoint-b2b/v1/approve-partner
[6] WP prima webhook → povlači partner data; dodjeljuje B2B rolu
[7] Kupac dobiva pristup
```

#### Model korisničkih računa (DP-01 — revidiran)

Identificirana su dva scenarija — odluka nije donesena:

**Scenarij A:** Jedan WP korisnik, više dostavnih adresa (1:1 user ↔ `sif_kup`)
- Storage: user meta (jednostavniji model)
- Delivery locations: JSON array u `_b2b_delivery_locations` user meta

**Scenarij B:** Više WP korisnika (zaposlenici iste firme), više dostavnih adresa (1:N)
- Storage: `dp_b2b_company` custom post type
- `_dp_company_id` na WP User
- Pricing i ugovorni uvjeti na company razini
- Delivery locations na company razini

**Arhitekturalni zahtjev:** DP-01 mora biti potvrđen **prije Faze 3**. Odabir Scenarija B zahtijeva Company CPT — migracija naknadno nije trivijalna.

### Migration impact

| Element | B2C | B2B |
|---------|-----|-----|
| Customer sync | Ne postoji | Nova komponenta (Faza 3) |
| `sif_kup` | Ne postoji | `_sif_kup` user meta |
| Rabat storage | Ne postoji | `_b2b_brand_rabat` user meta |
| Approval flow | Ne postoji | Webhook endpoint + rola dodjela |
| OIB | Prazan hardcode | `_b2b_oib` user meta iz registracije |

---

## 5. Pricing Engine

### Što postoji danas

WooCommerce `regular_price` = `priceWithVat` iz Apros-a. Jedna cijena za sve korisnike. `lock_price` ACF boolean na produktu — sprečava ERP override za pojedine artikle. Nema per-partner logike, nema PDV izuzetaka, nema tier pricing.

### Što nedostaje

- Per-partner cijena (domaći kupci: `wholesalePrice` + Rabat 1; strani kupci: finalna neto cijena iz cjenika)
- **Promocijska cijena s prioritetom:** ako artikal ima aktivnu promotivnu cijenu (ERP atribut "Posebna ponuda"), ona ima prioritet nad Rabat 1 — mora biti eksplicitno implementirano u pricing filter hook-u
- **Iznos popusta ne prikazuje se na ribbon-ima** — no custom ribbon discount display potreban; discount vidljiv samo na fakturama
- PDV logika per tax profil: domestic → dodati PDV; foreign → bez PDV-a; tax_exempt → bez PDV-a
- Redis cache za per-user pricing data (listing performansa na velikom B2B katalogu)
- Cache invalidacija pri partner sync-u (promjena ugovornih uvjeta → invalidirati cache)

### Izolacija u zasebnu komponentu

Pricing engine mora biti odvojena klasa/komponenta koja je injectana u Importer i WC filter hook-ove. Razlog: tri moguća modela (A, B, C) ne smiju biti isprepletenа s import logikom — zamjena modela mora biti zamjena jedne klase, ne refaktor cijelog importera.

Predviđena struktura:
```
src/
  Pricing/
    PricingEngine.php         ← interface / abstrakcija
    ModelA_RabatCalculator.php
    ModelB_DirectPrice.php
    CountryPriceResolver.php  ← Model C (strani kupci)
```

### Arhitektura za sva tri modela (bez odluke)

**Model A — `wholesalePrice` je bazna cijena; Rabat 1 u WC-u**

- `_wholesale_price` product meta (iz `articleList/get`)
- `_b2b_brand_rabat` user meta: `{ brandId: percentage }` (iz ugovornih uvjeta)
- Runtime: `wholesale_price × (1 − rabat%)` u `woocommerce_product_get_price` filter hook-u
- Listing: neto s rabatom, bez PDV-a; Košarica: + PDV za domestic
- Redis cache key: `dp_b2b_price_{user_id}_{product_id}`

**Model B — `wholesalePrice` je finalna per-partner cijena**

- `_wholesale_price` product meta = gotova cijena
- WC prikazuje direktno; filter dodaje samo PDV logiku
- Minimalni pricing engine; nema rabat kalkulacije u WC-u
- Redis manje kritičan — nema per-user kalkulacije

**Model C — Strani kupci (dodatni sloj, ne alternativa A/B)**

- `_wholesale_price_country` product meta: `{ "DE": price, "AT": price }` JSON
- Runtime: ako `_b2b_tax_profile === 'foreign'` → čitaj country-specific cijenu, bez PDV-a
- Mora biti planiran od početka — naknadna ugradnja u pricing filter je rizična

**Odluka o modelu: blokirana AP-01.** Ne smije se odabrati model bez realnog payload primjera.

---

## 6. Order Export Rewrite Plan

### Trenutna implementacija i ograničenja

`order.php` u Jekaa B2C ima sljedeće Jekaa-specifične hardcode dijelove koji moraju biti uklonjeni:
- `productId: 56399` — Jekaa engraving item
- `_personalization_price` meta čitanje
- `billingOib: ""` — uvijek prazan
- Inline `wp_remote_post()` bez retry-a

Što nedostaje za B2B:
- `sif_kup` u payloadu
- `deliveryLocationId` — odabrana dostavna lokacija
- `salesLocationId` — prodajno mjesto 3 ili 5 (po vrsti robe)
- Action Scheduler flow umjesto inline senda
- Retry logika s exponential backoff-om
- Admin vidljivost sync statusa

### Zahtijevana B2B payload struktura

```json
{
  "number": "<wc_order_id>",
  "date": "YYYY-MM-DD",
  "sif_kup": "<user_meta._sif_kup>",
  "paymentTypeId": "<erp_id iz payment/shipping companion>",
  "shippingMethodId": "<erp_id iz payment/shipping companion>",
  "salesLocationId": "<3 ili 5 — routing po vrsti robe>",
  "billingTitle": "<firma ili ime/prezime>",
  "billingAddress": "<ulica i broj>",
  "billingZipCode": "<poštanski broj>",
  "billingCity": "<grad>",
  "billingCountry": "<ISO kod>",
  "billingPhone": "<telefon>",
  "billingEmail": "<email>",
  "billingOib": "<user_meta._b2b_oib>",
  "deliveryLocationId": "<odabrana lokacija, order meta>",
  "shippingTitle": "<adresa isporuke>",
  "shippingAddress": "...",
  "items": [
    {
      "position": 1,
      "productId": "<_ARTICLE_ID meta>",
      "code": "<_ARTICLE_CODE meta>",
      "title": "<naziv>",
      "priceWithoutVat": 0.00,
      "vatRate": 25.0,
      "priceWithVat": 0.00,
      "discountPercentage": 0,
      "quantity": 1,
      "amount": 0.00
    }
  ]
}
```

### Action Scheduler flow

```
[1] Hook: woocommerce_order_status_processing / woocommerce_payment_complete
[2] Provjeri _erp_sync_status — ako postoji: preskoči (idempotency guard)
[3] as_schedule_single_action('dp_erp_send_b2b_order', ['order_id' => $id], 'dp-erp')
[4] AS runner: validacija podataka → payload build → POST → Apros
[5a] Uspjeh: _erp_sync_status = 'synced'; _erp_apros_order_id = numberErp; _erp_synced_at = now()
[5b] Transijentna greška (timeout, 5xx): AS retry — exponential backoff, max 3 pokušaja
[5c] Permanentna greška (4xx, validation error): admin notifikacija; _erp_sync_status = 'failed'
```

### Error handling i retry strategija

- **Transijentni**: mrežni timeout, HTTP 500/503 → retry s backoff (1. pokušaj: 5 min, 2.: 30 min, 3.: 2h)
- **Permanentni**: HTTP 400/422, validation error, auth greška → ne retry; admin notice + log
- **Nepoznati Apros error format**: parsirati `[{"result": "Error", "message": "..."}]` pattern; ako nije prepoznatljiv format → treat as permanentni

### Idempotency strategija

- Client-side guard: `_erp_sync_status` meta — provjeri prije svakog slanja
- Ručna admin akcija (`woocommerce_order_action_sync_to_erp`): bypass guard, uvijek pošalji
- Server-side Apros idempotency: nevalidiran (AP-06); konzervativni retry dok AP-06 nije potvrđen (samo na timeout/5xx, ne na svaku grešku)
- Ako Apros vrati `numberErp`: pohrani kao second guard (`_erp_apros_order_id`) — ne šalji narudžbu koja već ima Apros ID osim kroz ručnu akciju

### Checkout UI za delivery location

- Custom checkout field (radio/select) koji čita `_b2b_delivery_locations` user meta
- Prikazuje samo za B2B korisnike (capability check)
- Odabrana lokacija se sprema na narudžbu kao `_b2b_selected_delivery_location`
- B2B narudžba bez odabrane lokacije mora biti blokirana (validation hook)

---

## 7. Database Impact

### Novi post meta (na produktima/varijacijama)

| Meta key | Tip | Svrha | Klasifikacija |
|----------|-----|-------|---------------|
| `_wholesale_price` | decimal | Apros wholesale cijena (za pricing engine) | REQUIRED |
| `_stock_warehouse_1` | integer | Zaliha — Glavno skladište | REQUIRED |
| `_stock_warehouse_3` | integer | Zaliha — Igračke | REQUIRED |
| `_stock_warehouse_4` | integer | Zaliha — Naočale | REQUIRED |
| `_stock_warehouse_5` | integer | Zaliha — Lifestyle | REQUIRED |
| `_stock_primary_warehouse` | integer | ID matičnog skladišta (brand mapping rezultat) | REQUIRED |
| `_wholesale_price_country` | JSON | Cjenici po državama za strane kupce (Model C) | OPTIONAL (ovisi o AP-01) |
| `_b2b_new_product` | bool | "Novi proizvod na B2B" ERP atribut | REQUIRED |
| `_b2b_special_offer` | bool | "Posebna ponuda" ERP atribut | REQUIRED |

### Novi user meta (na WP korisnicima)

| Meta key | Tip | Svrha | Klasifikacija |
|----------|-----|-------|---------------|
| `_sif_kup` | string | Apros partner ID | REQUIRED |
| `_b2b_status` | enum | pending / approved / suspended | REQUIRED |
| `_b2b_tax_profile` | enum | domestic / foreign / tax_exempt | REQUIRED |
| `_b2b_oib` | string | OIB/VAT ID | REQUIRED |
| `_b2b_company_name` | string | Naziv tvrtke | REQUIRED |
| `_b2b_advance_only` | bool | Samo avansno plaćanje | REQUIRED |
| `_b2b_free_shipping` | bool | Besplatna dostava | REQUIRED |
| `_b2b_approved_at` | datetime | Timestamp odobrenja | REQUIRED |
| `_b2b_delivery_locations` | JSON | Lista dostavnih lokacija (format ovisi o AP-07) | REQUIRED |
| `_b2b_brand_rabat` | JSON | `{ brandId: percentage }` rabat po brandu | REQUIRED (za Model A) / OPTIONAL (za Model B) |

### Novi order meta (na WC narudžbama)

| Meta key | Tip | Svrha | Klasifikacija |
|----------|-----|-------|---------------|
| `_b2b_selected_delivery_location` | string | Apros location ID odabran na checkoutu | REQUIRED |
| `_erp_apros_order_id` | string | `numberErp` iz Apros response-a | REQUIRED |
| `_erp_sync_status` | string | synced / failed / pending | REQUIRED (postoji u B2C) |
| `_erp_synced_at` | datetime | Timestamp uspješnog sync-a | REQUIRED |
| `_erp_payload_log` | JSON | Zadnji payload poslat (za debugging) | OPTIONAL |

### Novi WP options

| Option key | Svrha | Klasifikacija |
|------------|-------|---------------|
| `dp_b2b_brand_warehouse_map` | brandId → warehouseId mapping (admin konfiguriran) | REQUIRED |
| `dp_b2b_erp_base_endpoint` | Apros B2B API base URL | REQUIRED |
| `dp_b2b_erp_auth_config` | Auth konfiguracija (format ovisi o AP-09) | REQUIRED |

### Custom tablice

Trenutno **nije planirano.** User meta je dovoljan za 1:1 `sif_kup` model. Ako DP-01 validacija potvrdi 1:N model (više korisnika po `sif_kup`), potrebna je nova custom tablica `dp_b2b_companies` — ovo je architecturalni breakpoint koji zahtijeva odluku **prije Faze 3**.

---

## 8. Implementation Sequence

### Korak 1 — Fork i namespace migracija

**Opis:** Kopirati plugin iz Jekaa okruženja u DP-B2B projekt. Promijeniti namespace, plugin slug, konstante.

**Što se radi:**
- Kopirati `uncle-dev-importer/` u dp-b2b `/wp-content/plugins/dp-b2b-erp-importer/`
- Namespace: `UncleDev\Importer\` → `UncleDev\B2BImporter\`
- Plugin slug i opcijski prefiks: `uncle_dev_importer_` → `dp_b2b_importer_`
- ACF field group slugove: prefix s `dp_b2b_` (nova JSON eksport)
- Monitor plugin kopirati s novim slugom

**Ovisnosti:** Pristup DP-B2B staging okruženju  
**Rizik:** NIZAK — samo refaktor, nema logičkih promjena  
**Može početi bez Apros sesije?** ✅ DA

---

### Korak 2 — Uklanjanje Jekaa-specifičnih dijelova

**Opis:** Ukloniti sav kod koji je specifičan za Jekaa B2C i ne vrijedi za DP-B2B.

**Što se uklanja iz `order.php`:**
- Engraving item block (productId = 56399)
- `_personalization_price` meta čitanje
- `billingOib: ""` hardcode

**Što se verificira:**
- Atribut ID 154 ("Kolekcija") skip logika — verificirati s Dream Point vrijedi li i za B2B; ako ne vrijedi, ukloniti filter

**Ovisnosti:** Korak 1  
**Rizik:** NIZAK  
**Može početi bez Apros sesije?** ✅ DA

---

### Korak 3 — Proširenje ProductToImport DTO

**Opis:** Dodati B2B-specifična polja na data transfer objekt.

**Nova polja:**
```php
public bool   $b2b_eligible       = false;
public float  $wholesale_price     = 0.0;
public array  $stock_by_warehouse  = []; // [warehouseId => quantity]
public bool   $is_new_product      = false;
public bool   $is_special_offer    = false;
```

**Ovisnosti:** Korak 1  
**Rizik:** NIZAK — additivna promjena, ne mijenja postojeća polja  
**Može početi bez Apros sesije?** ✅ DA

---

### Korak 4 — `b2bArticle` filter u AprosProvider

**Opis:** Dodati eligibility provjeru u `map_fields()` — artikli bez `b2bArticle: true` ne ulaze u sync pipeline.

```php
// AprosProvider::map_fields()
if ( empty( $raw['b2bArticle'] ) ) {
    return null; // Importer::import() preskače null
}
$dto->b2b_eligible = true;
$dto->wholesale_price = (float) ( $raw['wholesalePrice'] ?? 0.0 );
```

**Ovisnosti:** Korak 3  
**Rizik:** SREDNJI — prvi run nakon deployu će re-importirati sve proizvode (intentionally, zbog reset `_RESPONSE_HASH` verzije)  
**Može početi bez Apros sesije?** ✅ DA (implementacija); Faza 2 zahtijeva realni B2B payload primjer za verifikaciju (AP-04)

---

### Korak 5 — Auth header inject mehanizam (placeholder)

**Opis:** Proširiti `CurlClient` s mogućnošću injektiranja auth headera. Auth model je nevalidiran (AP-09), ali infrastruktura mora biti na mjestu.

```php
// CurlClient — dodati metodu
public function set_auth_header( string $header_value ): void {
    $this->auth_header = $header_value;
}
```

Aprostakt `BaseProvider` proširiti za auth header provisioning. Konkretna implementacija (API key, Bearer, Basic) dolazi u Koraku 14 nakon AP-09 validacije.

**Ovisnosti:** Korak 1  
**Rizik:** NIZAK — placeholder ne mijenja ponašanje  
**Može početi bez Apros sesije?** ✅ DA

---

### Korak 6 — Per-warehouse stock storage u Importer.php

**Opis:** Dodati logiku za pohranjivanje per-warehouse stanja u `Importer::import()` loopi.

**Što se implementira:**
- Čitanje `stock_by_warehouse` iz DTO
- Pohrana: `update_post_meta($product_id, '_stock_warehouse_' . $wh_id, $qty)` za svako skladište
- `dp_b2b_brand_warehouse_map` option lookup → identificiranje matičnog skladišta
- WC `set_stock_quantity($stock_by_warehouse[$primary_warehouse_id])`
- `_stock_primary_warehouse` meta update

**Brand → Warehouse mapping admin UI** (novi ACF options tab ili custom WP settings page):
- Definirati admin UI za `dp_b2b_brand_warehouse_map`
- UI blokiran na Faza 2 validaciji payload formata (AP-07 — warehouse payload format)

**Ovisnosti:** Korak 3  
**Rizik:** SREDNJI — stock management promjena; testirati na staging prije produkcije  
**Može početi bez Apros sesije?** ⚠️ DJELOMIČNO — struktura se može implementirati; AP-07 je potreban za verifikaciju točnog warehouse payload formata

---

### Korak 7 — `_RESPONSE_HASH` verzija reset

**Opis:** Promijeniti hash verziju u `Importer.php` kako bi se pri prvom deployu pokrenuo re-import svih B2B artikala.

```php
// Staro (Jekaa):
$hash = md5( serialize($product) . $business_date . 'v20260401-13' );
// Novo (B2B):
$hash = md5( serialize($product) . $business_date . 'v20260601-01-b2b' );
```

**Ovisnosti:** Korak 4 (novi DTO uključen u hash)  
**Rizik:** NIZAK — namjerno ponašanje; re-import je željeni efekt pri prvom B2B deployu  
**Može početi bez Apros sesije?** ✅ DA

---

### Korak 8 — Cron konfiguracija za B2B server

**Opis:** Kreirati cron konfiguraciju za B2B server korisnika.

```bash
# /home/<b2b-site-user>/crontab
45 * * * *  /usr/local/bin/wp --path=/home/<b2b-user>/public_html importer import > /home/<b2b-user>/logs/erp-import/run_$(date +\%Y\%m\%d_\%H\%M\%S).log 2>&1
*/5 * * * *  /usr/local/bin/wp --path=/home/<b2b-user>/public_html cron event run --due-now
```

Log rotacija: zadržati 72 sesije (identično Jekaa-u).

**Ovisnosti:** Staging environment dostupan  
**Rizik:** NIZAK  
**Može početi bez Apros sesije?** ✅ DA (konfiguracija je neovisna o Apros validaciji)

---

### Korak 9 — B2B partner sync adapter (Faza 3)

**Opis:** Implementirati partner list fetch i WP User → `sif_kup` binding storage.

Preduvjeti: AP-03 (approval webhook body), AP-07 (delivery locations format), DP-01 (sif_kup kardinalitet).

**Što se implementira:**
- Partner list endpoint fetch (URL nevalidirani — AP-07)
- `sif_kup` → WP User lookup / insert logika
- Pohrana: `_sif_kup`, `_b2b_company_name`, `_b2b_tax_profile`, `_b2b_advance_only`, `_b2b_free_shipping`
- Delivery locations storage (format ovisi o AP-07)
- Rabat po brandu storage: `_b2b_brand_rabat` JSON

**Ovisnosti:** Korak 1, AP-03, AP-07, DP-01  
**Rizik:** VISOK — storage model ovisi o DP-01 validaciji (1:1 vs 1:N)  
**Može početi bez Apros sesije?** 🔴 NE

---

### Korak 10 — Approval webhook endpoint (Faza 3)

**Opis:** Registrirati WP REST endpoint koji prima Apros approval signal.

```
POST /wp-json/dreampoint-b2b/v1/approve-partner
```

**Logika:**
1. Verificirati webhook signature (format ovisi o AP-03)
2. Identificirati WP korisnika po `email` ili `sif_kup`
3. Pokrenuti partner data fetch (Korak 9)
4. Dodijeliti B2B rolu
5. Pohraniti `_b2b_status = 'approved'`, `_b2b_approved_at`
6. Triggerati welcome email

**Ovisnosti:** Korak 9, AP-03  
**Rizik:** VISOK — onboarding flow greška rezultira neaktivnim B2B računom  
**Može početi bez Apros sesije?** 🔴 NE (AP-03 je P0 bloker za webhook body)

---

### Korak 11 — Pricing engine (Faza 4)

**Opis:** Implementirati WooCommerce price filter za B2B korisnike.

Preduvjeti: Faza 3 (partner data dostupni), AP-01 (pricing model odabran).

**Zajednički za sve modele:**
- `woocommerce_product_get_price` i `woocommerce_product_get_regular_price` filter
- Check: je li korisnik B2B (`current_user_can('b2b_access')`)
- PDV logika: `domestic` → dodati PDV; `foreign` / `tax_exempt` → bez PDV-a
- Redis cache za per-user pricing data
- Cache invalidacija pri partner sync-u (action hook po partneru)

**Model-specifično:** vidi Sekciju 5.

**Ovisnosti:** Korak 9, AP-01  
**Rizik:** KRITIČAN — pricing greška je poslovni incident; QA s realnim partnerskim podacima obavezna  
**Može početi bez Apros sesije?** 🔴 NE (AP-01 je P0 bloker za odabir modela)

---

### Korak 12 — B2B order export rewrite (Faza 5)

**Opis:** Kompletni rewrite `order.php`.

Preduvjeti: Korak 9 (partner data), AP-02 (order endpoint), AP-09 (auth), DP-02 (sales location routing).

**Što se implementira:**
- `dp_erp_send_b2b_order` Action Scheduler hook
- Payload builder: čita `_sif_kup`, `_b2b_oib`, `_b2b_selected_delivery_location` iz order meta
- `salesLocationId` routing (mehanizam ovisi o DP-02)
- Retry logika (vidi Sekciju 6 — Action Scheduler flow)
- Admin order action: "Pošalji u ERP" (bypass idempotency)
- Admin order meta panel: Apros order ID, sync status, zadnji sync timestamp

**Ovisnosti:** Korak 9, AP-06, AP-09, DP-02  
**Rizik:** KRITIČAN — duplirane narudžbe u ERP-u su poslovni incident  
**Može početi bez Apros sesije?** 🔴 NE (AP-06 bloker)

---

### Korak 13 — Checkout UI — delivery location picker (Faza 5)

**Opis:** Custom checkout polje za odabir dostavne lokacije.

**Što se implementira:**
- WooCommerce checkout field (select/radio)
- Vidljiv samo za B2B korisnike
- Popunjava se iz `_b2b_delivery_locations` user meta
- Odabir se sprema na narudžbu (`_b2b_selected_delivery_location`)
- Validacija: B2B narudžba bez odabira → blocked checkout

**Ovisnosti:** Korak 9, AP-07 (delivery locations format)  
**Rizik:** SREDNJI — checkout promjena zahtijeva Playwright E2E test  
**Može početi bez Apros sesije?** 🔴 NE (AP-07 bloker za format i storage model)

---

### Korak 14 — Auth implementacija (kada AP-09 validira)

**Opis:** Popuniti auth header inject mehanizam iz Koraka 5 konkretnom implementacijom.

**Što se implementira (ovisi o AP-09 odgovoru):**
- API key: `CurlClient::set_auth_header('Bearer ' . $key)`
- OAuth 2.0: token fetch + refresh logika (znatno više posla; vlastita klasa)
- Basic auth: base64 encoding credentials
- Credential storage: `wp-config.php` konstante (`DP_ERP_API_KEY`, `DP_ERP_API_SECRET`)

**Ovisnosti:** Korak 5, AP-09  
**Rizik:** SREDNJI do VISOK (ovisi o auth modelu — OAuth 2.0 je najskuplja opcija)  
**Može početi bez Apros sesije?** 🔴 NE

---

### Korak 15 — Validacija i end-to-end testiranje (Faza 6)

**Opis:** End-to-end validacija s realnim Apros sandbox podacima.

**Što se testira:**
- Product sync: 10k artikala full run; mjerenje duration i memory usage
- B2B eligibility filter: non-B2B artikli ne ulaze; B2B artikli ulaze
- Warehouse stock: provjera per-warehouse meta za 5+ artikala
- Pricing točnost: 5+ partnera s različitim ugovornim uvjetima; Dream Point QA sudjelovanje
- Order export: Apros prima narudžbu, vraća `numberErp`, partner vidi narudžbu
- Retry: simulirati timeout → verificirati AS retry mehanizam
- Playwright E2E: B2B login → cijena prikazana → košarica s PDV-om → checkout s lokacijom → order potvrda

**Ovisnosti:** Faze 1–5 kompletne; Apros sandbox s realnim podacima  
**Rizik:** VISOK — pricing greške nisu vidljive bez realnih partner podataka  
**Može početi bez Apros sesije?** 🔴 NE (sandbox je preduvjet)

---

## 9. Validation Dependencies

Mapiranje svakog implementacijskog koraka na AP-xx i DP-xx validacijske stavke.

| Korak | Opis | AP/DP blokeri | Može početi odmah? |
|-------|------|---------------|-------------------|
| 1 | Fork i namespace migracija | — | ✅ DA |
| 2 | Uklanjanje Jekaa-specifičnog | — | ✅ DA |
| 3 | ProductToImport DTO proširenje | — | ✅ DA |
| 4 | `b2bArticle` filter u AprosProvider | AP-04 (validacija, nije bloker za Fazu 1) | ✅ DA |
| 5 | Auth inject mehanizam (placeholder) | — | ✅ DA |
| 6 | Per-warehouse stock storage | AP-07 (warehouse payload format) | ⚠️ DJELOMIČNO |
| 7 | `_RESPONSE_HASH` verzija reset | — | ✅ DA |
| 8 | Cron konfiguracija za server | — | ✅ DA |
| 9 | Partner sync adapter | AP-03, AP-07, DP-01 | 🔴 NE |
| 10 | Approval webhook endpoint | AP-03 | 🔴 NE |
| 11 | Pricing engine | AP-01, Faza 3 (Korak 9) | 🔴 NE |
| 12 | B2B order export rewrite | AP-06, AP-09, DP-02 | 🔴 NE |
| 13 | Checkout delivery location picker | AP-07, Faza 3 (Korak 9) | 🔴 NE |
| 14 | Auth implementacija | AP-09 | 🔴 NE |
| 15 | E2E validacija | Sve prethodne faze; Apros sandbox | 🔴 NE |

### AP-xx → koraci mapping

| Bloker | Severity | Utječe na korake | Što je potrebno |
|--------|----------|-----------------|-----------------|
| **AP-01** | P0 | 11 (Pricing engine — model) | Payload primjer: `wholesalePrice` semantika za domaćeg + stranog partnera |
| **AP-03** | P1 | 9, 10 (Partner sync, Webhook) | Kompletna lista polja u approval webhook body-u |
| **AP-06** | P0 | 12 (Order export) | B2B order endpoint URL, format, B2B-specifična polja, idempotency |
| **AP-07** | P1 | 6 (Warehouse — verifikacija), 9, 13 (Partner sync, Checkout UI) | Delivery locations format, polja, trigger za ažuriranje |
| **AP-08** | P1 | 9, 10 (`advance_only`, `free_shipping`) | Isporuka flagova i mehanizam ažuriranja za aktivne partnere |
| **AP-09** | P0 | 5 (placeholder), 14 (implementacija) | Auth metoda za B2B API pozive |
| **AP-05** | P2 | 6, 8 (warehouse verifikacija, cron interval) | Delta sync podrška i API rate limits |
| **AP-13** | P2 | 9, 11 (rabat ažuriranje) | Mehanizam ažuriranja rabata za aktivne partnere |
| **DP-01** | P1 | 9 (partner storage model) | sif_kup kardinalitet: 1:1 ili 1:N |
| **DP-02** | P1 | 12 (sales location routing) | Mehanizam mapiranja narudžbe na prodajno mjesto 3 ili 5 |

---

## 10. Ready-To-Implement Scope

### CAN START NOW

Ovi zadaci nemaju blokirajuće pretpostavke. Mogu početi odmah u Fazi 1.

| Zadatak | Korak | Procjena |
|---------|-------|----------|
| Fork baseline plugina iz Jekaa u dp-b2b | 1 | 0.5 dana |
| Namespace i slug migracija | 1 | 0.5 dana |
| Uklanjanje engraving i Jekaa hardcode dijelova | 2 | 0.5 dana |
| Proširenje ProductToImport DTO s B2B poljima | 3 | 0.5 dana |
| `b2bArticle` filter u AprosProvider::map_fields() | 4 | 0.5 dana |
| `wholesalePrice` polje čitanje i storage u DTO | 4 | 0.5 dana |
| Auth header inject mehanizam (placeholder) | 5 | 1 dan |
| Per-warehouse stock storage struktura | 6 | 2 dana |
| Brand → Warehouse mapping admin UI skeleton | 6 | 1 dan |
| `_RESPONSE_HASH` verzija reset | 7 | 0.5 dana |
| Cron konfiguracija za B2B server | 8 | 0.5 dana |
| Monitor plugin kopija s novim slugom | 1 | 0.5 dana |

**Ukupno: ~8 dana rada koji mogu početi odmah.**

---

### BLOCKED BY APROS

Ovi zadaci ne mogu početi dok Apros ne validira odgovarajuće stavke.

| Zadatak | Bloker | Zašto |
|---------|--------|-------|
| Pricing engine implementacija (model odabir) | AP-01 | Konfliktni signali o wholesalePrice semantici — pogrešan model = kompletan rework |
| Auth implementacija | AP-09 | CurlClient pattern ovisi o auth metodi; OAuth 2.0 = znatno više posla |
| B2B order export (B2B-specifična polja) | AP-06 | sif_kup, salesLocationId, deliveryLocationId nevalidirani u order endpoint formatu |
| Partner sync adapter | AP-03, AP-07 | Webhook body format i delivery locations format nepoznati |
| Approval webhook endpoint | AP-03 | Nemoguće implementirati bez poznavanja webhook body-a |
| Checkout delivery location picker | AP-07 | Storage model i UI ovise o broju i formatu lokacija |
| `advance_only` payment restrictions | AP-08 | Isporuka i ažuriranje mehanizam nevalidiran |
| Warehouse verifikacija (korak 6) | AP-07 | Točan warehouse payload format nevalidiran |

---

### BLOCKED BY DREAM POINT

| Zadatak | Bloker | Zašto |
|---------|--------|-------|
| Partner storage model (user meta vs. company CPT) | DP-01 | Scenarij A (1 user) vs. Scenarij B (više korisnika) = fundamentalno različita storage arhitektura |
| Sales location routing u order export-u | DP-02 | Jedini poznati izvor mapiranja je Josip / stari B2B sustav |

### RESOLVED (workshop Lipanj 2026)

| Stavka | Razriješeno |
|--------|-------------|
| MOQ / quantity rules (DP-B01) | Nema MOQ, nema stepova — native WC |
| Payment metoda (DP-A03) | Single virman; deferred izvan WC |
| Stock reservation | Native WC first-order-wins |
| Backorder (AP-12) | Nema naručivanja bez zaliha |

---

### BLOCKED BY PAYLOAD EXAMPLES

Ovi zadaci su arhitekturalno razumljeni ali ne mogu biti finalizirani bez realnih Apros payload primjera.

| Zadatak | Potrebni payload | Zašto |
|---------|-----------------|-------|
| Verifikacija `b2bArticle` + `wholesalePrice` field names | B2B `articleList/get` primjer | Točni field nazivi mogu biti drugačiji od pretpostavki |
| Pricing engine (Model A/B/C odabir) | `articleList/get` + `ugovorni uvjeti` za istog partnera | Bez prikaza obje vrijednosti nemoguće odlučiti |
| Order payload builder finalizacija | B2B order request/response primjer | Obavezna vs. opcionalna polja; `salesLocationId` format |
| Delivery location storage model | Delivery locations endpoint response | Broj i struktura lokacija određuju storage pristup |

---

### Prioritetni slijed Apros sesije

Za maksimalnu implementacijsku korist, Apros sesija mora odgovoriti redom:

1. **(AP-01)** `wholesalePrice` semantika + realni payload → odblokira pricing engine (Korak 11)
2. **(AP-09)** Auth mehanizam → odblokira sve realne API pozive (Korak 14)
3. **(AP-06)** B2B order endpoint detalji → odblokira order export rewrite (Korak 12)
4. **(AP-03)** Approval webhook body → odblokira partner sync i onboarding (Koraci 9, 10)
5. **(AP-07)** Delivery locations format → odblokira checkout UI i warehouse verifikaciju (Koraci 6, 13)

Po završetku prve Apros sesije s odgovorima na AP-01, AP-09 i AP-06: **ukupno ~5 tjedana rada postaje odblokirano**.
