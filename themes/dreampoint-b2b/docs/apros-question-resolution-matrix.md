# Apros Question Resolution Matrix — DP-B2B

**Datum:** 2026-06-08  
**Status dokumenta:** Autoritativni izvor istine za AP-01 – AP-14  
**Evidencijska hijerarhija:** produkcijski kod → produkcijsko ponašanje → developer feedback → discovery nalazi → workshop pretpostavke

> Ovaj dokument određuje koji AP-od zahtijevaju Apros sesiju, koji su zatvoreni, i koji se mogu riješiti interno.  
> Ne sadrži pretpostavke koje nisu eksplicitno označene. Ne invencija.

---

## AP-01

**Originalno pitanje**

Potvrdite egzaktnu vezu između `wholesalePrice`, Rabat 1 i finalne B2B cijene za kupca. Je li `wholesalePrice` ista za sve partnere (bazna/katalog cijena) ili per-partner (Apros preračunava po kupcu)? Primjenjuje li se Rabat 1 uvijek ili samo za određene partnere? Dostavite realni payload primjer za partnera s Rabat 1 i za partnera bez.

**Current Status**

PARTIALLY ANSWERED

**Confidence Level**

LOW

**Evidence Sources**

- Developer Feedback (Milenko Stojaković, 2026-06-03)
- Discovery Findings (Leo Benkek email, NC-04)
- Adaptation Blueprint (Sekcija 5, konflikt dokumentiran)
- Architecture Validation Audit (EG-02, P0-01)

**What We Know**

- `wholesalePrice` je proširenje dodano na postojeći B2C `articleList/get` endpoint (NC-02 — potvrđeno)
- Rabat 1 dolazi po brandu za partnera, iz "ugovorni uvjeti" endpointa: `sif_kup` + brand + postotak (NC-04 — podržano dokazima)
- Listing prikazuje neto cijenu s rabatom, bez PDV-a (potvrđena poslovna pravila)
- Košarica prikazuje puni iznos s PDV-om (potvrđena poslovna pravila)
- Strani kupci dobivaju finalnu neto cijenu iz cjenika po državama — ne postotak (NC-04 — podržano dokazima)
- Dva nezavisna iskustvena izvora daju direktno konfliktne interpretacije semantike `wholesalePrice`:
  - Leo Benkek (integrator): `wholesalePrice` = bazna/katalog cijena, ista za sve partnere; Rabat 1 je obavezan per-partner mehanizam za finalnu cijenu
  - Milenko Stojaković (developer, B2C iskustvo): `wholesalePrice` = generalno već finalna B2B cijena; Rabat 1 je rijedak opcionalni sloj

**What We Do NOT Know**

- Je li `wholesalePrice` ista vrijednost za sve partnere ili se razlikuje per-partner
- Primjenjuje li se Rabat 1 uvijek, samo za određene partnere, ili nikad automatski
- Format realnog payloada koji sadrži obje vrijednosti za istog partnera
- Koji od tri modela (A, B, C iz blueprinta) implementirati — odluka nije moguća bez ovoga

**Why This Matters**

Pricing engine je nova komponenta bez presedana u B2C pluginu. Model A (WC računaju finalnu cijenu iz baze i rabata) i Model B (WC prikazuju gotovu cijenu) zahtijevaju potpuno drugačiju implementaciju i storage strategiju. Greška znači kompletan rework pricing komponente. P0-01 bloker.

**Can Implementation Proceed Without This?**

NO

**Required Next Action**

Need payload example — realni `articleList/get` response za domaćeg partnera + realni `ugovorni uvjeti` response za istog partnera, s obje vrijednosti u istom prikazu.

---

## AP-02

**Originalno pitanje**

Po čemu Apros identifikuje artikle pri sync-u — SKU, interni ID ili oboje? Je li identifikator jedinstven na razini varijante ili samo parent-a?

**Current Status**

ANSWERED

**Confidence Level**

HIGH

**Evidence Sources**

- Production Code (`AprosProvider.php`, `Importer.php`, `ProductToImport.php`)
- Plugin Analysis (Sekcija 5, Product Identification Model)
- Discovery Findings (NC-02 — B2B proširuje isti B2C endpoint)

**What We Know**

