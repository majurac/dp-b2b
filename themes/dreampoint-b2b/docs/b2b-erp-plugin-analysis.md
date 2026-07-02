# Uncle Dev Importer (Apros) — B2B ERP Plugin Analysis

**Datum analize:** 2026-06-08  
**Izvor:** Produkcijsko okruženje — `/home/jekaastore.com/public_html/wp-content/plugins/uncle-dev-importer/`  
**Verzija plugina:** 1.0  
**Monitor verzija:** 2.0

---

## 1. Executive Summary

Uncle Dev Importer (Apros) je WooCommerce plugin koji implementira **CMS-pull ERP integraciju** s Apros ERP sistemom. Plugin preuzima kompletni katalog proizvoda s Apros API-ja, kreira i ažurira WooCommerce proizvode (simple i variable), te šalje narudžbe nazad u ERP pri plaćanju.

**Glavne odgovornosti:**
- Povlačenje kataloga (proizvodi, varijacije, slike, brändovi, atributi) iz Apros API-ja
- Kreiranje i ažuriranje WooCommerce simple i variable proizvoda
- Sinkronizacija cijena i stanja zaliha
- Mapiranje kategorija i brändova na WC taksonomije
- Export narudžbi prema ERP-u pri plaćanju
- Praćenje import sesija s alertingom (companion monitor plugin)

**Procjena iskoristivosti za DP-B2B:** Visoka (~70% koda može se direktno reupotrijebiti ili uz minimalne modifikacije). Osnovna import arhitektura i Apros konekcija su generičke. Specifičnosti Jekaa B2C koje zahtijevaju preinake: order export (hardkodirani engraving ID, nema partner ID-a), nedostatak B2B pricing polja, nema customer sync komponente.

---

## 2. Plugin Architecture

### Struktura fajlova

```
uncle-dev-importer/
├── uncle-dev-importer-apros.php    ← bootstrap / plugin entry
├── hooks.php                       ← frontend hooks (galerija varijacija, Redis cache)
├── order.php                       ← order export prema ERP-u
├── src/
│   ├── Importer.php                ← core orchestrator (import pipeline)
│   ├── ImporterWPClient.php        ← WP-CLI interface
│   ├── Client/
│   │   └── CurlClient.php          ← raw HTTP client
│   ├── Models/
│   │   ├── ProductToImport.php     ← DTO za product data
│   │   └── ProductAttribute.php    ← DTO za attribute
│   └── Providers/
│       ├── BaseProvider.php        ← abstract provider (credentials, endpoint)
│       └── AprosProvider.php       ← Apros API fetch + field mapping
├── acf-json/                       ← ACF field group definitions
│   ├── group_676e71bd5b2b0.json    ← Importer settings (endpoint, credentials)
│   ├── group_67a1cfdf163b5.json    ← Product lock fields (lock_price, lock_name)
│   ├── group_67cc46137fa65.json    ← Category/Brand remote ID mapping
│   └── group_68cc4a812f3fe.json    ← Product variation images (ACF repeater)
├── composer.json                   ← PSR-4: UncleDev\Importer\ → src/
└── vendor/                         ← Composer autoloader (no external dependencies)

uncle-dev-importer-monitor.php      ← companion: session tracking, alerting, dashboard
erp-payment-shipping-custom-meta/   ← companion: ERP ID mapping za shipping/payment
```

### Klase i odgovornosti

