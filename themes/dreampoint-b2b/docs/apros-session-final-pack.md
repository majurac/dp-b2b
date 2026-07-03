# Apros Session — Final Preparation Pack

**Datum:** 2026-06-08 | **Ažurirano:** 2026-07-03 (Documentation Reconciliation — Workshop Follow-up)
**Svrha:** Minimalni skup pitanja potrebnih za deblokiranje implementacije  
**Izvor istine:** `apros-question-resolution-matrix.md`, `b2b-erp-adaptation-blueprint.md`, `b2b-erp-plugin-analysis.md`

> **Status 2026-07-02:** Apros je odgovorio na pricing, autentikaciju, order endpoint, partner approval flow i dostavne lokacije. Sesija je djelomično uspješna — arhitekturalni blokeri (pricing model, auth, approval flow) su razriješeni. Payload primjeri (egzaktni field nazivi i realne vrijednosti) i dalje nedostaju — vidi Sekciju "Still Required From Apros".

---

## 1. Executive Summary

### Što je poznato

- Apros arhitektura je u potpunosti mapirana iz produkcijskog B2C koda (Jekaa plugin)
- Pull model (WC povlači podatke iz Apros-a) je potvrđen produkcijskim kodom
- Product identifikacijski model (`articleId`, `variationId`, `barcode`) je potvrđen — bez pitanja
- Variable produkt model (parent + varijacije) je potvrđen i nepromijenjen za B2B
- EAN/barcode polje (`barcode` → `global_unique_id`) je potvrđeno i dostupno
- **Onboarding tok potvrđen kao polling/import, NE webhook** (2026-07-02): web forma → DP admin → ručno kreiranje u Apros-u → `B2B KUPAC = DA` → partner se pojavljuje na partner list endpointu; WC ga preuzima periodičnim pollingom
- Intent narudžbi je jednosmjeran (WC → Apros) — potvrđen iz više izvora
- Default ponašanje za out-of-stock: nema narudžbe, potvrđeno od klijenta
- **Auth mehanizam: API Key** (2026-07-02), isti pristup kao Jekaa B2C
- **Pricing arhitektura razriješena** (2026-07-02): domaći kupci = Model A (`wholesalePrice − Rabat 1`); strani kupci = Model C (`countryPriceList`)

### Što je validirano

- 4 od 14 pitanja su u potpunosti zatvorena/riješena (AP-02, AP-03, AP-09, AP-11); 1 je out of scope (AP-08)
- 3 pitanja su djelomično razriješena na arhitekturalnoj razini, payload primjer pending (AP-01, AP-06, AP-07)
- Faza 1 implementacije može početi bez Apros sesije; Faza 3 partner sync arhitektura (polling/import) je sada poznata
- Produkcijski B2C plugin pokriva ~75% infrastrukturnog koda koji se može direktno preuzeti

### Što i dalje blokira implementaciju

- **2 P0 payload blokera** — arhitektura poznata, finalizacija čeka realni payload primjer (AP-01 pricing, AP-06 order endpoint)
- **1 P1 payload bloker** — AP-07 (delivery locations format)
- **2 interna blokera** — moraju riješiti Dream Point, ne Apros (DP-01, DP-02)

### Razriješeno ovom sesijom

- **AP-09 (auth)** — RESOLVED: API Key
- **AP-03 (approval flow)** — RECLASSIFIED: polling/import model, nema webhooka
- **AP-01 (pricing)** — PARTIALLY RESOLVED: arhitektura poznata (Model A/C), payload pending
- **AP-08 (advance_only/free_shipping)** — OUT OF SCOPE: temeljio se na webhook pretpostavci koja ne postoji

### Traženi ishod sljedeće interakcije s Apros-om

Preostali minimalni skup: realni payload primjeri za pricing (domaći + strani partner), order request/response, i delivery locations. Vidi Sekciju "Still Required From Apros".

---

## 2. Pitanja Uklonjena S Agende