- Apros šalje i interni numerički ID i šifru artikla (`code`); oba su prisutna u payloadu
- Primarni ERP identifikator: `articleId` za parent proizvode, `variationId` za varijante — čuva se kao `_erp_id` postmeta
- Šifra artikla (`code`) čuva se kao `_ARTICLE_CODE` postmeta
- Identifikator je jedinstven na razini varijante: varijante imaju vlastiti `variationId`
- SKU format: `"P-" + articleId` za parent; `variationId` bez prefiksa za varijacije
- EAN/GTIN barcode (`barcode`) je treće identifikacijsko polje, čuva se kao WC `global_unique_id`
- NC-02 potvrđuje B2B proširuje isti B2C endpoint → isti identifikacijski model primjenjuje se

**What We Do NOT Know**

- Ništa kritično. Identifikacijski model je u potpunosti poznat iz produkcijskog koda.

**Why This Matters**

Osnova svakog sync mehanizma (insert, update, deduplication). Pitanje je zatvoreno.

**Can Implementation Proceed Without This?**

YES

**Required Next Action**

No action required. Validacija B2B payload primjera u Fazi 2 (formalna potvrda, ne bloker).

---

## AP-03

**Originalno pitanje**

Kada Apros poziva WP approval endpoint, šalje li samo `email`/`user_id` — ili uključuje i `sif_kup`, price listu i `advance_only` flag?

**Current Status**

OPEN

**Confidence Level**

LOW

**Evidence Sources**

- Discovery Findings (NC-05 — konceptualni tok potvrđen)
- Adaptation Blueprint (Sekcija 4, Approval Lifecycle — body nevalidiran)
- Architecture Validation Audit (CO-04 — protivrječje između checklist i blueprint prioritizacije)

**What We Know**

- Konceptualni onboarding tok potvrđen (NC-05): web forma → DP admin notifikacija → ručno otvaranje u Apros-u → Apros šalje signal → WC dodjeljuje rolu
- `sif_kup` je u vlasništvu Apros-a — WC čuva kopiju
- Planirani WP inbound endpoint: `POST /wp-json/dreampoint-b2b/v1/approve-partner` (dizajn, nije implementiran)

**What We Do NOT Know**

- Koja su točno polja u webhook body-u
- Stiže li `sif_kup` u samom approval webhook pozivu
- Stižu li podaci o price listi/rabatu u istom pozivu
- Stiže li `advance_only` flag u istom pozivu
- Format webhook body-a (JSON? query params?)
- Postoji li webhook signature za verifikaciju

**Why This Matters**

Određuje arhitekturu onboarding flow-a. Ako `sif_kup` ne stiže u approval webhookу, potreban je odvojeni partner sync mehanizam koji komplicira arhitekturu i dodaje moving parts. Faza 3 bloker (AP-05 u blueprint Sekciji 9).

**Can Implementation Proceed Without This?**

NO

**Required Next Action**

Validate with Apros — kompletna lista polja u approval webhook body-u.

---

## AP-04

**Originalno pitanje**

Kako su varijante strukturirane u `articleList/get` payload-u? Zasebne stavke ili ugniježđene unutar parent-a? Ima li svaka varijanta vlastiti SKU i cijenu?

**Current Status**

PARTIALLY ANSWERED

**Confidence Level**

MEDIUM

**Evidence Sources**

- Production Code (9 GET endpointa potvrđenih u `AprosProvider.php`)
- Plugin Analysis (Sekcija 3, Endpoint Inventory; Sekcija 5, Parent/Child Handling)
- Discovery Findings (NC-02 — B2B proširuje isti B2C endpoint)
- Architecture Validation Audit (BL-02 downgraded from HIGH to MEDIUM)

**What We Know**

- Produkcijska B2C implementacija koristi ODVOJENE endpointe za artikle i varijacije:
  - `/articleList/get` → parent artikli
  - `/articleVariationList/get` → sve varijacije s `articleId` kao foreign key na parenta
- Varijacije imaju vlastiti `variationId`, vlastitu cijenu, stanje zaliha i `visible` flag
- `virtualArticle: true` označava parent s varijantama; `virtualArticle: false` je simple produkt
- NC-02 potvrđuje: B2B proširuje isti B2C endpoint, ne uvodi novi — format varijanti vjerojatno identičan
- BL-02 je downgraded: historijsko B2C endpoint maturity značajno smanjuje vjerovatnoću iznenađenja

