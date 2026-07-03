# DP-B2B ERP Adaptation Blueprint

**Datum:** 2026-06-08  
**Baseline plugin:** Uncle Dev Importer (Apros) — `/home/jekaastore.com/public_html/wp-content/plugins/uncle-dev-importer/`  
**Companion analiza:** `docs/b2b-erp-plugin-analysis.md`  
**Discovery dokumentacija:** `docs/erp-discovery-findings.md`, `docs/erp-validation-checklist.md`

> Ovaj dokument je arhitekturalni blueprint, ne implementacijski plan s kodom.  
> Implementacija ne može početi dok blokeri u Sekciji 9 nisu razriješeni.  
> Evidencijska hijerarhija: produkcijski kod → produkcijsko ponašanje → discovery dokumentacija → pretpostavke.

---

## 1. Executive Summary

### Što se može reupotrijebiti

Apros konektivna infrastruktura, pull sinkronizacijska arhitektura i product import pipeline su **direktno prenosivi**. Produkcijski Jekaa plugin sadrži robusnu implementaciju koja pokriva: HTTP klijent, provider abstraction, product/variation kreiranje, attribute taxonomy management, image preuzimanje i deduplication, category/brand mapiranje, monitoring i cron egzekuciju. Ove komponente zahtijevaju samo proširenja, ne zamjene.

### Što zahtijeva modifikaciju

**Product import adapter** mora biti proširen za dva nova Apros polja (`b2bArticle`, `wholesalePrice`) i prošireni warehouse stock model (4 skladišta umjesto jednog ukupnog). **Cron arhitektura** mora biti skalirana za 10.000 artikala s preferiranim delta syncronizacijom. **ACF konfiguracija** mora biti proširena za B2B-specifične product metapolja.

### Što zahtijeva redesign / novu implementaciju

**Order export** mora biti u potpunosti renapisan za B2B: uključuje `sif_kup`, dostavnu lokaciju, OIB, prodajno mjesto routing i Action Scheduler umjesto inline sinkronog poziva. **Pricing engine** je nova komponenta bez presedana u B2C pluginu — arhitektura ovisi o AP-01 validaciji. **Customer/partner sync** je nova komponenta: B2C plugin nema customer sync; B2B zahtijeva vezivanje WP korisnika na `sif_kup`, ugovornih uvjeta i dostavnih lokacija.

---

## 2. Reuse Inventory

### Produkcijska klasifikacija

| Komponenta | Klasifikacija | Justifikacija |
|---|---|---|
| `CurlClient.php` | ✅ REUSE AS-IS | Generički HTTP klijent, bez B2C specifičnosti; samo dodati auth header inject |
| `BaseProvider.php` | ✅ REUSE AS-IS | Apstraktna baza s endpoint/credential upravljanjem |
| `ProductToImport.php` | 🔧 REUSE WITH MODIFICATIONS | Dodati: `b2b_eligible`, `wholesale_price`, `stock_per_warehouse[]` |
| `ProductAttribute.php` | ✅ REUSE AS-IS | DTO bez B2C logike |
| `AprosProvider::fetch()` | 🔧 REUSE WITH MODIFICATIONS | Dodati: partner endpoint, delivery location endpoint; prilagoditi field mapping |
| `AprosProvider::map_fields()` | 🔧 REUSE WITH MODIFICATIONS | Mapirati nova B2B polja (`b2bArticle`, `wholesalePrice`, warehouse stock) |
| `Importer.php` core loop | 🔧 REUSE WITH MODIFICATIONS | ~75% logike generično; dodati: B2B filter, per-warehouse stock, pricing storage |
| `Importer::import_image()` | ✅ REUSE AS-IS | Image pipeline je generički (URL, base64, resize, dedup) |
| `Importer::trash_missing_products()` | ✅ REUSE AS-IS | Soft-delete logika je generička |
| `Importer::detect_varying_attributes()` | ✅ REUSE AS-IS | Auto-detekcija varijiranih atributa — nema B2C specifičnosti |
| `Importer::update_variation_images()` | ✅ REUSE AS-IS | ACF variation image pipeline — generički |
| `ImporterWPClient.php` | ✅ REUSE AS-IS | WP-CLI interface, performance optimizacije su dobre |
| `hooks.php` | ✅ REUSE AS-IS | Variation image display, Redis cache sloj — generički |
| `order.php` | 🔴 REWRITE | Hardkodirani Jekaa engraving ID (56399), nema `sif_kup`, prazno OIB, nema Action Scheduler, nema prodajno mjesto routing |
| Monitor plugin | ✅ REUSE AS-IS | Session tracking, alerting dashboard — generička infrastruktura |
| ERP payment/shipping plugin | 🔧 REUSE WITH MODIFICATIONS | Proširiti za B2B-specifične metode plaćanja; zadržati admin mapping UI |
| ACF: importer settings | ✅ REUSE AS-IS | Provider config pattern identičan |
| ACF: product lock fields | ✅ REUSE AS-IS | `lock_price`, `lock_name` — generički override mehanizam |
| ACF: category mapping | ✅ REUSE AS-IS | Remote ID mapiranje — generički pattern |
| ACF: variation images | ✅ REUSE AS-IS | Variation image repeater — generički pattern |

### Nova komponenta inventar

| Komponenta | Svrha |
|---|---|
| Partner sync adapter | Preuzimanje i pohranjivanje partner data (`sif_kup`, ugovorni uvjeti, dostavne lokacije) |
| Pricing engine | Per-partner cijene: storage, runtime resolucija, cache invalidacija |
| B2B order export | Rewritten order.php s `sif_kup`, lokacijom, OIB, prodajnim mjestom, Action Scheduler retry |
| Partner REST endpoint | Inbound: odobrenje/deaktivacija partnera od Apros-a |
| B2B product filter | Per-sync `b2bArticle` filtracija — import samo B2B-eligible artikala |

---

## 3. Product Architecture

### Trenutni model (produkcija — Jekaa)

- Simple produkt: `virtualArticle = false`
- Variable produkt: `virtualArticle = true`, djeca su WC_Product_Variation
- Identifikacija: `articleId` / `variationId` → `_erp_id` meta
- SKU format: `"P-" + articleId` za parent; `variationId` bez prefiksa za varijacije
- Jedan ukupni stock po varijaciji, nema warehouse breakdown

### Procjena za B2B katalog

**B2B katalog: ~10.000 artikala s varijacijama.**

| Aspekt | Postojeći model | B2B zahtjev | Promjena |
|---|---|---|---|
| Variable/simple split | `virtualArticle` flag | Isti flag, isti format | Nema (NC-02 potvrđuje isti endpoint) |
| B2B eligibilnost | Nema filter | `b2bArticle` flag → skip non-B2B | Novi filter u `map_fields()` |
| Product identifikator | `articleId` + SKU format | Identičan | Nema |
| Stock model | Jedan `stock` field | 4 skladišta, po warehouse | Novo: `stock_per_warehouse` mapping |
| Cijena | `priceWithVat` | `wholesalePrice` + pricing engine | Novo: proširena pricing logika |

