# Apros Question Resolution Matrix — DP-B2B

**Datum:** 2026-06-08 | **Ažurirano:** 2026-07-03 (Documentation Reconciliation — Workshop Follow-up)
**Status dokumenta:** Autoritativni izvor istine za AP-01 – AP-14  
**Evidencijska hijerarhija:** direktan Apros odgovor → produkcijski kod → produkcijsko ponašanje → developer feedback → discovery nalazi → workshop pretpostavke

> Ovaj dokument određuje koji AP-od zahtijevaju Apros sesiju, koji su zatvoreni, i koji se mogu riješiti interno.  
> Ne sadrži pretpostavke koje nisu eksplicitno označene. Ne invencija.

> **Revizija 2026-07-02:** Apros je direktno odgovorio na AP-01 (pricing), AP-09 (auth), AP-06 (order endpoint), AP-03 (approval flow) i AP-07 (delivery locations). Ovi odgovori imaju najvišu evidencijsku razinu — iznad produkcijskog koda i developer feedback-a. Puni payload primjeri i dalje nedostaju za AP-01, AP-06, AP-07 — vidi `docs/apros-session-final-pack.md` → "Still Required From Apros".

---

## AP-01

**Originalno pitanje**

Potvrdite egzaktnu vezu između `wholesalePrice`, Rabat 1 i finalne B2B cijene za kupca. Je li `wholesalePrice` ista za sve partnere (bazna/katalog cijena) ili per-partner (Apros preračunava po kupcu)? Primjenjuje li se Rabat 1 uvijek ili samo za određene partnere? Dostavite realni payload primjer za partnera s Rabat 1 i za partnera bez.

**Current Status**

PARTIALLY RESOLVED (payload pending)

**Confidence Level**

MEDIUM (arhitektura); LOW (payload)

**Evidence Sources**

- **Direktan Apros odgovor (2026-07-02)** — najviša evidencijska razina
- Developer Feedback (Milenko Stojaković, 2026-06-03)
- Discovery Findings (Leo Benkek email, NC-04)
- Adaptation Blueprint (Sekcija 5, konflikt dokumentiran)
- Architecture Validation Audit (EG-02, P0-01)

**What We Know**

- **Apros je potvrdio (2026-07-02):** Domaći kupci — `wholesalePrice` je bazna veleprodajna cijena; Rabat 1 je postotni popust po partneru/brandu; finalna cijena = `wholesalePrice − Rabat 1`. Ovo je **Model A**.
- **Apros je potvrdio (2026-07-02):** Strani kupci — nema Rabat 1 mehanizma; finalna neto cijena dolazi direktno iz `countryPriceList`; PDV tretman ovisi o partnerovoj pravnoj/poreznoj kategoriji. Ovo je **Model C**.
- Ovaj odgovor razrješava raniji konflikt u korist Leo Benkek interpretacije za domaći segment — Milenko Stojakovićevo zapažanje (Model B) odnosilo se na B2C kontekst i ne primjenjuje se na B2B
- `wholesalePrice` je proširenje dodano na postojeći B2C `articleList/get` endpoint (NC-02 — potvrđeno)
- Listing prikazuje neto cijenu s rabatom, bez PDV-a (potvrđena poslovna pravila)
- Košarica prikazuje puni iznos s PDV-om (potvrđena poslovna pravila)

**What We Do NOT Know**

- Egzaktni field nazivi i realne vrijednosti u `articleList/get` + ugovorni uvjeti responsu za istog domaćeg partnera
- Format i egzaktna polja `countryPriceList` payloada za stranog partnera
- Format `partnerBrandDiscountList` (Rabat 1 isporuka)
- Je li Rabat 1 obavezan za sve domaće partnere ili opcionalan u pojedinim slučajevima

**Why This Matters**

Pricing engine je nova komponenta bez presedana u B2C pluginu. Arhitekturalni model je sada poznat (Model A domaći, Model C strani), čime je najveći implementacijski rizik uklonjen. Bez realnog payload primjera implementacija se i dalje ne može finalizirati — pogrešna pretpostavka o field nazivima ili strukturi znači rework pri integraciji, ne kompletan arhitekturalni rework.

**Can Implementation Proceed Without This?**

PARTIALLY — arhitekturalni dizajn (Korak 11, pricing engine) može početi na osnovu potvrđenog modela; finalna implementacija i QA zahtijevaju realni payload primjer.

**Required Next Action**

Need payload example — realni `articleList/get` response za domaćeg partnera + realni ugovorni uvjeti response za istog partnera (Rabat 1), i `countryPriceList` primjer za stranog partnera.

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