```
uncle-dev-importer-apros.php
  ├── Importer::init()                   ← init hook
  ├── WP_CLI::add_command('importer', ImporterWPClient)
  └── include hooks.php, order.php

Importer (src/Importer.php)
  ├── acf_setup()                        ← registrira ACF JSON putanje
  ├── import()                           ← main pipeline orchestrator
  ├── get_providers()                    ← čita konfiguraciju iz ACF options
  ├── get_categories()                   ← mapira WC kategorije na remote IDs
  ├── import_remote_categories()         ← kreira WC kategorije iz ERP-a
  ├── import_image()                     ← preuzima slike (URL/base64), resize, WP media
  ├── assign_taxonomy_term_to_product()  ← brand/category assignment
  ├── get_product_id_by_erp_id()         ← lookup po _erp_id meta
  ├── detect_varying_attributes()        ← automatska detekcija variation atributa
  ├── update_variation_images()          ← ACF repeater za variation slike
  ├── trash_missing_products()           ← soft-delete proizvoda kojih nema u ERP-u
  └── normalize_label()                  ← sanitize_title za attribute slugove

ImporterWPClient (src/ImporterWPClient.php)
  ├── import()                           ← WP-CLI entry, performance optimizacije
  └── clear_empty_images()               ← WP-CLI maintenance alat

AprosProvider extends BaseProvider (src/Providers/)
  ├── fetch()                            ← Generator: fetch svih 9 endpointa, mapiranje
  └── map_fields()                       ← raw API array → ProductToImport DTO
```

### Admin alati

- ACF Options page: `uncle-dev-importer-apros` — konfiguracija providera (endpoint, credentials)
- Per-product ACF fields: `lock_price`, `lock_name` — sprječavaju ERP override
- Admin notice: upozorenje ako import nije pokrenut X sati
- Importer Monitor dashboard: historija sesija, price/stock/status promjene, errori

---

## 3. Endpoint Inventory

### Import endpointi (sve GET, CurlClient)

Svi endpointi su relativni na `base_endpoint` koji se konfigurira u ACF settings.

| Endpoint | Method | Svrha | Frekvencija | Status |
|---|---|---|---|---|
| `/articleList/get` | GET | Kompletan katalog proizvoda | Svaki import | CONFIRMED BY CODE |
| `/articleVariationList/get` | GET | Sve varijacije svih proizvoda | Svaki import | CONFIRMED BY CODE |
| `/articleImageList/get` | GET | Slike svih proizvoda (artikala) | Svaki import | CONFIRMED BY CODE |
| `/articleVariationImageList/get` | GET | Slike svih varijacija | Svaki import | CONFIRMED BY CODE |
| `/brandList/get` | GET | Lista brändova | Svaki import | CONFIRMED BY CODE |
| `/attributeList/get` | GET | Lista tipova atributa | Svaki import | CONFIRMED BY CODE |
| `/attributeItemList/get` | GET | Lista vrijednosti atributa | Svaki import | CONFIRMED BY CODE |
| `/articleAttributeList/get` | GET | Mapiranje artikal → atribut | Svaki import | CONFIRMED BY CODE |
| `/articleVariationAttributeList/get` | GET | Mapiranje varijacija → atribut | Svaki import | CONFIRMED BY CODE |

Ukupno: **9 GET endpointa** pozivanih sekvencijalno u svakom import runu. Nema paginacije — svi podaci se vraćaju u jednom responsu.

### Export endpoint (order)

| Endpoint | Method | Svrha | Trigger | Status |
|---|---|---|---|---|
| `/order/create` | POST | Slanje narudžbe u ERP | WC order statusna promjena / ručno | CONFIRMED BY CODE |

**Napomena:** Order endpoint je konfiguriran putem WP option `options_uncle_dev_importer_0_base_endpoint` — ne iz ACF repeater polja za provider. Ovo je odvojena konfiguracija od import endpointa.

### Konzumirani payload fields

**articleList (product):**
- `articleId` — ERP identifikator, PostMeta `_erp_id`
- `code` — šifra artikla, PostMeta `_ARTICLE_CODE`
- `title` — naziv
- `description` — opis
- `barcode` — EAN/GTIN
- `priceWithVat` — cijena s PDV-om
- `priceWithoutVat` — cijena bez PDV-a (fallback)
- `salePriceWithVat` — akcijska cijena
- `saleDateStart`, `saleDateEnd`, `saleTitle` — akcijski datumi i naziv
- `stock` — stanje zaliha
- `visible` — vidljivost (publish/draft)
- `classification` — kategorija (string ID)
- `brandId` — brand identifikator
- `virtualArticle` — boolean: ima li varijacije (određuje Variable vs Simple)
- `images[]` — niz image objekata s `Url` poljem