### Prednosti zadržavanja variable product modela

- Direktna kompatibilnost s postojećim Apros `articleVariationList/get` endpointom
- Inventory management per-varijacija (kritično za B2B narudžbe)
- Variation attributes vidljivi na PDP — bitan za B2B decision making
- Nema rearchitekture — kod se proširuje, ne zamjenjuje

### Nedostaci

- Variable produkt + 10k artikala = potencijalni performance pritisak pri sync-u
- `wc_get_product()` u petljama je već identificiran kao rizik u CLAUDE.md (b2b section)
- Variation image sustav (ACF repeater) je dio B2C arhitekture i prenosi se — potrebna provjera skalabilnosti

### Warehouse visibility implikacija

4 skladišta (1, 3, 4, 5) s per-brand matičnim skladištem nije standard WC model. Vidi Sekciju 6 za detaljan warehouse dizajn.

### Preporuka

**Zadržati variable product model.** Proširiti `ProductToImport` s warehouse stock mapom. Dodati `b2bArticle` filtar na ulazu sync-a. Prilagoditi SKU format za eventualni overlap između Jekaa i B2B (prefiks promjena ili namespace separacija).

**Migracija nije potrebna** — B2B je novi WP install, nema existing products koji se migriraju.

---

## 4. Customer / Partner Architecture

### B2B Partner Model

Apros identificira poslovnog partnera putem `sif_kup` — jedinstveni Apros interni identifikator. B2B CMS mora pohraniti i održavati vezu između WP korisnika i Apros partnera.

### sif_kup ownership

**`sif_kup` je u vlasništvu Apros-a.** WC čuva kopiju kao user meta. Apros je izvor istine; WC nikad ne generira niti modificira `sif_kup` vrijednost.

**ASSUMPTION:** `sif_kup` je 1:1 s WP korisnikom. Ako je 1:N (više korisnika dijeli jednu kompaniju), storage model mora biti na razini company/org entiteta, ne user. Ovo je nevalidirani V-06 — vidi Sekciju 9.

### User-to-Company model (1:1 pretpostavka)

```
WP User
  ├── _sif_kup                    (string, Apros partner ID)
  ├── _b2b_status                 (enum: pending | approved | suspended)
  ├── _b2b_tax_profile            (enum: domestic | foreign | tax_exempt)
  ├── _b2b_oib                    (string, OIB/VAT ID)
  ├── _b2b_company_name           (string)
  ├── _b2b_advance_only           (bool, samo avansno plaćanje)
  ├── _b2b_free_shipping          (bool)
  ├── _b2b_approved_at            (datetime)
  └── _b2b_delivery_locations     (array, JSON — vidi Sekciju 6)
```

**ASSUMPTION:** Pricing meta je na razini korisnika — vidi Sekciju 5.

### Approval Lifecycle

**✅ AŽURIRANO 2026-07-02 — nema webhooka, polling/import model potvrđen od Apros-a:**

```
[1] Kupac popunjava web formu → WP kreira pending WP User
[2] Email notifikacija → Dream Point admin
[3] Dream Point ručno otvara partnera u Apros-u
[4] Apros postavlja B2B atribute (B2B KUPAC = DA, B2B EMAIL)
[5] Partner se pojavljuje na partner list endpointu (nema signala prema WP)
[6] WP cron-based polling job periodično provjerava partner list endpoint,
    detektira nove/promijenjene B2B KUPAC = DA zapise
[7] Job povlači partner data (sif_kup, ugovorni uvjeti, lokacije) za novootkrivenog partnera
[8] WP korisnik dobiva B2B rolu, pristup je aktivan
```

**Raniji koraci [5]-[6] (webhook `POST /wp-json/dreampoint-b2b/v1/approve-partner`) se NE implementiraju u tom obliku.** Apros je direktno potvrdio (2026-07-02) da approval webhook ne postoji — vidi AP-03 u `docs/apros-question-resolution-matrix.md` i Sekciju 9 ovog dokumenta (AP-03 → RECLASSIFIED).

**Implikacija za implementaciju:** Korak 10 u `docs/b2b-erp-migration-plan.md` ("Approval webhook endpoint") se redizajnira kao cron-based partner sync job — REST endpoint receiver otpada.

### Partner Data Sync

Apros šalje tri tipa partner podataka (NC-03):
1. **Lista partnera** — `sif_kup`, naziv, pravni oblik, B2B status
2. **Lista primatelja/dostavnih lokacija** (`partnerDeliveryLocationList`) — per partner, s ugovornim uvjetima i cjenicima

**Trigger za sync:** **Periodic polling/import cron** (frekvencija nevalidirana — nije bloker, operativna optimizacija). Webhook trigger otpada u potpunosti.

### Deaktivacija

**ASSUMPTION (i dalje nevalidirano):** Apros ne šalje deaktivacijski signal — konzistentno je s potvrđenim polling/import modelom (nema ikakvog webhooka od Apros-a). Default model: ručna deaktivacija od admin-a, ili detekcija promjene statusa u istom polling job-u koji provjerava `B2B KUPAC` vrijednost.

### Implikacija za sif_kup 1:N scenarij

Ako `sif_kup` može imati više WP korisnika (npr. više zaposlenika iste firme):
- Storage se pomiče s user meta na custom post type `dp_b2b_company`
- WP User → Company relacija putem user meta `_dp_company_id`
- Pricing i ugovorni uvjeti su na Company razini
- Delivery locations su na Company razini
- Ovo je signifikantna arhitekturalna promjena — ne može biti refaktorirana naknadno bez migracije

**Ovu pretpostavku mora potvrditi Dream Point ili Apros na prvoj sesiji.**

---

## 5. Pricing Architecture

> **✅ RAZRIJEŠENO 2026-07-02 (arhitektura).** Apros je direktno potvrdio: **domaći kupci = Model A**, **strani kupci = Model C** (paralelni slojevi, ne alternative). Model B (Sekcija ispod) se **ne implementira** — dokumentiran je radi povijesnog konteksta konflikta koji je ovime razriješen.
> **Payload primjer i dalje nedostaje** — vidi `docs/apros-session-final-pack.md` → "Still Required From Apros". Sekcija 9 (AP-01) ostaje otvorena samo za payload finalizaciju, ne za odabir modela.

### Poznate činjenice (potvrđene iz discovery + Apros odgovor 2026-07-02)

