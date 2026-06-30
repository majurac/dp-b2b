# Apros Session — Final Preparation Pack

**Datum:** 2026-06-08  
**Svrha:** Minimalni skup pitanja potrebnih za deblokiranje implementacije  
**Izvor istine:** `apros-question-resolution-matrix.md`, `b2b-erp-adaptation-blueprint.md`, `b2b-erp-plugin-analysis.md`

---

## 1. Executive Summary

### Što je poznato

- Apros arhitektura je u potpunosti mapirana iz produkcijskog B2C koda (Jekaa plugin)
- Pull model (WC povlači podatke iz Apros-a) je potvrđen produkcijskim kodom
- Product identifikacijski model (`articleId`, `variationId`, `barcode`) je potvrđen — bez pitanja
- Variable produkt model (parent + varijacije) je potvrđen i nepromijenjen za B2B
- EAN/barcode polje (`barcode` → `global_unique_id`) je potvrđeno i dostupno
- Konceptualni onboarding tok je potvrđen (web forma → DP admin → Apros → webhook → WC rola)
- Intent narudžbi je jednosmjeran (WC → Apros) — potvrđen iz više izvora
- Default ponašanje za out-of-stock: nema narudžbe, potvrđeno od klijenta

### Što je validirano

- 2 od 14 pitanja su u potpunosti zatvorena (AP-02, AP-11)
- Faza 1 implementacije može početi bez Apros sesije
- 8 pitanja su djelomično odgovorena — dovoljno za okvirni arhitekturalni dizajn
- Produkcijski B2C plugin pokriva ~75% infrastrukturnog koda koji se može direktno preuzeti

### Što blokira implementaciju

- **3 P0 blokera** — arhitektura nije moguća bez odgovora (AP-01, AP-06/AP-09 mapping)
- **3 P1 blokera** — Faze 3–5 ne mogu početi (AP-03, AP-07, AP-08)
- **2 interna blokera** — moraju riješiti Dream Point, ne Apros (DP-01, DP-02)

### Traženi ishod sesije

Minimalni skup potrebnih odgovora: potvrda pricing modela + payload primjeri za narudžbu i lokacije + auth mehanizam + approval webhook format. Bez toga, Faze 4 i 5 se ne mogu arhitekturalno odlučiti.

---

## 2. Pitanja Uklonjena S Agende

| AP-ID | Razlog uklanjanja | Izvor dokaza |
|-------|-------------------|--------------|
| AP-02 | Product identifikacijski model u potpunosti poznat iz produkcijskog koda | `AprosProvider.php`, `Importer.php`, `ProductToImport.php` |
| AP-11 | EAN/barcode polje potvrđeno produkcijskim kodom; čuva se kao `global_unique_id` | `AprosProvider.php`, `ProductToImport.php` |

---

## 3. Kritična Pitanja — P0 (blokiraju implementaciju)

### AP-01 — Pricing Model

**Pitanje:**  
Je li `wholesalePrice` u `articleList/get` ista vrijednost za sve partnere (bazna katalog cijena), ili se razlikuje per-partner (Apros preračunava finalnu cijenu po kupcu)?

**Zašto blokira:**  
Dva nezavisna izvora daju direktno konfliktne interpretacije. Model A (WC računa finalnu cijenu iz baze i rabata) i Model B (WC prikazuje gotovu cijenu) zahtijevaju potpuno drugačiju arhitekturu storage-a i runtime kalkulacije. Pogrešan odabir = kompletan rework pricing komponente.

**Traženi format odgovora:**  
Realni `articleList/get` response za jednog **domaćeg** partnera (s Rabat 1) i jednog **stranog** partnera (s cjenikom po državama) — oboje za isti artikal, u istom prikazu. Verbalni opis nije dovoljan.

**Dodatna pitanja uz payload:**
- Je li Rabat 1 obavezan za sve domaće partnere ili opcionalan?
- Koji je format "Rabat 1" u ugovornim uvjetima endpointu — postotak po brandu?
- Koji je format cjenika za strane kupce — zasebni endpoint ili polje u partner responsu?

---

### AP-09 — Auth Mehanizam

**Pitanje:**  
Kojom metodom WP autentificira pozive prema Apros-u? Produkcijski B2C plugin (`CurlClient.php`) ne šalje nikakve auth headere — zašto?

**Zašto blokira:**  
Svaki outbound API poziv prema Apros-u je blokiran dok auth model nije poznat. OAuth 2.0 scenarij znatno multiplicira implementacijski scope (token refresh, credential rotation).