**articleVariationList (variation):**
- `variationId` — ERP ID varijacije
- `articleId` — foreign key na roditelja
- Ista polja kao produkt (title, price, stock, visible, barcode...)

**brandList:**
- `brandId`, `title`

**attributeList:**
- `attributeId`, `title`

**attributeItemList:**
- `attributeId`, `attributeItemId`, `title`

**articleAttributeList / articleVariationAttributeList:**
- `articleId`/`variationId`, `attributeId`, `attributeItemId`
- Atribut ID 154 ("Kolekcija") se **eksplicitno preskače** — CONFIRMED BY CODE

---

## 4. Authentication Model

**✅ RESOLVED 2026-07-02:** Apros je direktno potvrdio auth mehanizam za B2B API pristup: **API Key**, isti pristup kao postojeća Jekaa B2C integracija.

**Raniji KRITIČNI NALAZ (i dalje relevantan za razumijevanje B2C koda):** Trenutna B2C implementacija ne šalje nikakve auth credentiale prema Apros API-ju, unatoč potvrđenom API Key modelu.

### Product import

- `CurlClient::request()` šalje samo `Content-Type: application/x-www-form-urlencoded` i `Accept: application/json`
- **Nema Authorization headera**, nema API key-a, nema Basic Auth
- `username` i `password` su polja u ACF konfiguraciji i `BaseProvider::get_username()/get_password()` metodama, ali `AprosProvider::fetch()` **nikad ne poziva te metode** niti ih prosleđuje `CurlClient`-u
- Ovo je vjerojatno previd u B2C implementaciji, ili je B2C API dodatno zaštićen na mrežnoj razini (IP whitelist) pa API Key nije bio striktno enforced — nije razjašnjeno, ali ne mijenja B2B zahtjev

**STATUS:** RESOLVED — B2B mora implementirati API Key header inject u `CurlClient`; isti previd (nedostatak stvarnog slanja headera) mora biti izbjegnut

### Order export

- `wp_remote_post()` bez autentikacije (samo `Content-Type: application/json`)
- Isti pattern kao import — B2B order export mora dodati API Key header

### Credential storage

- Username/password u ACF options (WP options table) — za B2B, API Key treba čuvati kao `wp-config.php` konstantu (`DP_ERP_API_KEY`), ne u ACF/DB, u skladu s projektnim pravilom o secrets
- Base endpoint URL u ACF options

**Za DP-B2B:** Implementirati `CurlClient::set_auth_header('ApiKey ' . DP_ERP_API_KEY)` pattern (Korak 5 placeholder → Korak 14 finalizacija u `docs/b2b-erp-migration-plan.md`). Preostalo, nije bloker: odvojeni credentials za read/write operacije, dodatni IP whitelist zahtjev.

---

## 5. Product Import Architecture

### Product Identification Model

| Polj | ERP izvor | WC storage |
|---|---|---|
| ERP ID | `articleId` / `variationId` | `_erp_id` post meta |
| Provider | plugin class name | `_erp_provider` post meta |
| SKU (parent) | `"P-" + articleId` | WC SKU |
| SKU (variation) | `variationId` (bez prefiksa) | WC variation SKU |
| EAN/GTIN | `barcode` | WC `global_unique_id` |
| Artikel šifra | `code` | `_ARTICLE_CODE` post meta |

**Lookup u bazi:** `get_posts()` sa `meta_key => '_erp_id'` — nema custom DB indeksa, može biti sporo na velikim katalozima.

### Parent/Child Handling

- `virtualArticle: true` → WC Variable Product
- `virtualArticle: false` → WC Simple Product
- Varijacije se grupiraju po `articleId`
- Automatska detekcija "varying attributes" (atributi koji se razlikuju među varijacijama)
- Duplikati varijacija (isti atributni set) se filtriraju s logiranjem

### Attribute Mapping