- `wholesalePrice` je proširenje dodano na B2C `articleList/get` endpoint
- **✅ Apros je potvrdio (2026-07-02): `wholesalePrice` je bazna veleprodajna cijena za domaće kupce; finalna cijena = `wholesalePrice − Rabat 1`.** Ranija pretpostavka da Apros šalje već izračunate finalne cijene za sve kupce (evidencija za Model B) je time zamijenjena za domaći segment.
- "Rabat 1" se šalje **po brandu** za partnera — ugovorni uvjet
- **Iznos popusta ne prikazuje se na ribbon-ima** — vidljiv samo na fakturama
- **Promocijska cijena ima prioritet nad Rabat 1** — kritično poslovno pravilo; mora biti eksplicitno implementirano u pricing filter hook-u
- Promotivne ponude dolaze iz Apros-a; postojat će zasebna promotivna stranica
- Prikaz na listingu: **neto cijena s rabatom, bez PDV-a**
- Prikaz u košarici: **puni iznos s PDV-om**
- Strani kupci: **finalna neto cijena** iz cjenika (nije postotak rabata)
- PDV izuzetak za strane kupce
- Porezni profil kupca iz "pravnog oblika" koji Apros šalje
- Jedina CMS payment metoda: **transakcijski račun (virman)**; odgoda plaćanja je izvan WC

### Konfliktni signali o wholesalePrice semantici (V-01) — RAZRIJEŠENO

| Izvor | Interpretacija | Implikacija |
|---|---|---|
| Leo Benkek email | `wholesalePrice` = bazna katalog cijena (ista za sve partnere) | WC mora računati: `wholesalePrice × (1 − Rabat1%)` |
| Milenko Stojaković | `wholesalePrice` = generalno finalna B2B cijena | WC prikazuje vrijednost; Rabat 1 je rijedak sloj |
| **Apros (2026-07-02)** | **`wholesalePrice` = bazna cijena za domaće kupce** | **Potvrđuje Leo Benkek interpretaciju — Model A je odabran model za domaći segment** |

**Konflikt razriješen 2026-07-02.** Leo Benkek interpretacija je potvrđena za domaći segment (Model A). Milenko Stojakovićevo zapažanje se odnosilo na B2C kontekst i ne primjenjuje se na B2B pricing. Vidi AP-01 u Sekciji 9 — status ažuriran, payload primjer i dalje nedostaje.

---

### Model A — wholesalePrice kao baza, Rabat 1 u WooCommerce-u ✅ ODABRANO ZA DOMAĆE KUPCE

**Premisa:** `wholesalePrice` je jednaka za sve partnere. Per-partner finalna cijena = `wholesalePrice × (1 − brand_rabat%)`.

#### Potrebni podaci

- `wholesalePrice` po artiklu (iz `articleList/get`)
- `brand_rabat_percentage` po partneru po brandu (iz partner/ugovorni uvjeti endpoint-a)
- `brand_id` na artiklu (postoji u B2C implementaciji)

#### WooCommerce storage strategija

```
wp_postmeta (product)
  _wholesale_price       → wholesale_price (decimal) — baza za izračun
  _erp_brand_id         → brandId (integer) — ključ za rabat lookup

wp_usermeta (user)
  _b2b_brand_rabat      → { brandId: percentage, ... } — JSON, per-brand rabat
```

#### Runtime kalkulacija

Na svakom product prikazu za prijavljenog B2B korisnika:
1. Čitaj `_wholesale_price` produkta
2. Čitaj `_b2b_brand_rabat` korisnika (iz Redis cache)
3. Dohvati `brandId` produkta → pronađi odgovarajući rabat
4. `finalna_cijena = wholesale_price × (1 − rabat / 100)`
5. PDV: ako `_b2b_tax_profile === 'domestic'` → prikaži + PDV; inače bez

**WooCommerce filter hook:** `woocommerce_product_get_price` / `woocommerce_get_price_excluding_tax`

#### Caching implikacije

- Per-user rabat mapa mora biti u Redis objektnom cache-u
- Invalidacija: pri svakom partner sync-u koji donosi novi rabat
- Cache key: `dp_b2b_brand_rabat_{user_id}`
- Runtime izračun je O(1) lookup u mapi — prihvatljivo za listing

#### Migracija utjecaj

- Visokog utjecaja: pricing filter zahvaća svaki product prikaz
- Nema utjecaja na order export (šalje se final computed price)
- Rollout mora biti per-user feature flag dok se ne validira pricing točnost

---

### Model B — wholesalePrice kao finalna per-partner cijena ❌ NIJE ODABRANO (povijesni kontekst)

> Apros je 2026-07-02 potvrdio Model A za domaće kupce — ovaj model se ne implementira. Dokumentiran je isključivo radi povijesnog konteksta konflikta iz Sekcije "Konfliktni signali" iznad.

**Premisa:** Apros preračunava finalnu neto cijenu per-partner na svojoj strani. `wholesalePrice` koji WC prima je već individualna cijena za tog partnera. Rabat 1 je rijedak opcionalni dodatni sloj.

#### Potrebni podaci

- `wholesalePrice` per artiklu **per partneru** (zahtijeva da API endpoint primi `sif_kup` kao parametar, ili da postoji per-partner feed)
- Ili: svi partneri dobivaju isti `wholesalePrice` → cijena nije per-partner, ali je već primijenjeni rabat

**Ključno pitanje koje razrješava Model B vs Model A:** Je li `wholesalePrice` u `articleList/get` responsesu jednaka vrijednost za sve partnere, ili se mijenja per-partner?

#### WooCommerce storage strategija

Ako je `wholesalePrice` ista za sve partnere:
```
wp_postmeta (product)
  _wholesale_price  → čuva generalnu veleprodajnu cijenu
  (nema per-partner storage potrebe)
```

Ako je per-partner (zahtijeva per-partner feed ili parametan API):
```
wp_usermeta (user)
  _b2b_product_prices → { product_erp_id: price, ... }
  (skalabilnost problematična na 10k artikala × n partnera)
```

#### Runtime kalkulacija

Scenarij A (ista za sve): prikaži `_wholesale_price`, dodaj PDV prema tax profilu. Trivijalan filter.

Scenarij B (per-partner u user meta): lookup u user meta JSON strukturi — O(1) ali potencijalno velik payload pri syncu.

#### Caching implikacije

Scenarij A: nema posebnih zahtjeva izvan standardnog WC product cache-a.

Scenarij B: Redis je neophodan za per-partner price lookup; payload veličine per-user je rizik.

#### Migracija utjecaj

Model B scenarij A je najjednostavniji za implementaciju — `_wholesale_price` se čuva kao `regular_price` u WC, filter dodaje PDV logiku po tax profilu. Nema pricing engine u klasičnom smislu.

---

### Model C — Cjenik po državama (strani kupci) ✅ ODABRANO ZA STRANE KUPCE

**Status:** Potvrđeno od Apros-a (2026-07-02) — endpoint `countryPriceList`. Payload primjer i dalje nedostaje.