**What We Do NOT Know**

- Sadrži li B2B `articleList/get` strukturne razlike za varijante (npr. additional B2B fields na varijantama)
- Točan B2B payload primjer s `wholesalePrice` i `b2bArticle` poljem na varijantama
- Ponašanje `b2bArticle` flaga na varijanti vs. parent-u (treba li oba biti true za B2B eligibilnost?)

**Why This Matters**

Sync adapter mora točno poznavati strukturu varijanti za parent/child kreiranje u WooCommerce-u. Rizik je sada LOW zahvaljujući produkcijskom kodu — ista logika primjenjuje se.

**Can Implementation Proceed Without This?**

YES (Faza 1 može početi; Faza 2 zahtijeva B2B payload primjer za validaciju)

**Required Next Action**

Need production verification — B2B `articleList/get` i `articleVariationList/get` payload primjer (Faza 2, nije bloker za Fazu 1).

---

## AP-05

**Originalno pitanje**

Pull ili push model za product sync? Full sync ili delta? Ako delta — kako Apros označava promijenjene stavke?

**Current Status**

PARTIALLY ANSWERED

**Confidence Level**

MEDIUM

**Evidence Sources**

- Production Code (cron konfiguracija, `ImporterWPClient.php`)
- Plugin Analysis (Sekcija 10, Cron & Synchronization Model)
- Discovery Findings (potvrđene poslovne domene — "inkrementalni sync preferiran")
- Architecture Validation Audit (CO-01 — dokumentirana kontradikcija)

**What We Know**

- CMS-pull model potvrđen produkcijskim kodom: WP povlači podatke iz Apros-a (ne push)
- Produkcija radi full sync svaki run — nema delta endpointa u upotrebi
- Cron frekvencija B2C: svaki sat (minuta 45)
- Change detection optimizacija postoji: `_RESPONSE_HASH` preskače WC save ako produkt nije promijenjen
- NC-02 potvrđuje B2B proširuje iste endpointe → pull model primjenjuje se
- "Inkrementalni sync preferiran" je izjava klijenta/integratora, ne potvrda Apros tehničke mogućnosti (CO-01)

**What We Do NOT Know**

- Podržava li Apros delta sync (changed-since timestamp, change feed, version ID)
- Maksimalni broj artikala u jednom API responsu
- Postoje li Apros API rate limits

**Why This Matters**

Full sync na 10k artikala + varijacija + slike + atributi = memorijski i vremenski intenzivna operacija. Ako delta nije podržan, cron arhitektura mora biti dizajnirana za batch/streaming od početka. P2-01 operativni rizik.

**Can Implementation Proceed Without This?**

PARTIALLY — Pull model s full sync može biti implementiran odmah. Delta je performansna optimizacija, ne arhitekturalni prerekvizit.

**Required Next Action**

Validate with Apros — podrška za delta sync i API rate limits.

---

## AP-06

**Originalno pitanje**

URL, metoda i format payload-a za order endpoint. Vraća li Apros order ID u responsu? Je li endpoint idempotent?

**Current Status**

PARTIALLY ANSWERED

**Confidence Level**

MEDIUM

**Evidence Sources**

- Production Code (`order.php`, `CurlClient.php`)
- Plugin Analysis (Sekcija 9, Order Export Architecture)
- Developer Feedback (PE-02 — B2C implementacija koristila slanje narudžbi)

**What We Know**

- B2C order endpoint: `/order/create` (POST)
- B2C success response format: `[{"numberErp": "12345"}]` — Apros order ID se vraća (potvrđeno)
- B2C error response format: `[{"result": "Error", "message": "..."}]` (potvrđeno)
- B2C core payload struktura poznata iz koda (items, billing, shipping, paymentTypeId, shippingMethodId)
- Client-side idempotency postoji u B2C: `_erp_sync_status` meta guard (ne Apros-side garancija)
- Apros order ID se čuva — ovo je signal da idempotency guard postoji barem na WC strani

**What We Do NOT Know**

- Je li B2B order endpoint isti URL ili drugačiji
- Prihvaća li Apros B2B-specifična polja: `sif_kup`, `salesLocationId`, `deliveryLocationId`, `billingOib`
- Je li Apros endpoint server-side idempotent (može li se ista narudžba poslati dva puta bez duplikata)
- Koji su obavezni vs. opcionalni B2B order payload fields