- Atributi se automatski kreiraju kao WC global attribute taxonomies ako ne postoje
- Naziv atributa normaliziran putem `sanitize_title()` za slug
- Atribut ID 154 ("Kolekcija") se preskače — razlog nije dokumentiran u kodu

### Category & Brand Mapping

- WC kategorija → `remote_category_id` ACF repeater (jedan WC term može primiti više remote ID-a)
- `product_brand` taxonomija za brändove (isto mapiranje)
- Kategorije se mogu auto-kreirati ako je `fetch_categories` opcija uključena u provider config

### Image Handling

- Podržani formati: URL, raw base64, inline data-URL
- Auto-resize: max 1000×1000px via GD
- PNG bez alpha kanala → konvertira se u JPEG (radi uštede prostora)
- Hash-based deduplication: `md5(image_source)` → preskače re-preuzimanje nepromijenjenih slika
- Variation images: Poseban ACF repeater `product_variation_images` s hash tracking-om

### Change Detection

- `_RESPONSE_HASH` = `md5(serialize($product) + business_date + 'v20260401-13')`
- Ako hash odgovara → skip (nema promjena)
- Business date = tekući dan (3:00 AM cutoff za "novi dan")
- **Efikasnost:** Izbjegava nepotreban WC save na nepromijenjen katalog

---

## 5a. Variable vs Simple — Impact Analysis

**Pitanje:** Koliko je teško prebaciti s Variable Products na Individual Simple Products?

**Trenutna arhitektura je čvrsto vezana za variable model:**

1. `virtualArticle` flag u ERP-u određuje tip — ovo se ne može promijeniti bez API promjene
2. Importer.php ima dvije odvojene code paths: simple (direktan save) i variable (nova klasa, varijacije, sync)
3. Variation images sustav (ACF repeater + hooks.php filter) pretpostavlja parent-child odnos
4. Atributi za varijacije se kreiraju kao `set_variation(true)` WC atributi

**Promjene potrebne za all-simple model:**
- Svaka varijacija postaje samostalni simple produkt
- `_erp_id` bi bio `variationId` (ne `articleId`)
- Slike bi bile direktno na svakom produktu, ne putem ACF repeater
- Atributi bi ostali ali bez variation purpose-a
- `detect_varying_attributes()`, `build_child_attr_map()`, `get_existing_variations_index()` metode bi postale suvišne
- Kategorija/brand assignment bi ostao isti
- Order export ne bi trebao promjene

**Procjena težine:** Srednje-teška (3-5 dana rada). Veći dio Importer.php-a bi bio nepromijenjen. Refaktor bi bio lokaliziran na import loop u `import()` metodi i AprosProvider mapiranju.

**Preporuka za DP-B2B:** Zadržati variable model ako ERP podržava `virtualArticle` distinkciju. Simple model je pogodniji samo ako B2B katalog nema varijacije ili ako svaka "varijacija" treba biti zasebno naručljiv artikl s vlastitim kataloškim listom.

---

## 6. Pricing Architecture

### ERP Price Fields

| ERP polje | Svrha | WC storage |
|---|---|---|
| `priceWithVat` | Osnovna cijena s PDV-om | `regular_price` |
| `priceWithoutVat` | Fallback ako nema `priceWithVat` | `regular_price` |
| `salePriceWithVat` | Akcijska cijena | `sale_price`, `price` |
| `saleDateStart` | Početak akcije | `_SALE_DATE_START` meta |
| `saleDateEnd` | Kraj akcije | `_SALE_DATE_END` meta |
| `saleTitle` | Naziv akcije | `_SALE_TITLE` meta |

**NAPOMENA:** WooCommerce `sale_price` / `date_on_sale_from` / `date_on_sale_to` WC native polja se **ne koriste** za sale datume — čuvaju se samo kao custom meta. Ovo znači da WC ne aktivira/deaktivira akciju automatski po datumu.

### Per-Product Lock

ACF boolean `lock_price` na svakom produktu — ako je true, ERP ne može promijeniti cijenu. Mehanizam je na razini jednog produkta, bez grupnog lockanja.