| AP-ID | Razlog uklanjanja | Izvor dokaza |
|-------|-------------------|--------------|
| AP-02 | Product identifikacijski model u potpunosti poznat iz produkcijskog koda | `AprosProvider.php`, `Importer.php`, `ProductToImport.php` |
| AP-11 | EAN/barcode polje potvrđeno produkcijskim kodom; čuva se kao `global_unique_id` | `AprosProvider.php`, `ProductToImport.php` |
| AP-03 | Apros direktno odgovorio: nema webhooka, polling/import model potvrđen | Apros odgovor 2026-07-02 |
| AP-09 | Apros direktno odgovorio: API Key, isti pristup kao Jekaa B2C | Apros odgovor 2026-07-02 |
| AP-08 | Reklasificirano kao out of scope — temeljilo se na webhook pretpostavci koja ne postoji (vidi AP-03) | Odluka nakon Apros odgovora 2026-07-02 |

---

## 3. Kritična Pitanja — P0 (payload primjeri, arhitektura razriješena)

### AP-01 — Pricing Model ✅ PARTIALLY RESOLVED

**Pitanje (originalno):**  
Je li `wholesalePrice` u `articleList/get` ista vrijednost za sve partnere (bazna katalog cijena), ili se razlikuje per-partner (Apros preračunava finalnu cijenu po kupcu)?

**Odgovor Apros-a (2026-07-02):** Domaći kupci = **Model A** (`wholesalePrice − Rabat 1%` = finalna cijena). Strani kupci = **Model C** (`countryPriceList` isporučuje gotovu finalnu neto cijenu, bez Rabat 1). VAT tretman ovisi o pravnoj/poreznoj kategoriji partnera.

**Što i dalje nedostaje:**  
Realni `articleList/get` response za jednog **domaćeg** partnera (s Rabat 1) i jednog **stranog** partnera (s `countryPriceList` vrijednošću) — oboje za isti artikal, u istom prikazu. Format `partnerBrandDiscountList` (Rabat 1 isporuka). Verbalni opis arhitekture je dobiven; egzaktni field nazivi i vrijednosti nisu.

---

### AP-09 — Auth Mehanizam ✅ RESOLVED

**Pitanje (originalno):**  
Kojom metodom WP autentificira pozive prema Apros-u? Produkcijski B2C plugin (`CurlClient.php`) ne šalje nikakve auth headere — zašto?

**Odgovor Apros-a (2026-07-02):** **API Key** — isti pristup kao postojeća Jekaa B2C integracija.

**Preostalo (nije bloker, opcionalno razjasniti):** Zasebni credentials za read (artikli) vs. write (narudžbe)? Dodatni IP whitelist zahtjev uz API Key?

---

### AP-06 — Order Endpoint Format ✅ PARTIALLY RESOLVED

**Pitanje (originalno):**  
Je li B2B order endpoint isti URL kao B2C (`/order/create`)? Prihvaća li `sif_kup`, `salesLocationId` i `deliveryLocationId`? Je li endpoint idempotent?

**Odgovor Apros-a (2026-07-02):** Obavezna polja potvrđena: `sif_kup`/`partnerId`, `partnerDeliveryLocationId`, stavke narudžbe s količinama.

**Što i dalje nedostaje:**  
Realni B2B order payload primjer koji Apros prihvaća, s potvrđenim poljima. Success response format i error response format. Je li endpoint idempotent — nije adresirano. Verbalni opis nije dovoljan za finalizaciju.

**Dodatno:**
- Što se dogodi ako ista narudžba stigne dva puta (retry scenario)?
- Koja su opcionalna polja uz potvrđena obavezna (npr. `billingOib`, `salesLocationId`)?
- **Invoice splitting mehanizam — Status: PARTIALLY RESOLVED.** Nije adresiran ovim odgovorom. **Trenutna radna pretpostavka: Apros vrši interno segmentiranje faktura** (jedna WC narudžba → Apros interno odlučuje o razdvajanju). Pretpostavka zahtijeva formalnu Apros potvrdu.

---

## 4. Važna Pitanja — P1

### AP-03 — Approval / Partner Sync Format ✅ RECLASSIFIED