**Why This Matters**

Order export je highest-risk operacija. Duplirane narudžbe u ERP-u su poslovni incident. Bez poznatih B2B polja, order export builder ne može biti implementiran. P0-02 bloker.

**Can Implementation Proceed Without This?**

NO

**Required Next Action**

Validate with Apros + Need payload example — B2B order payload primjer koji Apros prihvaća.

---

## AP-07

**Originalno pitanje**

Format i polja liste dostavnih lokacija po partneru. Može li partner imati više lokacija? Koja je default? Na koji trigger se ažurira?

**Current Status**

PARTIALLY ANSWERED

**Confidence Level**

LOW

**Evidence Sources**

- Discovery Findings (NC-03 — endpoint tip potvrđen; NC-04 — ugovorni uvjeti struktura djelomično opisana)

**What We Know**

- Endpoint tip potvrđen (NC-03): "Lista primatelja / dostavnih lokacija po partneru (s ugovornim uvjetima i cjenicima)" postoji
- NC-04 potvrđuje da ugovorni uvjeti sadrže: `sif_kup` + brand + postotak rabata za domaće partnere

**What We Do NOT Know**

- Točan endpoint URL
- Polja po lokaciji (naziv, adresa, grad, poštanski broj, zemlja, Apros location ID)
- Označuje li Apros default lokaciju za partnera
- Može li partner imati jednu ili više lokacija (utječe na storage model)
- Trigger za ažuriranje lokacija: approval webhook? periodični sync? on-demand?
- Je li delivery location ID stabilan između sync ciklusa (V-11)

**Why This Matters**

Direktno određuje storage model (user meta vs. custom post type) i checkout UI za odabir adrese dostave. Ako partner može imati mnogo lokacija, user meta nije primjeren. Faza 3 bloker (AP-04 u blueprint Sekciji 9).

**Can Implementation Proceed Without This?**

NO

**Required Next Action**

Validate with Apros + Need payload example — realni delivery locations response za jednog partnera.

---

## AP-08

**Originalno pitanje**

Stižu li `advance_only` i free shipping flag u approval webhook-u ili zasebno? Mogu li se naknadno promijeniti za aktivnog partnera?

**Current Status**

OPEN

**Confidence Level**

LOW

**Evidence Sources**

- Discovery Findings (flagovi postoje kao koncepti)
- Architecture Validation Audit (CO-05 — identificirana kao gap u dokumentima)
- Adaptation Blueprint (Data Ownership Matrix — "Apros → WC (pri approval)" — nevalidirano)

**What We Know**

- `advance_only` i `free_shipping` flagovi postoje kao koncepti u projektu
- Blueprint tretira ih kao podatke koji dolaze pri approval-u — ali ovo je pretpostavka, ne potvrda
- CO-05 identificira gap: postoji razlika između "kako dolazi?" i pretpostavke "dolazi pri approval"

**What We Do NOT Know**

- Kako i kada ovi flagovi dolaze do WC-a (approval webhook? zasebni sync?)
- Mogu li se promijeniti za aktivnog partnera i koji je mehanizam ažuriranja
- Format i naziv polja u Apros payloadu

**Why This Matters**

Checkout payment restrictions ovise o `advance_only` flagu. Ako se flag može promijeniti za aktivnog partnera bez novog webhook poziva, WC ne može garantirati konzistentne payment restrictions. P2-02 operativni rizik; P1-05 ako nema mehanizma ažuriranja.

**Can Implementation Proceed Without This?**

NO (Faza 3 bloker — payment restriction logika ne može biti finalizirana)

**Required Next Action**

Validate with Apros — isporuka i mehanizam ažuriranja oba flaga.

---

## AP-09

**Originalno pitanje**

Auth metoda za WP → Apros pozive (API key, OAuth, Basic auth). Postoji li IP whitelist?

**Current Status**

PARTIALLY ANSWERED

**Confidence Level**

LOW

**Evidence Sources**

- Production Code (`CurlClient.php`, `BaseProvider.php`, `order.php`)
- Plugin Analysis (Sekcija 4, Authentication Model — KRITIČNI NALAZ)
- Architecture Validation Audit (EG-03, P0-03, CO-03)