**Traženi format odgovora:**  
- Auth metoda: API key / OAuth 2.0 / Basic auth / JWT / Bearer token / IP whitelist / nema
- Zasebni credentials za read (artikli) vs. write (narudžbe)?
- IP whitelist — koji je izlazni IP koji treba whitelistati (staging vs. produkcija)?

---

### AP-06 — Order Endpoint Format

**Pitanje:**  
Je li B2B order endpoint isti URL kao B2C (`/order/create`)? Prihvaća li `sif_kup`, `salesLocationId` i `deliveryLocationId`? Je li endpoint idempotent?

**Zašto blokira:**  
Order export je highest-risk operacija. Duplirane narudžbe u ERP-u su poslovni incident. Bez poznatih B2B-specifičnih polja, order payload builder ne može biti implementiran.

**Traženi format odgovora:**  
Realni B2B order payload primjer koji Apros prihvaća — s `sif_kup`, `salesLocationId`, `deliveryLocationId`, `billingOib`. Uz to: success response format i error response format. Verbalni opis nije dovoljan.

**Dodatno:**
- Što se dogodi ako ista narudžba stigne dva puta (retry scenario)?
- Koji su obavezni vs. opcionalni fields?

---

## 4. Važna Pitanja — P1 (blokiraju Faze 3–5)

### AP-03 — Approval Webhook Format

**Pitanje:**  
Šalje li Apros `sif_kup`, ugovorni uvjeti (rabat po brandu) i `advance_only` flag u approval webhook body-u, ili zasebno?

**Zašto je važno:**  
Određuje arhitekturu onboarding flow-a. Ako `sif_kup` ne stiže u approval webhook-u, potreban je odvojeni partner sync mehanizam koji komplicira arhitekturu i dodaje moving parts.

**Traženi format odgovora:**  
Kompletna lista polja u approval webhook body-u. Format (JSON body / query params?). Postoji li webhook signature za verifikaciju?

---

### AP-07 — Delivery Locations Format

**Pitanje:**  
Format, polja i trigger za listu dostavnih lokacija po partneru. Može li partner imati više lokacija? Je li Apros location ID stabilan između sync ciklusa?

**Zašto je važno:**  
Direktno određuje storage model (user meta vs. custom post type) i checkout UI za odabir adrese dostave. Ako partner može imati mnogo lokacija, user meta nije primjeren storage.

**Traženi format odgovora:**  
Realni delivery locations response za jednog partnera — s Apros location ID-em, svim adresnim poljima i oznakom defaulta ako postoji.

---

### AP-08 — Advance Only i Free Shipping Flagovi

**Pitanje:**  
Stižu li `advance_only` i `free_shipping` flagovi u approval webhook-u ili zasebno? Mogu li se promijeniti za aktivnog partnera i koji je mehanizam ažuriranja?

**Zašto je važno:**  
Checkout payment restrictions direktno ovise o `advance_only` flagu. Ako se flag može promijeniti bez novog webhook poziva, WC ne može garantirati konzistentne payment restrictions.

**Traženi format odgovora:**  
Kako i kada dolaze; mehanizam ažuriranja za aktivnog partnera (novi webhook / periodični sync / ručno).

---

## 5. Opcionalna Pitanja — P2 (mogu se odgoditi)

| AP-ID | Pitanje | Zašto se može odgoditi |
|-------|---------|------------------------|
| AP-05 | Podržava li Apros delta sync za artikle? Maksimalni broj artikala po responsu? Rate limits? | Full sync se može implementirati odmah; delta je performansna optimizacija |
| AP-13 | Koji mehanizam ažurira rabat za aktivnog partnera — webhook ili periodični sync? | Approval-time sync je siguran default za start |
| AP-10 | Kada Apros rezervira stanje — pri checkoutu ili tek pri ERP potvrdi? | WC default (rezervacija pri narudžbi) je prihvatljiv za start |
| AP-12 | Može li Apros primiti narudžbu za artikal s nultim stanjem? | Default no-backorder ponašanje je potvrđeno i implementabilan odmah |
| AP-14 | Šalje li Apros status update natrag prema WC? | Jednosmjeran model je siguran default; inbound endpoint se može dodati naknadno |

---

## 6. Traženi Payload Primjeri

### Pricing Payload (AP-01) — P0 KRITIČNO

**Svrha:** Odabir pricing modela (Model A vs Model B) — nema zaobilaznog rješenja  
**Traženi primjer:**