**Pitanje (originalno):**  
Šalje li Apros `sif_kup`, ugovorni uvjeti (rabat po brandu) i `advance_only` flag u approval webhook body-u, ili zasebno?

**Odgovor Apros-a (2026-07-02):** Nema approval webhooka. Tok: web registracija → email notifikacija → ručno kreiranje partnera u Apros-u → `B2B KUPAC = DA` → partner se pojavljuje na partner list endpointu. Partner sync = **periodic polling/import**, ne webhook.

**Preostalo (nije arhitekturalni bloker):** Frekvencija polling-a; postoji li delta/changed-since mehanizam na partner list endpointu.

---

### AP-07 — Delivery Locations Format ✅ PARTIALLY RESOLVED

**Pitanje (originalno):**  
Format, polja i trigger za listu dostavnih lokacija po partneru. Može li partner imati više lokacija? Je li Apros location ID stabilan između sync ciklusa?

**Odgovor Apros-a (2026-07-02):** Endpoint: `partnerDeliveryLocationList`. Partner može imati više lokacija. **Nema default lokacije** — korisnik bira pri naručivanju.

**Što i dalje nedostaje:**  
Realni `partnerDeliveryLocationList` response za jednog partnera — s Apros location ID-em, svim adresnim poljima. Stabilnost location ID-a između sync ciklusa nije eksplicitno potvrđena.

---

### AP-08 — Advance Only i Free Shipping Flagovi 🚫 OUT OF SCOPE

**Pitanje (originalno):**  
Stižu li `advance_only` i `free_shipping` flagovi u approval webhook-u ili zasebno? Mogu li se promijeniti za aktivnog partnera i koji je mehanizam ažuriranja?

**Status (2026-07-02):** Reklasificirano kao izvan scope-a — pitanje se temeljilo na approval webhook mehanizmu koji ne postoji (vidi AP-03). `advance_only` ostaje dokumentiran kao potencijalno buduće proširenje, ne validira se u ovoj fazi.

---

## 5. Opcionalna Pitanja — P2 (mogu se odgoditi)

| AP-ID | Pitanje | Zašto se može odgoditi |
|-------|---------|------------------------|
| AP-05 | Podržava li Apros delta sync za artikle? Maksimalni broj artikala po responsu? Rate limits? | Full sync se može implementirati odmah; delta je performansna optimizacija |
| AP-13 | Koji mehanizam ažurira rabat za aktivnog partnera — webhook ili periodični sync? | Approval-time sync je siguran default za start |
| AP-10 | Kada Apros rezervira stanje — pri checkoutu ili tek pri ERP potvrdi? | WC default (rezervacija pri narudžbi) je prihvatljiv za start. **Napomena (2026-07-03):** zasebno od ovog pitanja, Dream Point je otvorio internu poslovnu odluku o cart-level (WC-strana) rezervaciji — vidi DP-B06 u `docs/project-status-matrix.md`, ne zahtijeva Apros input |
| AP-12 | Može li Apros primiti narudžbu za artikal s nultim stanjem? | Default no-backorder ponašanje je potvrđeno i implementabilan odmah |
| AP-14 | Šalje li Apros status update natrag prema WC? | Jednosmjeran model je siguran default; inbound endpoint se može dodati naknadno |

---

## 6. Still Required From Apros

> Ovo je autoritativna, konsolidirana lista svega što nedostaje za finalizaciju implementacije nakon odgovora od 2026-07-02. Arhitekturalni blokeri su razriješeni — sve što slijedi su payload primjeri i formalne potvrde, ne arhitekturalne odluke.

### 1. Pricing Payload Examples (AP-01) — P0 KRITIČNO

**Svrha:** Finalizacija pricing engine implementacije — arhitektura poznata (Model A domaći / Model C strani), payload primjer nedostaje  
**Traženi primjer:**

```
articleList/get response za domaćeg partnera:
- articleId, code, wholesalePrice, brandId, b2bArticle flag

ugovorni uvjeti / partnerBrandDiscountList response za istog partnera:
- sif_kup, brandId, discount percentage (Rabat 1)

countryPriceList response za stranog partnera:
- isti artikal — finalna neto cijena po državi
```