**What We Know**

- KRITIČNI NALAZ: `CurlClient.php` u produkciji ne šalje nikakve auth headere
- `username` i `password` polja postoje u ACF konfiguraciji i `BaseProvider` metodama — ali `AprosProvider::fetch()` ih nikad ne poziva niti prosljeđuje `CurlClient`-u
- `order.php` koristi `wp_remote_post()` bez autentikacije — isti pattern
- Zaključak: ili Apros B2C API je zaštićen IP whitelistom, ili ne zahtijeva auth, ili auth postoji na mrežnoj razini
- Developer feedback (Milenko Stojaković) implicira da je prethodni B2C radio bez eksplicitnog auth headera

**What We Do NOT Know**

- Zašto B2C radi bez auth headera (IP whitelist? Otvoreni API? Mrežni auth?)
- Hoće li B2B API zahtijevati drugačiji auth model od B2C-a
- Format auth mehanizma za B2B (API key? OAuth 2.0? Basic auth? JWT? Bearer token?)
- Postoji li IP whitelist na Apros strani koji ograničava inbound pozive
- Jesu li odvojeni credentials za read (products) vs. write (orders)?

**Why This Matters**

Svaki outbound API poziv prema Apros-u je blokiran dok auth model nije poznat. Ako B2B zahtijeva OAuth 2.0, implementacijski scope se znatno povećava (token refresh, credential rotation). P0-03 bloker.

**Can Implementation Proceed Without This?**

NO — Faza 1 može kreirati CurlClient s auth header inject mehanizmom kao placeholder; ali nijedan realni API poziv ne može biti testiran ili finaliziran.

**Required Next Action**

Validate with Apros — auth mehanizam za B2B API pristup.

---

## AP-10

**Originalno pitanje**

Kada Apros rezerviše stanje — pri dodavanju u korpu, checkoutu ili tek ERP potvrdom?

**Current Status**

OPEN

**Confidence Level**

LOW

**Evidence Sources**

- Developer Feedback (PE-06 — "rukovanje skladištima vjerojatno interno unutar Apros-a")
- Discovery Findings (stock model opisuje prikaz, ne rezervaciju)

**What We Know**

- WC nativno: stock se rezervira pri kreiranju narudžbe (na checkoutu)
- Developer signal (PE-06): warehouse handling je vjerojatno interno unutar Apros-a (radna pretpostavka, ne potvrda)
- 4 skladišta s per-brand matičnim mappingom su potvrđena poslovna pravila

**What We Do NOT Know**

- Kada točno Apros rezervira stock (dodavanje u korpu? checkout? ERP potvrda?)
- Utječe li Apros rezervacija na WC stock messaging
- Je li real-time stock provjera potrebna prije checkoutа

**Why This Matters**

Određuje stock messaging UX u košarici i na checkoutu. Edge case: korisnik stavi u korpu, drugi kupac naruči isti artikal, stock dostupan za prvog ali Apros ga je rezervirao za drugog. Nije bloker za implementaciju, ali utječe na checkout UX dizajn.

**Can Implementation Proceed Without This?**

PARTIALLY — može se implementirati s WC default ponašanjem (rezervacija pri kreiranju narudžbe); edge case UX se može revidirati naknadno.

**Required Next Action**

Validate with Apros — operativni detalj, nizak prioritet.

---

## AP-11

**Originalno pitanje**

Šalje li Apros EAN/barcode u `articleList/get`?

**Current Status**

ANSWERED

**Confidence Level**

HIGH

**Evidence Sources**

- Production Code (`AprosProvider.php`, `ProductToImport.php`)
- Plugin Analysis (Sekcija 3, Konzumirani payload fields; Appendix, Meta Fields)
- Discovery Findings (NC-02 — B2B proširuje isti B2C endpoint)

**What We Know**

- `barcode` polje postoji u `articleList/get` payloadu (potvrđeno produkcijskim kodom)
- Čuva se kao WC `global_unique_id` (EAN/GTIN standard)
- Čuva se i kao `_erp_barcode` postmeta
- NC-02 potvrđuje B2B proširuje isti endpoint → barcode polje je prisutno

**What We Do NOT Know**

- Ništa kritično. Polje je potvrđeno.

**Why This Matters**