**Premisa:** Strani kupci ne dobivaju Rabat 1 mehanizam. Dobivaju finalnu neto cijenu iz državno-specifičnog cjenika koji Apros šalje. PDV tretman ovisi o pravnoj/poreznoj kategoriji partnera.

**Ovo nije alternativa Modelu A ili B — to je DODATNI sloj** koji se primjenjuje na strane kupce paralelno s domaćim pricing modelom.

#### Potrebni podaci

- `_b2b_tax_profile` korisnika: `domestic` / `foreign` / `tax_exempt`
- Country-specific price map po artiklu (dolazi od Apros-a, format nevalidiran)
- Korisnikova zemlja iz `billing_country` ili posebnog partner atributa

#### WooCommerce storage strategija

```
wp_postmeta (product)
  _wholesale_price_country  → { "DE": price, "AT": price, ... } JSON
  (alternativa: zasebni price list termin u custom taksonomiji)
```

#### Runtime kalkulacija

```
IF user._b2b_tax_profile === 'foreign':
  prikaži _wholesale_price_country[user.billing_country]
  PDV: ne primjenjuj
ELSE IF user._b2b_tax_profile === 'domestic':
  primijeni Model A ili B logiku
  PDV: primijeni
ELSE IF user._b2b_tax_profile === 'tax_exempt':
  primijeni base price, bez PDV-a
```

#### Caching implikacije

Kombinirani tax-aware cache: `dp_b2b_price_{user_id}_{product_id}_{country}`. Složen, ali neophodan.

#### Migracija utjecaj

Model C mora biti planiran od početka — naknadna ugradnja country-specific pricing u postojeći filter je rizična. Storage model mora biti dizajniran da podrži oba domestic i foreign price u istom produktu.

---

### Što je razriješeno, a što treba payload finalizaciju (ažurirano 2026-07-02)

**Razriješeno:**
1. ✅ `wholesalePrice` je bazna cijena za domaće kupce (Model A odabran)
2. ✅ Strani kupci koriste `countryPriceList` (Model C odabran)

**Još treba payload primjer:**
3. Egzaktan format i field nazivi "Rabat 1" u partner/ugovorni uvjeti (`partnerBrandDiscountList`) endpointu
4. Je li Rabat 1 obavezan za sve domaće partnere ili opcionalan u pojedinim slučajevima
5. Egzaktan format `countryPriceList` endpointa
6. Realni payload primjer s jednim domaćim partnerom s rabatom i jednim stranim partnerom s cjenikom — vidi `docs/apros-session-final-pack.md` → "Still Required From Apros"

---

## 6. Warehouse Architecture

### Trenutna implementacija (Jekaa B2C)

Jedan `stock` field po varijaciji. `manage_stock = true`. Nema warehouse breakdown. Sve što dolazi iz Apros-a je agregirani ukupni stock.

### B2B zahtjev (potvrđeno iz discovery)

4 aktivna skladišta:
- **Skladište 1** — Glavno
- **Skladište 3** — Igračke
- **Skladište 4** — Naočale
- **Skladište 5** — Lifestyle

**Pravilo vidljivosti stanja:** Stanje se prikazuje **po skladištu, ne agregirano**. Automatsko mapiranje branda na matično skladište — brand koji postoji u više skladišta vuče stock samo s matičnog.

### Stock Storage Model

WooCommerce nema native multi-warehouse podršku. Opcije:

**Opcija A — Custom meta per warehoue (preporučeno)**

```
wp_postmeta (product/variation)
  _stock_warehouse_1    → integer
  _stock_warehouse_3    → integer
  _stock_warehouse_4    → integer
  _stock_warehouse_5    → integer
  _stock_primary_warehouse → integer (ID matičnog skladišta, određen brand mappingom)
```

WC `stock_quantity` postaje = vrijednost matičnog skladišta (za WC internu logiku narudžbi).

**Opcija B — JSON meta**

```
wp_postmeta (product/variation)
  _stock_by_warehouse   → {"1": 50, "3": 0, "4": 12, "5": 8} JSON
```

Prednosti Opcije A: direktni meta query, bez JSON parsing u PHP. Prednosti Opcije B: jedno polje, lakše za Apros payload proširenja.

**Preporuka: Opcija A** — performansno bolje za meta query i za WP-CLI diagnostiku.

### Brand → Warehouse Mapping

```
wp_options (ili ACF options)
  dp_b2b_brand_warehouse_map → { brandId: warehouseId, ... }
```

Admin UI za upravljanje mappingom (proširenje ERP payment/shipping plugina ili novi admin settings tab).

**Logika sync-a:**
1. Apros šalje stock per warehouse u product payload
2. Sync čuva sve warehouse vrijednosti u postmeta
3. Brand lookup → identificira matično skladište
4. WC `stock_quantity` = vrijednost matičnog skladišta
5. PDP prikazuje sve warehouse vrijednosti s custom frontend komponentom

### Vidljivost stanja na frontendu

Per-warehouse prikaz zahtijeva custom PDP komponentu. WC native stock prikazuje samo jedan ukupni broj. Prijedlog: shortcode ili ACF block koji čita `_stock_warehouse_*` meta i renderira tablicu/listu po skladištu (s labelama: "Glavno: 50 kom", "Naočale: 12 kom").

### Stock Sync Frekvencija

**ASSUMPTION:** Stock sync je dio product sync cron-a (isti interval). Delta sync za stock je nevalidiran (V-08, V-07). Ako Apros podržava stock-only delta endpoint, odvojiti stock sync na viši frekvenciji (npr. svakih 15 minuta) od product sync (hourly).

**Napomena — cart-level rezervacija (2026-07-03):** Ova sekcija opisuje warehouse *sync* stock, ne rezervaciju. Pitanje cart-level rezervacije zaliha (1-satni hold od dodavanja u košaricu) je otvorena poslovna odluka, praćena kao **DP-B06** u `docs/project-status-matrix.md` — trenutno nije dio arhitekture (native WC first-order-wins ostaje preporuka). Ako se cart-level rezervacija naknadno prihvati, to je nova komponenta koja zahtijeva vlastiti lock/expiry mehanizam, neovisan o warehouse sync modelu opisanom ovdje.

---

## 7. Order Export Architecture

### Analiza trenutne implementacije (Jekaa B2C)

Slabe točke koje se moraju riješiti za B2B:
- Nema `sif_kup` u payloadu
- `billingOib` je hardkodirano prazno (`""`)
- Nema odabira dostavne lokacije — šalje se WC billing/shipping adresa
- Engraving item (productId=56399) je Jekaa-specifičan hardkod
- Nema prodajno mjesto routing (igračke vs. lifestyle)
- Sinkroni inline send (bez retry, bez AS)
- Nema idempotency na Apros strani (nevalidiran V-05)

### B2B Order Export Arhitektura

#### Trigger uvjeti