**Zašto je važno:** Bez egzaktnih field naziva i realnih vrijednosti pricing engine ne može biti implementiran niti testiran. Verbalni opis arhitekture je dobiven; payload primjer nije.

---

### 2. Order Payload Examples — Success i Error Responses (AP-06) — P0 KRITIČNO

**Svrha:** Implementacija B2B order export buildera — obavezna polja poznata, response format nedostaje  
**Traženi primjer:**

```
B2B order request payload (potvrđena obavezna polja):
- sif_kup / partnerId
- partnerDeliveryLocationId
- items[] sa količinama
- (opcionalno, format nepotvrđen): salesLocationId, billingOib

Success response: format nepoznat (B2C referenca: { "numberErp": "..." })
Error response: format nepoznat (B2C referenca: { "result": "Error", "message": "..." })
```

**Zašto je važno:** Order export je highest-risk operacija; duplirane narudžbe su poslovni incident. Bez success/error response formata ne može se implementirati retry/idempotency logika.

---

### 3. countryPriceList Payload (dio AP-01) — P0 KRITIČNO

**Svrha:** Implementacija Model C pricing sloja za strane kupce  
**Traženi primjer:** Realni `countryPriceList` response — format po državi, koje države su pokrivene, da li je cijena neto/bruto, veza na `articleId`.

---

### 4. partnerBrandDiscountList Payload (dio AP-01) — P0 KRITIČNO

**Svrha:** Implementacija Rabat 1 mehanizma za domaće kupce  
**Traženi primjer:** Realni ugovorni uvjeti / `partnerBrandDiscountList` response — `sif_kup`, `brandId`, postotak, je li obavezan za sve domaće partnere.

---

### 5. Delivery Locations Payload Examples (AP-07) — P1

**Svrha:** Određivanje finalne storage strukture i checkout UI za odabir dostavne adrese — model poznat (više lokacija, nema defaulta), payload nedostaje  
**Traženi primjer:**

```
partnerDeliveryLocationList response za jednog partnera:
- Apros location ID (stabilan identifikator — potvrditi stabilnost između sync ciklusa)
- adresna polja (naziv, ulica, grad, poštanski broj, zemlja)
- potvrda: nema default oznake (već potvrđeno verbalno)
```

---

### 6. B2B Artikal Payload (AP-04) — P1, Faza 2

**Svrha:** Validacija B2B-specifičnih polja u product sync-u (nije bloker za Fazu 1)  
**Traženi primjer:**

```
articleList/get i articleVariationList/get s:
- b2bArticle flag
- wholesalePrice polje
- stock po warehouse (format: nested? zasebni field-ovi?)
```

---

## 7. Interna Pitanja (Nisu Za Apros)

Ove stavke mora riješiti **Dream Point**, ne Apros.

| Oznaka | Pitanje | Vlasnik | Zašto je interno |
|--------|---------|---------|------------------|
| DP-01 | Može li više zaposlenika iste firme imati B2B pristup pod istim `sif_kup`? | Dream Point | Poslovna odluka o company modelu; Apros ne određuje storage arhitekturu na WC strani |
| DP-02 | Koji atribut artikla/kategorije određuje `salesLocationId` (3 = Igračke, 5 = Lifestyle)? Što se radi s mješovitom narudžbom? | Dream Point / Josip | Jedini poznati izvor je stari B2B sustav; Apros prima ID ali ne diktira mapiranje |

**Napomena DP-01:** Ako je odgovor "da, više zaposlenika dijeli `sif_kup`" — storage model se mora redesignirati s User meta na Company custom post type. Ova odluka mora biti donesena **prije početka Faze 3**. Naknadna migracija je složena.

**Napomena DP-02:** `salesLocationId` greška šalje narudžbu na pogrešno prodajno mjesto u ERP-u. Mora biti razriješeno prije početka Faze 5.

---

## 8. Kriteriji Uspjeha Sesije — status 2026-07-02