RECLASSIFIED (polling/import model — nema potvrđenog webhooka)

**Confidence Level**

HIGH

**Evidence Sources**

- **Direktan Apros odgovor (2026-07-02)** — najviša evidencijska razina
- Discovery Findings (NC-05 — raniji konceptualni tok, sada djelomično zamijenjen)
- Adaptation Blueprint (Sekcija 4, Approval Lifecycle — dizajn zahtijeva reviziju)
- Architecture Validation Audit (CO-04 — protivrječje između checklist i blueprint prioritizacije)

**What We Know**

- **Apros je potvrdio (2026-07-02): ne postoji approval webhook.** Pretpostavka o inbound webhook pozivu prema WP se povlači.
- Potvrđeni tok: web registracija → email notifikacija → ručno kreiranje partnera u Apros-u → `B2B KUPAC = DA` → partner se pojavljuje na partner list endpointu
- Partner sinkronizacija mora biti **periodic polling/import**, ne webhook-driven
- `sif_kup` je u vlasništvu Apros-a — WC čuva kopiju preko polling job-a
- Raniji planirani WP inbound endpoint `POST /wp-json/dreampoint-b2b/v1/approve-partner` **se ne implementira u ovom obliku** — zamjenjuje ga cron-based partner sync job

**What We Do NOT Know**

- Frekvencija na kojoj partner list endpoint treba biti polled da bi se novi/promijenjeni `B2B KUPAC = DA` zapisi pravovremeno detektirali
- Postoji li mehanizam za detekciju promjena (delta/changed-since) na partner list endpointu ili je full-list polling jedina opcija

**Why This Matters**

Ovo je arhitekturalna promjena za Fazu 3: partner sync se dizajnira kao cron-based polling/import job umjesto inbound REST webhook receivera. Utječe na `docs/b2b-erp-migration-plan.md` Korak 9/10 i `docs/b2b-erp-adaptation-blueprint.md` Sekciju 4 (Approval Lifecycle) — oba moraju biti ažurirana da odražavaju polling model.

**Can Implementation Proceed Without This?**

YES — arhitektura je sada poznata (polling/import); implementacija partner sync job-a može biti dizajnirana. Preostaje samo pitanje frekvencije/delta detekcije, što nije arhitekturalni bloker.

**Required Next Action**

No further Apros validation required za osnovnu arhitekturu. Opcionalno: potvrditi postoji li delta/changed-since mehanizam na partner list endpointu (performance optimizacija, ne bloker).

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

PARTIALLY RESOLVED (payload pending)

**Confidence Level**

MEDIUM

**Evidence Sources**

- **Direktan Apros odgovor (2026-07-02)** — obavezna polja potvrđena
- Production Code (`order.php`, `CurlClient.php`)
- Plugin Analysis (Sekcija 9, Order Export Architecture)
- Developer Feedback (PE-02 — B2C implementacija koristila slanje narudžbi)

**What We Know**

- **Apros je potvrdio (2026-07-02) obavezna polja:** `sif_kup`/`partnerId`, `partnerDeliveryLocationId`, stavke narudžbe s količinama
- B2C order endpoint: `/order/create` (POST)
- B2C success response format: `[{"numberErp": "12345"}]` — Apros order ID se vraća (potvrđeno, B2C)
- B2C error response format: `[{"result": "Error", "message": "..."}]` (potvrđeno, B2C)
- B2C core payload struktura poznata iz koda (items, billing, shipping, paymentTypeId, shippingMethodId)
- Client-side idempotency postoji u B2C: `_erp_sync_status` meta guard (ne Apros-side garancija)

**What We Do NOT Know**

- Je li B2B order endpoint isti URL kao B2C ili drugačiji
- Je li success/error response format za B2B identičan B2C formatu
- Je li Apros endpoint server-side idempotent (može li se ista narudžba poslati dva puta bez duplikata)
- Koja su opcionalna polja (npr. `billingOib`) uz potvrđena obavezna polja
- **Invoice splitting mehanizam — status: PARTIALLY RESOLVED (zaseban aspekt AP-06).** Nije adresiran Apros odgovorom od 2026-07-02. **Trenutna radna pretpostavka: Apros vrši interno segmentiranje faktura** — WC šalje jednu narudžbu, Apros interno odlučuje o razdvajanju na više faktura. Ako se pretpostavka pokaže netočnom, AP-06 zahtijeva formalno proširenje. Vidi `docs/project-status-matrix.md` Sekciju 1.8 i DP-D4.

**Why This Matters**