Omogućava B2B search by barcode. Polje je dostupno — implementacija može to iskoristiti.

**Can Implementation Proceed Without This?**

YES

**Required Next Action**

No action required.

---

## AP-12

**Originalno pitanje**

Dozvoljava li Apros narudžbe za artikle van zalihe (backordering)?

**Current Status**

PARTIALLY ANSWERED

**Confidence Level**

LOW

**Evidence Sources**

- Discovery Findings (potvrđena poslovna pravila — default Jekaa model)
- Stakeholder Question Matrix (Sekcija 1, "Potvrđene činjenice")
- Developer Feedback (PE-06 — warehouse handling interno u Apros-u)

**What We Know**

- Default ponašanje je potvrđeno od klijenta: artikal se prikazuje bez mogućnosti narudžbe kad je van zalihe (Jekaa model)
- Dream Point je potvrdio ovo kao željeno ponašanje
- WC konfiguracija: backordering disabled je planirani default

**What We Do NOT Know**

- Podržava li Apros API narudžbe za artikle koji imaju stock ≤ 0
- Postoje li kategorije gdje backordering može biti dozvoljen
- Signalizira li Apros per-artikal backorder sposobnost

**Why This Matters**

Određuje out-of-stock UX. Default je poznat i može biti implementiran. Edge case za specifične kategorije ostaje otvoren.

**Can Implementation Proceed Without This?**

PARTIALLY — može se implementirati default no-backorder ponašanje; specijalni slučajevi zahtijevaju Apros potvrdu.

**Required Next Action**

Validate with Apros — nizak prioritet; default je implementabilan odmah.

---

## AP-13

**Originalno pitanje**

Koji mehanizam ažurira rabat za aktivnog partnera (novi webhook ili periodični sync)?

**Current Status**

OPEN

**Confidence Level**

LOW

**Evidence Sources**

- Architecture Validation Audit (CO-05 — gap identificiran; P2-02 — operativni rizik)
- Discovery Findings (NC-04 — ugovorni uvjeti opisani, ne mehanizam promjene)
- Adaptation Blueprint (Data Ownership Matrix — "Apros → WC (pri approval / periodic)" — oba kao pretpostavka)

**What We Know**

- Ugovorni uvjeti (rabat po brandu) dolaze iz Apros-a — Apros je izvor istine
- Pri odobrenju: rabatni podaci dolaze u/nakon approval procesa
- Blueprint Data Ownership Matrix: "pri approval / periodic" — oba su pretpostavka, ne potvrda
- Promjene rabata su "iznimno rijetke" prema discovery, ali moguće

**What We Do NOT Know**

- Točan mehanizam za ažuriranje rabata za aktivnog partnera
- Postoji li webhook za promjenu partner podataka kod aktivnih partnera
- Frekvencija periodičnog partner sync-a ako postoji
- Što se dogodi ako rabat promijeni dok je artikal u aktivnoj korpi

**Why This Matters**

Ako se rabat mijenja samo pri odobrenju, promjena kreditnih uvjeta za aktivnog partnera neće se reflektirati u WC dok admin ručno ne interveni. Edge case, ali realan poslovni scenarij. P2-02 operativni rizik.

**Can Implementation Proceed Without This?**

PARTIALLY — može se implementirati s approval-time sync; mehanizam ažuriranja je operativna poboljšica, ne arhitekturalni prerekvizit.

**Required Next Action**

Validate with Apros — srednji prioritet; implementacija se može početi bez odgovora, ali mora biti riješen za produkcijsku stabilnost.

---

## AP-14

**Originalno pitanje**

Šalje li Apros status update natrag u WC (potvrđeno, isporučeno, stornirano)?

**Current Status**

PARTIALLY ANSWERED

**Confidence Level**

MEDIUM

**Evidence Sources**

- Stakeholder Question Matrix (Sekcija 1, "Narudžbe: jednosmjerne" — potvrđena intencija)
- Discovery Findings (potvrđene poslovne domene)
- Architecture Validation Audit (P2-03 — intent potvrđen, Apros-side confirmation missing)

**What We Know**

- Intent je jednosmjeran (WC → Apros): potvrđeno kao poslovna intencija iz više izvora
- Korisnici vide pregled narudžbi + PDF stamp (Jekaa model) — nema spomena live status update-a
- Finansijska vidljivost: "fakture, dugovanja, kreditni limit nisu eksplicitni zahtjev"