### B2B Pricing Readiness

**Trenutna arhitektura podržava samo jednu cijenu.** Nema polja za:
- Tier cijene (količinski popusti)
- Kupac-specifične cijene (po `sif_kup`)
- Role-based cijenike

Za DP-B2B B2B pricing, `ProductToImport` DTO mora biti proširen s dodatnim price array-em, a Importer mora znati koji cjenik primijeni za koji kupac/role. Ovo je **novi development**, ne modifikacija postojećeg koda.

---

## 7. Stock & Warehouse Architecture

| Aspekt | Implementacija |
|---|---|
| ERP field | `stock` (integer, ukupno stanje) |
| WC storage | `stock_quantity` + `manage_stock = true` |
| Parent product | `manage_stock = false` (stock na razini varijacije) |
| Status mapping | `stock > 0` → `instock`; `stock ≤ 0` → `outofstock` |
| Multi-warehouse | Nije implementirano |
| Rezervacije | Nisu implementirane |

**Sync model:** Svaki import povlači trenutno stanje. Nema delta — uvijek se piše vrijednost iz ERP-a (osim ako je stock isti).

**Multi-warehouse readiness:** Nema. Jedan `stock` field za cijeli produkt. Za DP-B2B, ako Apros šalje warehouse breakdown, potreban je novi field mapping i logika za aggregaciju ili selekciju skladišta.

---

## 8. Customer & Partner Architecture

**NALAZ: Nema customer/partner sync u ovom pluginu.**

Plugin ne implementira:
- Sinkronizaciju kupaca s ERP-om
- `sif_kup` identifikator
- Grupe kupaca
- Kupac-specifične cijene
- Odobravanje B2B računa

**Jedina veza između kupca i ERP-a** je kroz order export:
- `billingTitle`, `billingAddress`, `billingPhone`, `billingEmail` — podaci iz WC order-a
- `billingOib` — prazno polje u payloadu, nigdje nije popunjeno
- Nema ERP customer ID u order payloadu

**Implikacija za DP-B2B:** Customer/partner sync je nova komponenta koja ne postoji u B2C implementaciji. Potrebno razjasniti koje Apros endpointe podržava za customer management (partnerList, customerPrice, itd.).

---

## 9. Order Export Architecture

### Trigger uvjeti

| Hook | Uvjet | Napomena |
|---|---|---|
| `woocommerce_order_status_processing` | Automatski | Svaka narudžba u processing statusu |
| `woocommerce_thankyou` | COD/BACS/cheque metode plaćanja | Redundantni trigger za gotovinska plaćanja |
| `woocommerce_payment_complete` | Automatski | Online plaćanja |
| `woocommerce_order_action_sync_to_erp` | Ručno (admin) | Bypass idempotency check |

### Idempotency

- Meta key `_erp_sync_status` na narudžbi
- Automatski sync: ako meta postoji → preskači
- Ručni sync: uvijek šalje (bypass)
- **Nema retry mehanizma** — ako send ne uspije, admin mora ručno resync-ati

### Order Payload

```json
{
  "number": 12345,
  "date": "2026-06-08",
  "paymentTypeId": 2,
  "shippingMethodId": 2,
  "billingTitle": "Ime Prezime",
  "billingAddress": "Ulica 1",
  "billingZipCode": "10000",
  "billingCity": "Zagreb",
  "billingCountry": "HR",
  "billingPhone": "0912345678",
  "billingEmail": "kupac@example.com",
  "shippingTitle": "...",
  "shippingAddress": "...",
  "shippingZipCode": "...",
  "shippingCity": "...",
  "shippingCountry": "...",
  "shippingPhone": "...",
  "shippingEmail": "...",
  "shippingNote": "...",
  "billingOib": "",
  "items": [
    {
      "position": 1,
      "productId": 55620,
      "code": "ART-001",
      "title": "Naziv proizvoda",
      "note": "",
      "priceWithoutVat": 80.00,
      "vatRate": 25.0,
      "priceWithVat": 100.00,
      "discountPercentage": 0,
      "quantity": 2,
      "amount": 200.00
    }
  ]
}
```