Order export je highest-risk operacija. Duplirane narudžbe u ERP-u su poslovni incident. Obavezna polja su sada poznata, što omogućava payload builder dizajn; bez URL-a, response formata i idempotency potvrde implementacija se ne može finalizirati niti testirati protiv sandbox-a.

**Can Implementation Proceed Without This?**

PARTIALLY — payload builder dizajn (Korak 12) može početi na osnovu poznatih obaveznih polja; finalizacija i sandbox test zahtijevaju URL, response format i idempotency potvrdu.

**Required Next Action**

Need payload example — realni B2B order request + success/error response primjer koji Apros prihvaća/vraća; potvrda idempotency ponašanja.

---

## AP-07

**Originalno pitanje**

Format i polja liste dostavnih lokacija po partneru. Može li partner imati više lokacija? Koja je default? Na koji trigger se ažurira?

**Current Status**

PARTIALLY RESOLVED (payload pending)

**Confidence Level**

MEDIUM

**Evidence Sources**

- **Direktan Apros odgovor (2026-07-02)** — endpoint naziv i ključni model potvrđeni
- Discovery Findings (NC-03 — endpoint tip potvrđen; NC-04 — ugovorni uvjeti struktura djelomično opisana)

**What We Know**

- **Apros je potvrdio (2026-07-02):** Endpoint `partnerDeliveryLocationList`. Partner može imati više dostavnih lokacija. **Ne postoji default lokacija** — korisnik bira lokaciju pri naručivanju.
- Endpoint tip ranije potvrđen (NC-03): "Lista primatelja / dostavnih lokacija po partneru (s ugovornim uvjetima i cjenicima)"
- NC-04 potvrđuje da ugovorni uvjeti sadrže: `sif_kup` + brand + postotak rabata za domaće partnere

**What We Do NOT Know**

- Egzaktna polja po lokaciji (naziv, adresa, grad, poštanski broj, zemlja, Apros location ID format)
- Trigger za ažuriranje lokacija: periodični sync? on-demand poziv pri checkoutu?
- Je li delivery location ID stabilan između sync ciklusa (V-11) — nije adresirano ovim odgovorom

**Why This Matters**

Direktno određuje storage model i checkout UI za odabir adrese dostave. Potvrđeno da partner može imati više lokacija i da nema defaulta — checkout MORA prisiliti eksplicitan odabir lokacije od korisnika (nema fallback na "default"). Finalna storage struktura ovisi o poljima iz payload primjera.

**Can Implementation Proceed Without This?**

PARTIALLY — checkout UX zahtjev (eksplicitan odabir, nema defaulta) i osnovni storage pristup (array lokacija) mogu biti dizajnirani; finalna polja i trigger mehanizam zahtijevaju payload primjer.

**Required Next Action**

Need payload example — realni `partnerDeliveryLocationList` response za jednog partnera s više lokacija.

---

## AP-08

**Originalno pitanje**

Stižu li `advance_only` i free shipping flag u approval webhook-u ili zasebno? Mogu li se naknadno promijeniti za aktivnog partnera?

**Current Status**

OUT OF SCOPE

**Confidence Level**

N/A

**Evidence Sources**

- **Odluka nakon Apros odgovora (2026-07-02)** — pitanje se temeljilo na pretpostavci approval webhooka koji ne postoji (vidi AP-03)
- Discovery Findings (flagovi postoje kao koncepti)
- Architecture Validation Audit (CO-05 — identificirana kao gap u dokumentima)

**What We Know**

- `advance_only` i `free_shipping` flagovi postoje kao koncepti u projektu, ali isporučni mehanizam na kojem se pitanje temeljilo (approval webhook) ne postoji
- Reklasificirano kao izvan scope-a za inicijalnu implementaciju

**What We Do NOT Know**

- Kako i kada bi ovi flagovi eventualno dolazili do WC-a u polling/import modelu — nije istraženo jer je izvan scope-a

**Why This Matters**

Payment restrictions bazirane na `advance_only` nisu dio inicijalne implementacije. Flag ostaje dokumentiran u arhitekturi (storage polje) kao potencijalno buduće proširenje, ali se ne validira niti implementira u ovoj fazi.

**Can Implementation Proceed Without This?**

YES — nije bloker; funkcionalnost je izvan scope-a

**Required Next Action**

No action required. Ako se `advance_only` funkcionalnost naknadno vrati u scope, potreban je zaseban estimate i nova Apros validacija u kontekstu polling/import partner sync arhitekture.

---

## AP-09

**Originalno pitanje**

Auth metoda za WP → Apros pozive (API key, OAuth, Basic auth). Postoji li IP whitelist?

**Current Status**

RESOLVED

**Confidence Level**