**What We Do NOT Know**

- Šalje li Apros tehnički ikakav status update natrag u WC (nije potvrđeno od Apros-a eksplicitno)
- Ako šalje: koji statusi (confirmed, delivered, cancelled) i koji format

**Why This Matters**

My Account UX za prikaz statusa narudžbe. Jednosmjeran prikaz je znatno jednostavniji za implementaciju. Ako Apros šalje status updates, potreban je inbound WP REST endpoint. P2-03 operativni rizik.

**Can Implementation Proceed Without This?**

PARTIALLY — može se implementirati jednosmjeran model; inbound status endpoint se može dodati naknadno ako Apros potvrdi da šalje.

**Required Next Action**

Validate with Apros — nizak prioritet; jednosmjeran model je siguran default.

---

## Summary Dashboard

| AP-ID | Status | Confidence | Can Proceed | Next Action |
|-------|--------|-----------|-------------|-------------|
| AP-01 | PARTIALLY ANSWERED | LOW | NO | Need payload example + Validate with Apros |
| AP-02 | ANSWERED | HIGH | YES | No action required |
| AP-03 | OPEN | LOW | NO | Validate with Apros |
| AP-04 | PARTIALLY ANSWERED | MEDIUM | YES (Faza 1) | Need production verification (Faza 2) |
| AP-05 | PARTIALLY ANSWERED | MEDIUM | PARTIALLY | Validate with Apros |
| AP-06 | PARTIALLY ANSWERED | MEDIUM | NO | Need payload example + Validate with Apros |
| AP-07 | PARTIALLY ANSWERED | LOW | NO | Need payload example + Validate with Apros |
| AP-08 | OPEN | LOW | NO | Validate with Apros |
| AP-09 | PARTIALLY ANSWERED | LOW | NO | Validate with Apros |
| AP-10 | OPEN | LOW | PARTIALLY | Validate with Apros |
| AP-11 | ANSWERED | HIGH | YES | No action required |
| AP-12 | PARTIALLY ANSWERED | LOW | PARTIALLY | Validate with Apros |
| AP-13 | OPEN | LOW | PARTIALLY | Validate with Apros |
| AP-14 | PARTIALLY ANSWERED | MEDIUM | PARTIALLY | Validate with Apros |

---

## Remaining Questions For Apros

Samo pitanja koja zahtijevaju direktnu Apros potvrdu, preformulirana u najkraći mogući oblik.

**Prioritet 1 — P0 blokeri (arhitektura nije moguća bez odgovora)**

1. **(AP-01)** Je li `wholesalePrice` u `articleList/get` ista vrijednost za sve partnere ili per-partner? Dostavite realni payload primjer s jednim domaćim partnerom (s rabatom) i jednim stranim (s finalnom cijenom).

2. **(AP-09)** Kojom metodom WP autentificira pozive prema Apros-u? Postoji li IP whitelist? Zašto B2C plugin ne šalje auth headere?

3. **(AP-06)** Je li B2B order endpoint isti kao B2C (`/order/create`)? Prihvaća li `sif_kup`, `salesLocationId` i `deliveryLocationId`? Je li endpoint idempotent?

**Prioritet 2 — P1 blokeri (Faze 3–5 ne mogu početi bez odgovora)**

4. **(AP-03)** Šalje li Apros approval webhook `sif_kup`, rabate i `advance_only` u istom pozivu ili zasebno?

5. **(AP-07)** Format, polja i trigger za listu dostavnih lokacija po partneru. Može li partner imati više lokacija? Je li Apros location ID stabilan između sync ciklusa?

6. **(AP-08)** Stižu li `advance_only` i `free_shipping` u approval webhook-u? Može li se promijeniti za aktivnog partnera i koji je mehanizam ažuriranja?

**Prioritet 3 — P2 operativni (implementacija može početi, ali ovo utječe na produkcijsku stabilnost)**

7. **(AP-05)** Postoji li delta sync endpoint za artikle (changed-since timestamp ili change feed)? Koji je maksimalni broj artikala po API responsu? Postoje li rate limits?

8. **(AP-13)** Koji mehanizam ažurira rabat za aktivnog partnera — novi webhook ili periodični partner sync?