### Payment/Shipping ID Mapping

- Preferira `wc_erp_id_get_payment()` / `wc_erp_id_get_shipping()` iz companion plugina
- Fallback na hardkodirane string mape (`my_get_erp_payment_map()`, `my_get_erp_shipping_map()`)
- Shipping ID format: `"metoda_id:product_id"` — shipping se dodaje kao posebna stavka u items

### Hardkodirani ERP artikli (Jekaa-specific)

- **Graviranje (productId=56399):** Personalizacija nakita je Jekaa-specifičan feature. Cijena gravure se čita iz `_personalization_price` meta. **Ovo mora biti uklonjen za DP-B2B.**
- `billingOib: ""` — prazno polje, za B2B trebat će OIB/VAT ID

### ERP response format

```json
[{ "result": "Error", "message": "..." }]    // greška
[{ "numberErp": "12345" }]                  // uspjeh, ERP broj narudžbe
```

---

## 10. Cron & Synchronization Model

### Arhitektura

**Model: CMS Pull (WooCommerce povlači podatke iz Apros-a)**

Apros ERP ne šalje push notifikacije. Nema webhooks. WP-CLI komanda `wp importer import` pokreće kompletni pull ciklus.

### Raspored

**System cron (user jekaa9701):**
```
45 * * * *  wp importer import > /home/jekaastore.com/logs/wpcli/run_YYYYMMDD_HHMMSS.log 2>&1
```
- **Frekvencija: svaki sat, na minuti 45**
- Logovi čuvani 72 sesija (rotacija)
- Svaki run je **full sync** — nema delta/incremental importa

**WP-Cron (svake 5 minuta):**
```
*/5 * * * *  wp cron event run --due-now
```
- Za monitor alerting (`udim_check_import_gap`) i opće WP-Cron operacije

### Full vs Delta

- **Full sync** svaki run
- Optimizacija: `_RESPONSE_HASH` — ako hash nije promijenjen, produkt se preskače bez WC save-a
- Nema Apros endpointa za "changed since timestamp" — potvrda potrebna

### Action Scheduler

**Nije korišten.** Import se pokreće direktno putem WP-CLI, ne kroz Action Scheduler. Ovo znači:
- Nema retry logike
- Nema queue management
- Nema web-based triggera — samo CLI
- Za web-based (admin button) trigger možda treba dodati AS

---

## 11. Reusability Assessment

| Komponenta | Procjena | Justifikacija |
|---|---|---|
| `CurlClient.php` | ✅ REUSE AS-IS | Generički HTTP client, nema B2C specifičnosti |
| `BaseProvider.php` | ✅ REUSE AS-IS | Apstraktna klasa, endpoint/credentials logika |
| `ProductToImport.php` | 🔧 REUSE WITH MODIFICATIONS | Dodati polja za B2B price tiers, partner ID |
| `ProductAttribute.php` | ✅ REUSE AS-IS | DTO bez B2C logike |
| `AprosProvider.php` | 🔧 REUSE WITH MODIFICATIONS | `map_fields()` mora mapirati B2B polja; provjera auth |
| `Importer.php` | 🔧 REUSE WITH MODIFICATIONS | ~80% logike je generičko; B2B specifičnosti: partner lookup, B2B pricing |
| `ImporterWPClient.php` | ✅ REUSE AS-IS | Performance optimizacije su dobre, generičke |
| `hooks.php` | ✅ REUSE AS-IS | Variation image display generički pattern |
| `order.php` | 🔴 REWRITE | Hardkodirani engraving ID, nema partner ID, billingOib prazno, nema B2B retry |
| Monitor plugin | ✅ REUSE AS-IS | Monitoring infrastruktura je generička |
| ERP payment/shipping plugin | 🔧 REUSE WITH MODIFICATIONS | Proširi za B2B-specifične metode plaćanja |
| ACF group: importer settings | ✅ REUSE AS-IS | Isti konfiguracijski pattern |
| ACF group: product locks | ✅ REUSE AS-IS | lock_price / lock_name su generički |
| ACF group: category mapping | ✅ REUSE AS-IS | Remote ID mapiranje je generičko |
| ACF group: variation images | ✅ REUSE AS-IS | Variation image pattern je generički |