Identični B2C modelu:
- `woocommerce_order_status_processing`
- `woocommerce_payment_complete`
- `woocommerce_thankyou` (gotovinska plaćanja)
- Ručna admin akcija

**Razlika:** Umjesto inline `wp_remote_post`, triggerira Action Scheduler job.

#### Action Scheduler flow

```
[1] WC order dođe u processing status
[2] Hook dodaje AS job: dp_erp_send_order (order_id)
[3] AS runner pokupi job (max timeout: 30s)
[4] AS job: validacija, payload build, POST → Apros
[5a] Uspjeh: spremi Apros order ID u order meta, status → 'erp_synced'
[5b] Greška (transijentna): AS retry (exponential backoff, max 3 pokušaja)
[5c] Greška (permanentna): admin notifikacija, ručna intervencija
```

**ASSUMPTION:** Apros order endpoint je idempotent ili vraća Apros order ID koji se može koristiti kao guard (V-05 nevalidiran). Bez idempotency garancije, retry mora biti konzervativniji — samo na network timeout, ne na HTTP 4xx/5xx.

#### B2B Order Payload

```json
{
  "number": 12345,
  "date": "2026-06-08",
  "sif_kup": "APR-00123",
  "paymentTypeId": 2,
  "shippingMethodId": 2,
  "salesLocationId": 3,
  "billingTitle": "Firma d.o.o.",
  "billingAddress": "Ulica 1",
  "billingZipCode": "10000",
  "billingCity": "Zagreb",
  "billingCountry": "HR",
  "billingPhone": "0912345678",
  "billingEmail": "nabava@firma.hr",
  "billingOib": "12345678901",
  "deliveryLocationId": "LOC-456",
  "shippingTitle": "Firma d.o.o. - Poslovnica 2",
  "shippingAddress": "Druga ulica 5",
  "shippingNote": "...",
  "items": [
    {
      "position": 1,
      "productId": 55620,
      "code": "ART-001",
      "title": "Naziv proizvoda",
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

**Nova polja u odnosu na B2C:**

| Polje | Izvor | Napomena |
|---|---|---|
| `sif_kup` | `user_meta._sif_kup` | Obavezno za B2B |
| `salesLocationId` | Određen per order, prema brand/roba tipu | Vidi "Prodajno mjesto" ispod |
| `billingOib` | `user_meta._b2b_oib` | Prazno na B2C; obavezno za B2B |
| `deliveryLocationId` | Odabran na checkoutu | Apros location ID odabrane lokacije |

#### Prodajno mjesto (Sales Location) Routing

Narudžbe se smještaju na prodajno mjesto prema vrsti robe:
- Igračke → prodajno mjesto 3
- Lifestyle → prodajno mjesto 5

**Mehanizam:** Per-order determination na osnovu sadržaja košarice. Ako narudžba sadrži artikle iz oba tipa — podijeliti narudžbu u dva AS joba, jedan per salesLocation. Ili: dominantna kategorija određuje salesLocation.

**BLOKER (BL-06):** Konkretni mapping mehanizam nepoznat — jedini izvor je Josip / stari B2B sustav. Vidi Sekciju 9.

#### Invoice Splitting

**Status: PARTIALLY RESOLVED.** Potvrđeno: Apros vrši splitting faktura. Mehanizam po kojem Apros određuje kada dolazi do razdvajanja nije poznat.

**Trenutna radna pretpostavka (2026-07-03):** Apros vrši interno segmentiranje faktura — WC šalje jednu narudžbu (jedan standardni order payload), Apros interno odlučuje o razdvajanju na više faktura na svojoj strani, bez potrebe da WC to inicira ili prati. Ova pretpostavka nije formalno potvrđena od Apros-a.

**Arhitekturalne implikacije (ovisne o potvrdi pretpostavke — zahtijeva AP-06 proširenje):**
- Ako je pretpostavka točna (split isključivo interan u Apros-u, WC dobiva jedan standardni response) → nema implikacija za WC order export arhitekturu
- Ako je pretpostavka netočna (Apros može vratiti više response-a ili zahtijeva split na WC strani) → order export arhitektura se komplicira, potreban je redesign payload buildera i response parsinga
- Mora biti formalno razriješeno u okviru AP-06 validacije — vidi `docs/project-status-matrix.md` Sekciju 1.8, DP-D4

#### Dostavna lokacija (Delivery Location)

B2B kupac bira dostavnu lokaciju na checkoutu iz liste dostupnih lokacija za njihov `sif_kup`. Odabrana lokacija se sprema u WC order meta i šalje kao `deliveryLocationId` u Apros.

**Checkout UI:** Custom polje (radio/select) koji čita `_b2b_delivery_locations` user meta i prikazuje listu.

#### OIB Handling

OIB/VAT ID korisnika:
- Pohraniti u `user_meta._b2b_oib` pri registraciji ili partner sync-u
- Kopirati na WC order pri kreiranju (`woocommerce_checkout_create_order`)
- Uključiti u Apros order payload

---

## 8. Data Ownership Matrix

| Data Element | Izvor istine | WC uloga | Sync smjer |
|---|---|---|---|
| Artikli i varijacije | **Apros** | Cache / prikaz | Apros → WC (pull) |
| Cijena (wholesale) | **Apros** | Cache / prikaz | Apros → WC (pull) |
| Rabat (Rabat 1) | **Apros** | Cache (user meta) | Apros → WC (pull) |
| Country cjenik | **Apros** | Cache (product meta) | Apros → WC (pull) |
| Promocijske cijene | **Apros** | Cache / prikaz; prioritet nad Rabat 1 | Apros → WC (pull, ERP atribut "Posebna ponuda") |
| Stanje zaliha | **Apros** | Cache (per warehouse meta) | Apros → WC (pull) |
| Kategorije | **Apros** / Shared | WC drži prikaz | Apros → WC (sync) |
| Brändovi | **Apros** | WC drži prikaz | Apros → WC (sync) |
| Atributi | **Apros** | WC drži prikaz | Apros → WC (sync) |
| Slike artikala | **Apros** | WC Media Library (kopija) | Apros → WC (pull) |
| Vidljivost artikala | **WooCommerce** | Potpuni vlasnik | Nema — CMS lokalno |
| Korisnici (WP) | **WooCommerce** | Potpuni vlasnik | Import pri pokretanju (Apros export → WC import) |
| Partner status (B2B rola) | **Dream Point** | WC implementira odluku | Apros → signal → WC |
| `sif_kup` vrijednost | **Apros** | User meta (kopija) | Apros → WC (pri approval) |
| Ugovorni uvjeti (rabat %) | **Apros** | User meta (kopija) | Apros → WC (pri approval / periodic) |
| Dostavne lokacije | **Apros** | User meta (kopija) | Apros → WC (pri approval / periodic) |
| `advance_only` flag | **Apros** | User meta (zadržano za buduće proširenje) | Apros → WC (pri approval) |
| OIB / VAT ID | **Dream Point (kupac)** | User meta | Forma pri registraciji |
| WC narudžbe | **WooCommerce** | Potpuni vlasnik | WC → Apros (export) |
| Apros order ID | **Apros** | Order meta (kopija iz response-a) | Apros → WC (order export response) |
| Order status | **WooCommerce** | Potpuni vlasnik (za WC statuse) | Jednosmjeran (WC → Apros) |
| Brand → Warehouse mapping | **Dream Point / admin** | WP options | Ručna admin konfiguracija |
| Payment → ERP ID mapping | **Dream Point / admin** | WP options (companion plugin) | Ručna admin konfiguracija |

---

## 9. Open Validation Items — BLOCKERS

> **✅ RENUMERISANO 2026-07-02.** Ova sekcija je usklađena sa kanonskom AP numeracijom iz `docs/apros-question-resolution-matrix.md` (autoritativni izvor, AP-01–AP-14). Ranija interna numeracija ovog blueprinta (koja se nije poklapala s kanonskom) je uklonjena. Dvije stavke nemaju kanonski AP ekvivalent — obilježene su odvojenim prefiksima **PL-xx** (Partner List) i **WH-xx** (Warehouse), ne AP, kako se ne bi lažno predstavljale kao dio kanonske AP-01–14 liste. Stavke su poredane po kanonskom broju.

### AP-01 — Pricing Model ✅ PARTIALLY RESOLVED (payload pending)

**Severity:** ~~P0~~ → P2 — arhitektura razriješena, payload finalizacija preostaje  
**Subsystem:** Pricing Engine (Sekcija 5)  
**Status (2026-07-02):** Apros potvrdio Model A (domaći: `wholesalePrice − Rabat 1`) + Model C (strani: `countryPriceList`). Arhitekturalni rizik uklonjen.

**Što još treba:**
- Realni payload primjer s jednim domaćim partnerom (s rabatom) i jednim stranim (s cjenikom)
- Egzaktan format `partnerBrandDiscountList` (Rabat 1 isporuka) i `countryPriceList`

**Izvor:** Direktan Apros odgovor (2026-07-02) + preostali payload primjer

---

### AP-03 — Approval Flow 🔄 RECLASSIFIED (polling/import, nema webhooka)

**Severity:** ~~P1~~ → Zatvoreno (arhitekturalna promjena, ne payload čekanje)  
**Subsystem:** Customer/Partner Architecture (Sekcija 4)  
**Status (2026-07-02):** Apros je potvrdio da approval webhook ne postoji. Tok: web registracija → email → ručno kreiranje u Apros-u → `B2B KUPAC = DA` → partner se pojavljuje na partner list endpointu. Partner sync mora biti **periodic polling/import**, ne webhook receiver. Vidi ažuriranu Sekciju 4 "Approval Lifecycle" iznad.

**Preostalo (nije arhitekturalni bloker):**
- Frekvencija polling-a
- Postoji li delta/changed-since mehanizam na partner list endpointu

**Izvor:** Direktan Apros odgovor (2026-07-02)

---

### AP-05 — Delta Sync Podrška (MEDIUM)

**Severity:** P2 — Operativni rizik  
**Subsystem:** Product Sync, Partner Sync  
**Impact:** Full sync na 10.000 artikala pri svakom cron pozivu je memorijski i vremenski intenzivan. Bez delta synca, cron interval mora biti duži.

**Što je potrebno:**
- Podržava li Apros delta sync za artikle? (timestamp filter, change feed, version ID?)
- Podržava li Apros delta sync za partnere?
- Koji je maksimalni broj artikala u jednom API responsu?

**Izvor:** Apros API sesija

---

### AP-06 — Order Endpoint Format ✅ PARTIALLY RESOLVED (payload pending)

**Severity:** ~~P0~~ → P1 — obavezna polja poznata, response format i idempotency preostaju  
**Subsystem:** Order Export (Sekcija 7)  
**Status (2026-07-02):** Apros potvrdio obavezna polja: `sif_kup`/`partnerId`, `partnerDeliveryLocationId`, stavke narudžbe s količinama.

**Što još treba:**
- Apros order endpoint URL i HTTP metoda
- Response format (vraća li Apros order ID? JSON struktura?)
- Je li endpoint idempotent? Što se dogodi pri duplikatnom slanju iste narudžbe?

**Izvor:** Direktan Apros odgovor (2026-07-02) + preostali payload primjer

---

### AP-07 — Delivery Locations Format ✅ PARTIALLY RESOLVED (payload pending)

**Severity:** P1 — Bloker za checkout/storage finalizaciju  
**Subsystem:** Customer/Partner Architecture (Sekcija 4)  
**Status (2026-07-02):** Endpoint `partnerDeliveryLocationList` potvrđen. Partner može imati više lokacija. **Nema default lokacije.**

**Što još treba:**
- Realni `partnerDeliveryLocationList` payload primjer s adresnim poljima i Apros location ID formatom
- Stabilnost location ID-a između sync ciklusa

**Izvor:** Direktan Apros odgovor (2026-07-02) + preostali payload primjer

---

### AP-09 — Auth Mehanizam ✅ RESOLVED

**Severity:** ~~P0~~ → Zatvoreno  
**Subsystem:** CurlClient, svi outbound Apros pozivi  
**Status (2026-07-02):** Apros potvrdio: **API Key**, isti pristup kao Jekaa B2C. OAuth 2.0 scenarij isključen.

**Preostalo (nije bloker):**
- Zasebni credentials za read (products) vs. write (orders)?
- Postoji li dodatni IP whitelist zahtjev uz API Key?

**Izvor:** Direktan Apros odgovor (2026-07-02)

---

### PL-01 — Partner List Endpoint Format (nije kanonski AP — vidi napomenu iznad)

**Severity:** P1 — Bloker za partner sync payload finalizaciju  
**Subsystem:** Customer/Partner Architecture (Sekcija 4), Pricing (Sekcija 5)  
**Status:** Neadresirano ovim Apros odgovorom — ostaje otvoreno.

**Napomena:** Ovo pitanje (opći format i URL "Liste partnera" — `sif_kup`, pravni oblik, B2B status) nema poseban kanonski AP-ID u `apros-question-resolution-matrix.md`. Blisko je vezano uz AP-07 (dostavne lokacije) i AP-01 (ugovorni uvjeti/rabat), ali je opći partner list endpoint format zaseban, neadresiran ostatak.

**Što je potrebno:**
- Partner list endpoint URL i format response-a
- Polja po partneru: `sif_kup`, pravni oblik, ugovorni uvjeti (rabat po brandu), cjenici po državama

**Izvor:** Apros API sesija — nije pokriveno odgovorom od 2026-07-02

---

### WH-01 — Multi-Warehouse Stock Format (nije kanonski AP — vidi napomenu iznad)

**Severity:** P2 — Storage dizajn bloker  
**Subsystem:** Warehouse Architecture (Sekcija 6)  
**Impact:** Storage model za per-warehouse stock ovisi o tome kako Apros strukturira stock u payloadu.

**Napomena:** Ovo pitanje nema poseban kanonski AP-ID u `apros-question-resolution-matrix.md` (AP-01–14 ne pokriva warehouse stock payload format). Preporuka: ako se ovo pitanje formalno postavi Apros-u, uvesti ga u kanonski registar kao novu stavku umjesto da ostane orphan.

**Što je potrebno:**
- Format stock podataka po skladištu u `articleList/get` ili zasebnom stock endpointu
- Identifikator skladišta u payloadu (numerički ID, string, nested objekt?)

**Izvor:** Apros API sesija + payload primjer — nije pokriveno odgovorom od 2026-07-02

---

### DP-01 — Model korisničkih računa (HIGH — REVIDIRAN)

**Severity:** P1 — Bloker za partner storage model  
**Subsystem:** Customer/Partner Architecture (Sekcija 4)  
**Status:** Pitanje je redefinisano — identificirana su dva konkretna scenarija; odluka nije donesena

**Dva identificirana scenarija:**

- **Scenarij A:** Jedan WP korisnik po firmi; više dostavnih adresa vezanih uz tog korisnika
- **Scenarij B:** Više WP korisnika (zaposlenici iste firme); više dostavnih adresa po firmi

**Impact po scenariju:**
- Scenarij A → `sif_kup` je 1:1 s WP User; storage je user meta; manji implementacijski scope
- Scenarij B → `sif_kup` je 1:N; potreban je `dp_b2b_company` CPT; user meta nije dovoljna; pricing i lokacije na razini company entiteta; značajna arhitekturalna promjena

**Što je potrebno:**
- Dream Point mora potvrditi koji scenarij odgovara poslovnim potrebama
- Ako Scenarij B → potrebna je odluka o shared order history između korisnika iste firme (DP-A02)

**Napomena:** Bez obzira na scenarij, migracija između modela nakon Faze 3 je složena — mora biti odlučeno **prije početka Faze 3**.

**Izvor:** Dream Point workshop

---

### DP-02 — Prodajno Mjesto Routing (HIGH)

**Severity:** P1 — Bloker za order export  
**Subsystem:** Order Export (Sekcija 7)  
**Impact:** Order export ne može odrediti `salesLocationId` bez mehanizma mapiranja narudžbe na prodajno mjesto.

**Što je potrebno:**
- Koji atribut artikla/kategorije određuje prodajno mjesto (3 = Igračke, 5 = Lifestyle)?
- Što se događa s mješovitom narudžbom (artikli iz oba tipa)?
- Jedini poznati izvor: Josip / stari B2B sustav

**Izvor:** Dream Point / Josip

---

## 10. Implementation Roadmap

> Roadmap je uvjetovan razrješenjem bloker-a iz Sekcije 9.  
> **Ažurirano 2026-07-02:** AP-09 (auth) i AP-03 (approval flow) su razriješeni — Faza 3 partner sync arhitektura je sada poznata (polling/import, ne webhook). AP-01, AP-06, AP-07 su djelomično razriješeni na arhitekturalnoj razini; Faze 4 i 5 mogu biti arhitekturalno dizajnirane, ali finalna implementacija čeka payload primjere (vidi `docs/apros-session-final-pack.md` → "Still Required From Apros"). PL-01, WH-01, DP-01 i DP-02 ostaju nepromijenjeni blokeri.

---

### Faza 1 — Infrastructure Preparation

**Može početi odmah. Nema blokirajućih pretpostavki.**

**Sadržaj:**
- Kopiranje i restrukturiranje baseline plugina (namespace: `UncleDev\Importer\B2B`)
- Proširenje `ProductToImport` DTO s B2B poljima (`b2b_eligible`, `wholesale_price`, `stock_by_warehouse`)
- Proširenje `CurlClient` s auth header inject mehanizmom — AP-09 (auth) je RESOLVED (API Key, 2026-07-02); implementacija može koristiti konačan mehanizam umjesto placeholder dizajna
- `b2bArticle` filtar u `AprosProvider::map_fields()` — skip non-B2B artikala
- Proširenje cron arhitekture za skalabilnost (batch processing, memory management za 10k artikala)
- ACF field group za B2B-specifična product meta polja
- Monitor plugin kopija (bez izmjena)
- Server: cron konfiguracija za Jekyll→B2B (novi user, nova putanja)

**Ovisnosti:** Pristup DP-B2B staging environment-u  
**Rizici:** Nema značajnih  
**Output:** B2B importer plugin skeleton koji može biti instantiran; product sync koji ignorira non-B2B artikle i prima ali ne procesira B2B-only polja

---

### Faza 2 — Product Synchronization Adaptation

**Ovisi o:** Faza 1 + WH-01 (warehouse format) + BL-02 razriješen

**Sadržaj:**
- Implementacija per-warehouse stock storage (`_stock_warehouse_1`, `_stock_warehouse_3`, `_stock_warehouse_4`, `_stock_warehouse_5`)
- Brand → Warehouse mapping admin UI
- WC `stock_quantity` = vrijednost matičnog skladišta (prema brand mapping-u)
- Per-warehouse stock prikaz na PDP (custom frontend komponenta)
- Validacija variation handling s B2B payload primjerima
- Hash-based change detection za B2B (`_RESPONSE_HASH` — nove verzija koja uključuje B2B polja)
- Delta sync adapter (ako AP-05 potvrdi podršku)

**Ovisnosti:** Apros sandbox pristup, realni artikal payload primjeri  
**Rizici:** Multi-warehouse model bez native WC podrške; performance na 10k artikala  
**Output:** Funkcionalni product sync za B2B katalog; warehouse prikaz na PDP

---

### Faza 3 — Partner Synchronization

**Ovisi o:** Faza 1 + PL-01 (partner list format — otvoreno) + AP-07 (delivery locations payload) + DP-01

**Ažurirano 2026-07-02:** AP-03 (approval flow) je RECLASSIFIED — partner sync se implementira kao cron-based polling/import job (vidi Sekciju 4), ne kao inbound webhook receiver. Ovo više nije arhitekturalni bloker za Fazu 3, ali implementacija dizajna (Korak 9/10 u `docs/b2b-erp-migration-plan.md`) mora biti ažurirana da odražava polling model.

**Sadržaj:**
- Partner list sync adapter (fetch `sif_kup`, pravni oblik, B2B status) — **cron-based polling job, ne webhook** (AP-03 reklasificirano 2026-07-02)
- WP User → `sif_kup` binding storage
- ~~Approval webhook endpoint~~ — **otpada**; zamjenjuje ga polling job koji detektira nove `B2B KUPAC = DA` zapise na partner list endpointu
- Ugovorni uvjeti storage (rabat per brand, cjenik per country)
- Delivery locations sync i storage (`partnerDeliveryLocationList` — endpoint potvrđen, payload primjer pending — AP-07)
- ~~`advance_only`, `free_shipping` flag propagacija~~ — **OUT OF SCOPE** (AP-08, temeljilo se na webhook pretpostavci koja ne postoji)
- B2B rola dodjela nakon polling job detekcije
- Admin partner management UI (prikaz `sif_kup`, status, ugovornih uvjeta)
- Periodični partner sync cron (interval nevalidiran — operativna optimizacija, nije bloker)

**Ovisnosti:** PL-01 (partner list format), AP-07 (delivery locations payload), DP-01 razriješen; Apros sandbox  
**Rizici:** `sif_kup` kardinalitet (DP-01) može zahtijevati Company entitet umjesto User meta  
**Output:** Funkcionalni onboarding flow (polling/import); B2B korisnici s ispravnim meta podacima

---

### Faza 4 — Pricing Engine

**Ovisi o:** Faza 3 + AP-01 (payload primjer — arhitektura već razriješena)

**✅ Modeli odabrani (2026-07-02): Model A (domaći) + Model C (strani), paralelno. Model B se ne implementira.**

**Sadržaj:**

**Zajednički za sve modele:**
- WooCommerce price filter hook za B2B korisnika
- PDV logika per tax profil (`domestic`, `foreign`, `tax_exempt`)
- Redis cache za per-user pricing data
- Cache invalidacija pri partner sync-u
- Listing prikaz: neto cijena bez PDV-a (potvrditi hover/tooltip za PDV)
- Košarica prikaz: puni iznos s PDV-om

**Model A specifično:**
- `_wholesale_price` storage (product meta)
- Per-user brand-rabat mapa (user meta)
- Runtime: `wholesale_price × (1 − rabat%)` u filter hook-u

**Model B specifično — ❌ ne implementira se (dokumentirano radi povijesnog konteksta):**
- `regular_price` = `wholesalePrice` (direktno)
- PDV filter dovoljno za prikaz
- Minimalni pricing engine

**Model C (strani kupci) — dodatno:**
- `_wholesale_price_country` JSON storage
- Country lookup u filter hook-u
- PDV exemption za foreign korisnika

**Ovisnosti:** AP-01 payload primjer; Faza 3 (partner data dostupni)  
**Rizici:** Pogrešan pricing u košarici je poslovni incident — QA obavezna s realnim partner podacima  
**Output:** Per-partner cijene ispravno prikazane na listingu, PDP i košarici

---

### Faza 5 — Order Export Adaptation

**Ovisi o:** Faza 3 + AP-06 (payload primjer — obavezna polja poznata) + ~~AP-09~~ (RESOLVED, auth) + DP-02

**Sadržaj:**
- Kompletni rewrite `order.php` za B2B
- Action Scheduler job: `dp_erp_send_b2b_order`
- Payload builder s `sif_kup`, `deliveryLocationId`, `billingOib`, `salesLocationId`
- Prodajno mjesto routing logika (prema DP-02 odgovoru)
- Retry logika: exponential backoff, max 3 pokušaja za transijentne greške
- Apros order ID storage u order meta
- Admin order action: "Pošalji u ERP" (s retry bypass-om)
- Checkout UI: delivery location picker (iz `_b2b_delivery_locations` user meta)
- Order meta prikaz u WC admin (Apros order ID, sync status, payload log)

**Ovisnosti:** AP-06 payload primjer, ~~AP-09~~ RESOLVED, DP-02 razriješen; Faza 3 kompletna  
**Rizici:** Duplikate narudžbe u Apros-u ako retry nije pažljivo dizajniran; `salesLocationId` greška šalje narudžbu na pogrešno prodajno mjesto  
**Output:** B2B narudžbe se pouzdano šalju u Apros; admin ima vidljivost sync statusa

---

### Faza 6 — Validation & Testing

**Ovisi o:** Faze 1–5 kompletne; Apros sandbox dostupan

**Sadržaj:**
- End-to-end test s realnim Apros sandbox podacima
- Pricing točnost: provjera s Dream Point i Apros za 5+ partnera s različitim ugovornim uvjetima
- Warehouse stock prikaz validacija
- Order export validacija: Apros prima narudžbu, vraća order ID, partner vidi narudžbu
- Rollout faza 1: Izipizi partneri (ograničeni skup)
- Monitoring: alert thresholdi za sync lag, order export failures
- Stress test: full product sync na 10k artikala, mjerenje duration i memory usage
- Playwright E2E: B2B login → cijena prikazana → košarica s PDV-om → checkout s lokacijom → order potvrda

**Ovisnosti:** Apros sandbox s realnim podacima; Dream Point QA sudjelovanje  
**Rizici:** Pricing greške koje nisu vidljive bez realnih podataka; Apros sandbox može imati drugačije ponašanje od produkcije  
**Output:** Validirana integracija; Rollout Phase 1 spreman

---

### Dependency Graph (skraćeni prikaz)

```
Faza 1 (odmah)
  └─► Faza 2 (WH-01)
        └─► [Product sync stabilan]