```
articleList/get response za domaćeg partnera:
- articleId, code, wholesalePrice, brandId, b2bArticle flag

ugovorni uvjeti response za istog partnera:
- sif_kup, brandId, discount percentage (Rabat 1)

articleList/get response za stranog partnera:
- isti artikal, ista polja — usporedba wholesalePrice vrijednosti
```

**Zašto je važno:** Jedini način razrješenja konflikta između dva iskustvena izvora. Verbalni opis nije dovoljan.

---

### Order Payload (AP-06) — P0 KRITIČNO

**Svrha:** Implementacija B2B order export buildera  
**Traženi primjer:**

```
B2B order payload koji Apros prihvaća:
- sif_kup
- salesLocationId
- deliveryLocationId
- billingOib
- items struktura

Success response: { "numberErp": "..." }
Error response: { "result": "Error", "message": "..." }
```

**Zašto je važno:** Order export je highest-risk operacija; duplikate narudžbe su poslovni incident.

---

### Delivery Locations Payload (AP-07) — P1

**Svrha:** Određivanje storage modela i checkout UI za odabir dostavne adrese  
**Traženi primjer:**

```
Delivery locations response za jednog partnera:
- endpoint URL
- Apros location ID (stabilan identifikator)
- adresna polja (naziv, ulica, grad, poštanski broj, zemlja)
- oznaka defaulta
- može li ih biti više
```

---

### B2B Artikal Payload (AP-04) — P1, Faza 2

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

## 8. Kriteriji Uspjeha Sesije

Sesija je uspješna ako su dobiveni sljedeći odgovori:

| # | Stavka | Format |
|---|--------|--------|
| 1 | **Pricing model potvrđen** — je li `wholesalePrice` bazna ili per-partner cijena | Realni payload primjer |
| 2 | **Auth mehanizam potvrđen** — metoda autentikacije za outbound API pozive | Verbalni + credentials format |
| 3 | **Order endpoint validiran** — URL, B2B-specifična polja, idempotency | Realni payload primjer |
| 4 | **Approval webhook format poznat** — kompletna lista polja u body-u | Verbalni ili JSON primjer |
| 5 | **Delivery locations format poznat** — endpoint, polja, storage implikacija | Realni payload primjer |
| 6 | **`advance_only` / `free_shipping` mehanizam poznat** — isporuka i ažuriranje | Verbalni |

Stavke 1, 2, 3 su **minimalni uvjet** — bez njih implementacija ne može nastaviti.

---

## 9. Finalni Checklist Za Sesiju

### Prije sesije

- [ ] Potvrdi prisustvo tehničke osobe s Apros strane koja ima pristup API dokumentaciji i sandbox
- [ ] Pripremi primjer domaćeg i stranog partnera (s poznatim rabatima) za demonstraciju pricing-a
- [ ] Pripremi testnu narudžbu iz starog B2B sustava kao referentu točku za order payload
- [ ] Razjasni DP-01 (`sif_kup` kardinalitet) s Dream Point interno **prije sesije**
- [ ] Razjasni DP-02 (prodajno mjesto routing) s Josipom **prije sesije**

### Tijekom sesije

- [ ] AP-01 — Traži payload primjer, ne verbalni opis (screenshot ili JSON export)
- [ ] AP-09 — Pitaj zašto B2C plugin ne šalje auth headere; clarify IP whitelist za staging IP
- [ ] AP-06 — Traži sandbox test ili dokumentaciju za B2B order endpoint; potvrdi `sif_kup` kao obavezno polje
- [ ] AP-03 — Traži primjer approval webhook body-a koji Apros šalje
- [ ] AP-07 — Traži primjer delivery locations response-a; pitaj može li lokacija biti više od jedne
- [ ] AP-08 — Pitaj gdje točno dolaze `advance_only` i `free_shipping` (u istom webhook pozivu ili zasebno)
- [ ] AP-04 — Traži B2B payload primjer s `b2bArticle`, `wholesalePrice` i warehouse stock strukturom (nije hitno, ali ako ima vremena)

### Nakon sesije

- [ ] Dokumentirati sve primljene payload primjere u `docs/erp-mapping.md`
- [ ] Ažurirati `apros-question-resolution-matrix.md` — zatvoriti odgovorena pitanja
- [ ] Donijeti arhitekturalnu odluku za pricing model (Model A / B / C) i dokumentirati u `docs/decisions.md`
- [ ] Ako auth zahtijeva OAuth — procijeni implementacijski scope i prilagodi roadmap
- [ ] Pokrenuti Fazu 3 / 4 / 5 tek nakon što su DP-01 i DP-02 interni blokeri razriješeni