### Najveći rizici pri reupotrebi

1. **Auth implementacija:** Auth model potvrđen (API Key, 2026-07-02) — preostaje implementacija header inject mehanizma u `CurlClient`, ne validacija modela
2. **Order export:** Kompletna nova implementacija s partner ID-em i bez Jekaa-specifičnosti
3. **Pricing:** B2B pricing nije u postojećoj arhitekturi — nova komponenta
4. **_RESPONSE_HASH versioning:** Hash uključuje hardkodiranu verziju `'v20260401-13'` — pri prvom deployu na B2B ovo će uzrokovati re-import svih proizvoda (što je željeno ponašanje)

---

## 12. Open Questions

Pitanja koja ne mogu biti odgovorena analizom koda:

1. ~~**Auth model:**~~ ✅ RESOLVED 2026-07-02 — API Key, isti pristup kao Jekaa B2C. Preostalo pitanje (nije bloker): zašto B2C CurlClient ne šalje username/password unatoč potvrđenom modelu — vrijedi razjasniti radi izbjegavanja istog previda u B2B.

2. **B2B price fields:** Koje field-ove šalje Apros za B2B cjenike? Postoji li `customerPrice`, `priceList`, `sif_kup`-based pricing u API responsu?

3. **Customer/Partner sync:** Postoji li Apros endpoint za kupce/partnere? Što je `sif_kup` u Apros kontekstu? Treba li DP-B2B sinkronizirati partner account iz ERP-a?

4. **Partner ID u narudžbi:** Treba li B2B order export sadržavati ERP partner ID? Koji field to u Apros order payloadu?

5. **OIB/VAT ID:** `billingOib` polje je u payloadu ali uvijek prazno. Za B2B je OIB kritičan — odakle se puni (WC customer meta, checkout field)?

6. **Atribut 154 — "Kolekcija":** Zašto je eksplicitno preskočen? Je li to Jekaa-specifičan atribut koji se ne smije pojaviti kao WC attribute? Vrijedi li ista logika za B2B?

7. **Delta import:** Postoji li Apros endpoint koji vraća samo izmijenjene artikle od zadanog timestampa? Ovo bi drastično ubrzalo sync na velikom B2B katalogu.

8. **Push webhooks:** Podržava li Apros B2B ERP push webhooks (stock promjene, cijena)? Ili je isključivo pull model?

9. **Shipping product ID:** Shipping se dodaje kao order item s `productId = shipping_method_product_id`. Koji je Apros ERP ID za B2B shipping metode (GLS, DPD, osobni preuzimanje)?

10. **Sync frekvencija za DP-B2B:** Je li hourly sync primjeren za B2B? Treba li veća frekvencija za stock (kritično za B2B narudžbe)?

---

## Appendix: Meta Polja koja Plugin Čuva

| Meta Key | Vrijednost | Svrha |
|---|---|---|
| `_erp_id` | articleId (integer) | Primarni ERP identifikator |
| `_erp_provider` | "Apros" | Provider name za multi-provider support |
| `_erp_barcode` | barcode string | EAN/barcode iz ERP-a |
| `_erp_images` | serialized array | Image hash tracking za deduplication |
| `_ARTICLE_ID` | articleId / variationId | Kopija na order line item i produkt |
| `_ARTICLE_CODE` | code string | ERP šifra artikla |
| `_SALE_DATE_START` | date string | Početak akcije (nije WC native) |
| `_SALE_DATE_END` | date string | Kraj akcije (nije WC native) |
| `_SALE_TITLE` | string | Naziv akcije |
| `_RESPONSE_HASH` | md5 hash | Change detection |
| `_erp_sync_status` | string | Order sync status (na order post) |
| `_ime_varijacije` | string | Naziv varijacije (custom) |