Faza 1 (odmah)
  └─► Faza 3 (PL-01, AP-07, AP-03, DP-01)
        ├─► Faza 4 (AP-01)
        │     └─► [Pricing stabilan]
        └─► Faza 5 (AP-06, AP-09, DP-02)
              └─► [Order export stabilan]

Faze 2 + 4 + 5
  └─► Faza 6 (Apros sandbox + Dream Point QA)
        └─► Rollout Phase 1 (Izipizi)
```

---

## Appendix: Oznake koje se koriste u dokumentu

| Oznaka | Značenje |
|---|---|
| **ASSUMPTION** | Pretpostavka bez validacije od Apros-a ili Dream Point-a |
| **NC-xx** | Novo potvrđena činjenica iz mail korespondencije (Maj 2026) |
| **V-xx** | ID nevalidirane pretpostavke iz `erp-discovery-findings.md` |
| **BL-xx** | ID blokirajuće stavke iz `erp-discovery-findings.md` |
| **AP-xx** | Bloker iz Sekcije 9 koji zahtijeva Apros validaciju — **numeracija je kanonska**, poklapa se s `docs/apros-question-resolution-matrix.md` (renumerisano 2026-07-02) |
| **PL-xx** | Partner List validacijska stavka bez kanonskog AP ekvivalenta (Sekcija 9) |
| **WH-xx** | Warehouse validacijska stavka bez kanonskog AP ekvivalenta (Sekcija 9) |
| **DP-xx** | Interna Dream Point odluka, ne zahtijeva Apros validaciju |
| **DP-xx** | Bloker iz Sekcije 9 koji zahtijeva Dream Point validaciju |