9. **(AP-10)** Kada Apros rezervira stanje — pri checkoutu ili tek pri ERP potvrdi narudžbe?

10. **(AP-12)** Može li Apros primiti narudžbu za artikal s nultim stanjem?

11. **(AP-14)** Šalje li Apros status update natrag prema WC (potvrđeno, isporučeno, stornirano)?

---

## Remaining Questions Requiring Payload Examples

Pitanja koja se ne mogu riješiti bez realnih payload primjera.

| AP-ID | Što je potrebno |
|-------|----------------|
| **AP-01** | Realni `articleList/get` response za domaćeg partnera + realni `ugovorni uvjeti` response — s `wholesalePrice` i Rabat 1 vrijednostima u istom prikazu za istog partnera |
| **AP-06** | B2B order payload primjer koji Apros prihvaća — s `sif_kup`, `salesLocationId`, `deliveryLocationId`, `billingOib`; + success i error response primjer |
| **AP-07** | Realni delivery locations endpoint response za jednog partnera — s Apros location ID-em, svim adresnim poljima, i oznaka defaulta ako postoji |
| **AP-04** | B2B `articleList/get` i `articleVariationList/get` payload primjer s `b2bArticle` i `wholesalePrice` poljem (Faza 2, nije bloker za Fazu 1) |

---

## Remaining Internal Validation Items

Pitanja koja se mogu riješiti interno bez Apros sesije.

| AP-ID | Što je potrebno | Vlasnik |
|-------|----------------|---------|
| **AP-04** | Potvrda B2B payload strukture usporedbom s B2C produkcijskim endpointom — moguće kroz Milenko Stojaković | Dev / Integrator |
| **DP-02** (nije AP, ali blokira AP-06) | Ekstrakcija sales location routing logike iz starog B2B sustava — jedini poznati izvor je Josip / ZGData | Dream Point / Josip |

---

## Final Classification

**Answered:** 2 (AP-02, AP-11)

**Partially Answered:** 8 (AP-01, AP-04, AP-05, AP-06, AP-07, AP-09, AP-12, AP-14)

**Open:** 4 (AP-03, AP-08, AP-10, AP-13)

**Out of Scope:** 0

---

### Most Critical Remaining Unknowns (rang po blokirajućoj težini)

| Rang | AP-ID | Bloker | Razlog |
|------|-------|--------|--------|
| 1 | **AP-01** | P0 — Pricing engine | Konfliktni iskustveni signali; jedini put razrješenja je realni payload primjer; pogrešan odabir = kompletan pricing rework |
| 2 | **AP-09** | P0 — Svi outbound API pozivi | CurlClient ne šalje auth; nijedan API poziv ne može biti finaliziran dok auth model nije poznat; OAuth scenarij multiplicira scope |
| 3 | **AP-06** | P0 — Order export | B2B-specifična polja nevalidirana; idempotency nepoznat; duplirane narudžbe = poslovni incident |
| 4 | **AP-03** | P1 — Onboarding flow | Bez znanja o webhook body-u, Faza 3 partner sync ne može biti arhitekturalno odlučen |
| 5 | **AP-07** | P1 — Checkout UI | Delivery location storage model i checkout UI blokirani |
| 6 | **AP-08** | P1 — Payment restrictions | `advance_only` mehanizam nepoznat; payment restrictions ne mogu biti finalizirane |
| 7 | **AP-05** | P2 — Performance | Full sync na 10k artikala bez delta = memorijski intenzivna operacija svaki sat |
| 8 | **AP-13** | P2 — Operativna stabilnost | Promjena rabata za aktivnog partnera bez mehanizma ažuriranja = zastarjele cijene u WC-u |
| 9 | **AP-10** | P2 — UX edge case | Stock rezervacija timing utječe na out-of-stock messaging pri simultanim narudžbama |
| 10 | **AP-12** | P2 — UX edge case | Backorder ponašanje za specifične kategorije nepoznato; default je implementabilan |
| 11 | **AP-14** | P2 — My Account UX | Jednosmjeran default je implementabilan; dvosmjeran zahtijeva novi endpoint |
| 12 | **AP-04** | LOW — Faza 2 validacija | Rizik drastično smanjen B2C produkcijskim kodom; B2B payload potvrda potrebna ali ne blokira Fazu 1 |