HIGH

**Evidence Sources**

- **Direktan Apros odgovor (2026-07-02)** — auth metoda potvrđena
- Production Code (`CurlClient.php`, `BaseProvider.php`, `order.php`)
- Plugin Analysis (Sekcija 4, Authentication Model — ažurirano)
- Architecture Validation Audit (EG-03, P0-03, CO-03)

**What We Know**

- **Apros je potvrdio (2026-07-02): auth metoda je API Key**, isti pristup kao postojeća Jekaa B2C integracija
- Ranije KRITIČNI NALAZ: `CurlClient.php` u produkciji ne šalje nikakve auth headere unatoč tome što `username`/`password` polja postoje u konfiguraciji — ovo ostaje otvoreno pitanje za B2C kod (mogući previd ili mrežni auth sloj koji API Key čini redundantnim u B2C), ali ne mijenja B2B zahtjev: B2B implementacija mora slati API Key header
- `order.php` koristi `wp_remote_post()` bez autentikacije — isti B2C pattern, isto zapažanje

**What We Do NOT Know**

- Zašto B2C plugin ne šalje API Key unatoč potvrđenom auth modelu — nije kritično za B2B, ali vrijedi razjasniti radi izbjegavanja istog previda
- Odvojeni credentials za read (products) vs. write (orders)?
- Postoji li dodatno IP whitelist ograničenje uz API Key?

**Why This Matters**

Auth model kao arhitekturalni bloker je zatvoren — implementacija `CurlClient` auth header inject mehanizma (Korak 5/14) može biti finalizirana s API Key umjesto placeholder dizajna za više mogućih modela (OAuth 2.0 scenarij, koji bi značajno povećao scope, je isključen).

**Can Implementation Proceed Without This?**

YES — Korak 14 (auth implementacija) može biti finaliziran: `CurlClient::set_auth_header('ApiKey ' . $key)` pattern, credential storage u `wp-config.php` konstantama.

**Required Next Action**

No action required za osnovni auth model. Opcionalno razjasniti: odvojeni credentials read/write, dodatni IP whitelist zahtjev.

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

**Napomena (2026-07-03) — razlika od DP-B06:** Ovo pitanje se odnosi isključivo na Apros-stranu rezervacije (ERP interna logika). Odvojeno od ovoga, Dream Point je na internom workshopu spomenuo mogući zahtjev za 1-satnu **cart-level** rezervaciju na WooCommerce strani — to je nova, zasebna poslovna odluka praćena kao **DP-B06** u `docs/project-status-matrix.md` (Sekcija 3.B), ne mijenja status ili prioritet ovog AP-10 pitanja.

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
| AP-01 | PARTIALLY RESOLVED (payload pending) | MEDIUM/LOW | PARTIALLY | Need payload example |
| AP-02 | ANSWERED | HIGH | YES | No action required |
| AP-03 | RECLASSIFIED (polling/import) | HIGH | YES | No action required |
| AP-04 | PARTIALLY ANSWERED | MEDIUM | YES (Faza 1) | Need production verification (Faza 2) |
| AP-05 | PARTIALLY ANSWERED | MEDIUM | PARTIALLY | Validate with Apros |
| AP-06 | PARTIALLY RESOLVED (payload pending) | MEDIUM | PARTIALLY | Need payload example |
| AP-07 | PARTIALLY RESOLVED (payload pending) | MEDIUM | PARTIALLY | Need payload example |
| AP-08 | OUT OF SCOPE | N/A | YES | No action required |
| AP-09 | RESOLVED | HIGH | YES | No action required |
| AP-10 | OPEN | LOW | PARTIALLY | Validate with Apros |
| AP-11 | ANSWERED | HIGH | YES | No action required |
| AP-12 | PARTIALLY ANSWERED | LOW | PARTIALLY | Validate with Apros |
| AP-13 | OPEN | LOW | PARTIALLY | Validate with Apros |
| AP-14 | PARTIALLY ANSWERED | MEDIUM | PARTIALLY | Validate with Apros |

---

## Remaining Questions For Apros

Samo pitanja koja zahtijevaju direktnu Apros potvrdu, preformulirana u najkraći mogući oblik.

**✅ Zatvoreno odgovorom od 2026-07-02:** AP-09 (auth — API Key), AP-03 (approval flow — polling/import, reklasificirano). AP-08 je reklasificiran kao OUT OF SCOPE.

**Prioritet 1 — P0 payload primjeri (arhitektura poznata, finalizacija nije moguća bez payloada)**