| # | Stavka | Format | Status |
|---|--------|--------|--------|
| 1 | Pricing model potvrđen — arhitektura | Verbalni | ✅ POTVRĐENO (Model A domaći / Model C strani) |
| 1a | Pricing — realni payload primjer | Realni payload primjer | ❌ NEDOSTAJE |
| 2 | Auth mehanizam potvrđen — metoda autentikacije | Verbalni | ✅ POTVRĐENO (API Key) |
| 3 | Order endpoint — obavezna polja | Verbalni | ✅ POTVRĐENO |
| 3a | Order endpoint — URL, response format, idempotency | Realni payload primjer | ❌ NEDOSTAJE |
| 4 | Partner approval/sync format poznat | Verbalni | ✅ POTVRĐENO (polling/import, nema webhooka) |
| 5 | Delivery locations — model i endpoint | Verbalni | ✅ POTVRĐENO (više lokacija, nema defaulta, `partnerDeliveryLocationList`) |
| 5a | Delivery locations — realni payload primjer | Realni payload primjer | ❌ NEDOSTAJE |
| 6 | `advance_only` / `free_shipping` mehanizam | — | 🚫 OUT OF SCOPE |

**Preostali minimalni uvjet za finalizaciju implementacije:** stavke 1a, 3a, 5a (payload primjeri) — vidi Sekciju 6 "Still Required From Apros".

---

## 9. Finalni Checklist Za Sesiju

### Prije sesije

- [ ] Potvrdi prisustvo tehničke osobe s Apros strane koja ima pristup API dokumentaciji i sandbox
- [ ] Pripremi primjer domaćeg i stranog partnera (s poznatim rabatima) za demonstraciju pricing-a
- [ ] Pripremi testnu narudžbu iz starog B2B sustava kao referentu točku za order payload
- [ ] Razjasni DP-01 (`sif_kup` kardinalitet) s Dream Point interno **prije sesije**
- [ ] Razjasni DP-02 (prodajno mjesto routing) s Josipom **prije sesije**

### Tijekom sesije — status 2026-07-02

- [x] AP-09 — Auth potvrđen: API Key
- [x] AP-03 — Approval flow potvrđen: polling/import, nema webhooka
- [x] AP-01 — Pricing arhitektura potvrđena: Model A domaći / Model C strani
- [x] AP-06 — Obavezna polja order endpointa potvrđena
- [x] AP-07 — Delivery locations model potvrđen: više lokacija, nema defaulta, `partnerDeliveryLocationList`
- [x] AP-08 — Reklasificirano kao out of scope
- [ ] AP-01 — Još treba: realni payload primjer (screenshot ili JSON export) za pricing
- [ ] AP-06 — Još treba: sandbox test ili dokumentacija za order endpoint response format i idempotency
- [ ] AP-07 — Još treba: realni payload primjer za delivery locations
- [ ] AP-04 — Traži B2B payload primjer s `b2bArticle`, `wholesalePrice` i warehouse stock strukturom (nije hitno, Faza 2)

### Nakon ove interakcije

- [ ] Dokumentirati primljene payload primjere u `docs/erp-mapping.md` kad stignu
- [x] Ažurirati `apros-question-resolution-matrix.md` — zatvoreno AP-02, AP-03, AP-09, AP-11; out of scope AP-08; djelomično razriješeno AP-01, AP-06, AP-07
- [x] Arhitekturalna odluka za pricing model donesena: Model A (domaći) + Model C (strani) — dokumentirati formalno u `docs/decisions.md`
- [ ] Auth implementacija (Korak 14): API Key header inject u `CurlClient` — može se implementirati odmah, nije potreban dodatni Apros input
- [ ] Pokrenuti Fazu 3 partner sync kao polling/import job (ne webhook endpoint) — ažurirati `docs/b2b-erp-migration-plan.md` Korak 9/10 i `docs/b2b-erp-adaptation-blueprint.md` Sekciju 4
- [ ] Pokrenuti Fazu 3 / 4 / 5 tek nakon što su DP-01 i DP-02 interni blokeri razriješeni i payload primjeri iz Sekcije 6 pristignu