1. **(AP-01)** Arhitektura potvrđena (Model A domaći, Model C strani). Potreban realni payload primjer: `articleList/get` + ugovorni uvjeti za domaćeg partnera, `countryPriceList` za stranog partnera.

2. **(AP-06)** Obavezna polja potvrđena (`sif_kup`/`partnerId`, `partnerDeliveryLocationId`, items+količine). Potreban: URL, HTTP metoda, success/error response format, potvrda idempotency.

**Prioritet 2 — P1 payload primjer (checkout/storage dizajn poznat, finalizacija čeka payload)**

3. **(AP-07)** Model potvrđen (više lokacija, nema defaulta, endpoint `partnerDeliveryLocationList`). Potreban realni payload primjer s adresnim poljima i Apros location ID formatom.

**Prioritet 3 — P2 operativni (implementacija može početi, ali ovo utječe na produkcijsku stabilnost)**

4. **(AP-05)** Postoji li delta sync endpoint za artikle (changed-since timestamp ili change feed)? Koji je maksimalni broj artikala po API responsu? Postoje li rate limits?

5. **(AP-13)** Koji mehanizam ažurira rabat za aktivnog partnera — periodični partner sync (polling/import)?

6. **(AP-10)** Kada Apros rezervira stanje — pri checkoutu ili tek pri ERP potvrdi narudžbe?

7. **(AP-12)** Može li Apros primiti narudžbu za artikal s nultim stanjem?

8. **(AP-14)** Šalje li Apros status update natrag prema WC (potvrđeno, isporučeno, stornirano)?

---

## Remaining Questions Requiring Payload Examples

Pitanja koja se ne mogu riješiti bez realnih payload primjera. Puna lista i traženi format: `docs/apros-session-final-pack.md` → "Still Required From Apros".

| AP-ID | Što je potrebno |
|-------|----------------|
| **AP-01** | Realni `articleList/get` response za domaćeg partnera + realni ugovorni uvjeti response (Rabat 1) — arhitektura poznata (Model A); + `countryPriceList` primjer za stranog partnera (Model C) |
| **AP-06** | B2B order payload primjer koji Apros prihvaća — s potvrđenim obaveznim poljima (`sif_kup`/`partnerId`, `partnerDeliveryLocationId`, items+količine) + success i error response primjer |
| **AP-07** | Realni `partnerDeliveryLocationList` response za jednog partnera s više lokacija — svim adresnim poljima (nema defaulta, potvrđeno) |
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

**Resolved / Answered:** 4 (AP-02, AP-03, AP-09, AP-11)

**Partially Resolved (payload pending):** 3 (AP-01, AP-06, AP-07)

**Partially Answered:** 4 (AP-04, AP-05, AP-12, AP-14)

**Open:** 2 (AP-10, AP-13)

**Out of Scope:** 1 (AP-08)

---

### Most Critical Remaining Unknowns (rang po preostaloj blokirajućoj težini, 2026-07-02)

| Rang | AP-ID | Bloker | Razlog |
|------|-------|--------|--------|
| 1 | **AP-01** | P0 → payload — Pricing engine finalizacija | Arhitektura razriješena (Model A/C); realni payload primjer je jedino preostalo za finalnu implementaciju |
| 2 | **AP-06** | P0 → payload — Order export finalizacija | Obavezna polja poznata; URL, response format i idempotency nedostaju; duplirane narudžbe = poslovni incident |
| 3 | **AP-07** | P1 → payload — Checkout UI finalizacija | Model poznat (više lokacija, nema defaulta); egzaktna polja i trigger nedostaju |
| 4 | **AP-05** | P2 — Performance | Full sync na 10k artikala bez delta = memorijski intenzivna operacija svaki sat |
| 5 | **AP-13** | P2 — Operativna stabilnost | Mehanizam ažuriranja rabata za aktivnog partnera u polling modelu nije eksplicitno potvrđen |
| 6 | **AP-10** | P2 — UX edge case | Stock rezervacija timing utječe na out-of-stock messaging pri simultanim narudžbama |
| 7 | **AP-12** | P2 — UX edge case | Backorder ponašanje za specifične kategorije nepoznato; default je implementabilan |
| 8 | **AP-14** | P2 — My Account UX | Jednosmjeran default je implementabilan; dvosmjeran zahtijeva novi endpoint |
| 9 | **AP-04** | LOW — Faza 2 validacija | Rizik drastično smanjen B2C produkcijskim kodom; B2B payload potvrda potrebna ali ne blokira Fazu 1 |

**Zatvoreno (više se ne pojavljuje kao bloker):** AP-02, AP-03 (polling/import razriješeno), AP-08 (out of scope), AP-09 (auth razriješeno), AP-11.
