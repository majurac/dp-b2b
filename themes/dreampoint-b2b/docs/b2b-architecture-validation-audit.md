# DP-B2B — Architecture Validation Audit

**Datum:** 2026-06-08  
**Uloga:** Nezavisni tehnički recenzent  
**Pregledani dokumenti:**
- `docs/erp-discovery-findings.md`
- `docs/erp-validation-checklist.md`
- `docs/project-status-matrix.md`
- `docs/stakeholder-question-matrix.md`
- `docs/b2b-erp-plugin-analysis.md`
- `docs/b2b-erp-adaptation-blueprint.md`

> Ovaj dokument ne predlaže novu arhitekturu niti rješava otvorena pitanja pretpostavkama.  
> Cilj je identificirati gdje arhitekturalni dokumenti stoje čvrsto, a gdje klize na lodu.  
> Evidencijska hijerarhija: produkcijski kod → produkcijsko ponašanje → discovery dokumenti → pretpostavke → iskustveni signali.

---

## 1. Executive Summary

### Ukupna razina povjerenja

| Oblast | Povjerenje |
|--------|-----------|
| Poslovne domene i pravila | **HIGH** |
| ERP konektivna infrastruktura (reuse baseline) | **HIGH** |
| Pricing arhitektura | **LOW** |
| Order export arhitektura | **LOW** |
| Partner/customer sync arhitektura | **LOW** |
| Auth model prema Apros-u | **LOW** |
| Warehouse stock model (business zahtjev) | **MEDIUM** |
| Warehouse stock model (tehnička isporuka) | **LOW** |
| Approval lifecycle (konceptualni tok) | **MEDIUM** |
| Approval lifecycle (tehnički format) | **LOW** |

### Arhitekturalna readiness

**CONDITIONALLY STABLE** — uz sljedeći uvjet: arhitekturalni dokumenti su interno konzistentni i ispravno identificiraju što se zna i što nije poznato. Međutim, tri najkritičnija tehničko-arhitekturalna podsustava (pricing engine, order export, partner sync) ovise o pretpostavkama koje nijedna validacija još nije potvrdila. Arhitektura je dobro *opisana* — ali nije *zasnovana* na dovoljno čvrstim dokazima za ulaz u implementaciju.

### Implementacijska readiness

**LOW** — Implementacija Faza 3, 4 i 5 nije moguća. Faza 1 može početi. Faza 2 djelomično ovisi o AP-07 koji je nevalidiran. Sve faze koje se tiču pricing-a, partner sync-a i order export-a su blokirane aktivnim P0 i P1 blokerima koji ne mogu biti razriješeni bez izravnog pristupa Apros API-ju i bez Dream Point workshopa.

---

## 2. Assumption Audit

Svaka značajna arhitekturalna odluka klasificirana je prema razini podrške dokazima.

| Oblast | Pretpostavka | Klasifikacija | Razlog |
|--------|-------------|---------------|--------|
| **Pricing — wholesalePrice semantika** | `wholesalePrice` je finalna per-partner cijena ili bazna cijena | **UNSUPPORTED** | Dva nezavisna iskustvena izvora (Leo Benkek, Milenko Stojaković) daju direktno konfliktne interpretacije. Nijedan nije potvrda od Apros-a. Jedini način razrješenja je realni payload primjer. |
| **Pricing — Rabat 1 isporuka** | Rabat 1 dolazi iz "ugovorni uvjeti" endpointa kao postotak po brandu | **PARTIALLY VALIDATED** | NC-04 (Leo email) opisuje strukturu ugovornih uvjeta: `sif_kup` + brand + postotak. Nije potvrđeno direktno od Apros-a niti je pokazan realni payload. |
| **Pricing — Model odabir** | Koji od tri modela (A, B, C) implementirati | **UNSUPPORTED** | Blueprint ispravno dokumentira sve tri mogućnosti ali eksplicitno ne odabire nijednu. Odabir nije moguć bez AP-01 validacije. |
| **Partner sync — sif_kup kardinalitet** | `sif_kup` je 1:1 s WP korisnikom | **ASSUMPTION** | Nigde nije potvrđeno od Dream Point-a ili Apros-a. Projekt-status-matrix bilježi "svaki korisnik registruje zaseban nalog čak i ako pripada istoj firmi" kao potvrđenu činjenicu, ali ovo ne odgovara na pitanje dijele li isti `sif_kup`. Miješanje odvojenosti WP naloga sa 1:1 sif_kup veznošću je logička greška. |
| **Approval lifecycle — konceptualni tok** | Web forma → notifikacija → ručno u Apros → Apros šalje signal → WC dodjeljuje rolu | **PARTIALLY VALIDATED** | NC-05 (Leo email) potvrđuje ovaj tok. Ali konkretni format webhook signala, koje je polje koje trigger je, i što se točno šalje u body-u — sve to ostaje nevalidirano. |
| **Approval lifecycle — webhook body** | Apros šalje `sif_kup`, rabat, cjenik i `advance_only` u jednom webhook pozivu | **ASSUMPTION** | Checklist 1.3 i blueprint AP-05 jasno bilježe ovo kao nevalidiran. |
| **Warehouse model — poslovni zahtjev** | 4 skladišta, per-warehouse prikaz, brand → matično skladište | **VALIDATED** | Potvrđeno eksplicitno iz više izvora u mail korespondenciji i potvrđeno od klijenta. |
| **Warehouse model — tehnička isporuka** | Apros šalje stock po skladištu u payloadu s jasnim warehouse identifikatorom | **ASSUMPTION** | Produkcijski B2C plugin šalje jedan `stock` integer bez warehouse breakdown-a. Format B2B proširenja je potpuno nevalidiran (V-08, AP-07). |
| **Product identifikacija (ERP ID)** | `articleId` / `variationId` kao primarni ERP ključ | **VALIDATED** | Potvrđeno produkcijskim kodom B2C plugina. B2B koristi isti endpoint. |
| **Variable product model** | `virtualArticle` flag razlikuje variable od simple | **VALIDATED** | Potvrđeno produkcijskim kodom. NC-02 potvrđuje isti endpoint za B2B. |
| **Auth model prema Apros-u** | Auth postoji i može biti implementiran | **UNSUPPORTED** | Kritičan nalaz iz plugin analize: `CurlClient.php` ne šalje nikakve auth headere. `username` i `password` polja postoje u konfiguraciji ali se nikad ne koriste u API pozivu. Apros B2C API ili ne zahtijeva auth, ili je zaštićen IP whitelistom. Format B2B auth zahtjeva je potpuno nepoznat. |
| **Order export — payload format** | Apros prima JSON payload s definiranim poljima | **ASSUMPTION** | Poznat je format B2C order payloada (iz produkcijskog koda). Ali B2B zahtijeva nova polja (`sif_kup`, `salesLocationId`, `deliveryLocationId`). Ne zna se prihvaća li Apros ova polja, niti su validirana. |
| **Order export — idempotency** | Apros endpoint je idempotent ili vraća order ID za zaštitu od duplikata | **UNSUPPORTED** | V-05, AP-02. B2C implementacija koristi client-side `_erp_sync_status` meta kao guard, ali nema Apros-side garancije. |
| **Sales location routing** | `salesLocationId` (3=Igračke, 5=Lifestyle) određuje se po atributu artikla | **UNSUPPORTED** | BL-06, DP-02. Jedini poznati izvor je "Josip / stari B2B sustav". Ni mehanizam mapiranja ni konkretan atribut koji se gleda nisu dokumentirani. |
| **Delta sync** | Apros podržava inkrementalni sync | **ASSUMPTION** | B2C produkcija koristi full sync svaki run. "Inkrementalni sync preferiran" je izjava klijenta/integratora, ne potvrda Apros tehničke mogućnosti. V-07, AP-06. |
| **Deaktivacijski webhook** | Apros šalje ili ne šalje deaktivacijski webhook | **UNSUPPORTED** | Blueprint default je ručna deaktivacija. Checklist 3.2 bilježi pitanje kao otvoreno. |
| **Order status bidirectionality** | Narudžbe su jednosmjerne (WC → Apros) | **PARTIALLY VALIDATED** | Potvrđeno kao intencija iz discovery. Apros-side potvrda da ne šalje status update natrag nije dobivena (checklist 3.6 još nije odgovoren). |
| **OIB/VAT ID izvor** | OIB dolazi iz registracijske forme | **ASSUMPTION** | Data ownership matrix definira OIB vlasnika kao "Dream Point (kupac)" s izvorom "forma pri registraciji". Ali nije potvrđeno da Apros ne šalje OIB kao dio partner payloada, niti da checkout forma ima validaciju formata HR OIB-a. |

---

## 3. Evidence Gaps

Mjesta gdje arhitektura ovisi o nepostojećim dokazima.

---

### EG-01 — Nema nijednog realnog Apros payload primjera

**Impact:** Sve pretpostavke o formatu API odgovora su nevalidiran. Bez payload primjera, svaki arhitekturalni model za pricing, warehouse, partner i order sync je "dobar plan koji može biti pogrešan".  
**Severity:** CRITICAL  
**Required validation:** Apros API pristup (sandbox ili production) + barem jedan realni response po endpointu.

---

### EG-02 — wholesalePrice semantika: konfliktni signali bez arbitraže

**Impact:** Pricing engine arhitektura je binarno pogrešna ako se odabere krivi model. Model A (WC računaju finalnu cijenu) i Model B (WC prikazuje gotovu cijenu) zahtijevaju potpuno drugačiju implementaciju i storage strategiju. Nema "malo pogrešnog" scenarija — greška znači rework cijele pricing komponente.  
**Severity:** CRITICAL  
**Required validation:** Realni `articleList/get` payload za domaćeg partnera s Rabat 1 + realni `ugovorni uvjeti` payload za istog partnera.

---

### EG-03 — Auth model: B2C produkcija ne šalje auth, B2B zahtjevi nepoznati

**Impact:** Svaki outbound API poziv prema Apros-u (product sync, partner sync, order export) ne može biti implementiran bez poznatog auth mehanizma. Ako Apros B2B zahtijeva OAuth 2.0, implementacijski scope se bitno povećava u odnosu na statički API key.  
**Severity:** CRITICAL  
**Required validation:** Izravna potvrda od Apros tima o auth mehanizmu za B2B API pristup.

---

### EG-04 — Order endpoint: URL, format, idempotency — sve nepoznato

**Impact:** Order export (Faza 5) ne može biti implementiran. Retry logika bez idempotency garancije stvara poslovni rizik od duplirane narudžbe u ERP-u.  
**Severity:** CRITICAL  
**Required validation:** Apros API dokumentacija ili sesija; realni primjer prihvaćenog order payload-a.

---

### EG-05 — sif_kup kardinalitet: odvojenost WP naloga ≠ 1:1 sif_kup

**Impact:** Ako jedna firma ima više zaposlenika koji dijeluju isti `sif_kup`, storage model mora biti na razini company entiteta (custom post type), ne user meta. Ovo je retroaktivno nemigrabilna promjena — jednom ugrađen user-meta model teško se pomiče na company model bez baze podataka migracije.  
**Severity:** HIGH  
**Required validation:** Dream Point workshop — eksplicitno pitanje o multi-user firma scenariju i što dijele.  
**Napomena:** projekt-status-matrix.md bilježi "svaki korisnik registruje zaseban nalog čak i ako pripada istoj firmi" kao potvrđenu činjenicu, ali ovo ne odgovara pitanje o kardinalitetu `sif_kup`. Ovo je gap u dokumentaciji koji može stvoriti lažnu sigurnost.

---

### EG-06 — Warehouse stock payload format potpuno nevalidiran

**Impact:** Opcija A (individual meta per warehouse) ili Opcija B (JSON meta) odabir pretpostavlja da Apros šalje warehouse breakdown. Produkcijski B2C plugin dobiva jednu `stock` vrijednost bez warehouse identifikatora. Bez potvrde da B2B endpoint šalje per-warehouse data, Warehouse architecture sekcija blueprinta nema utemeljenja.  
**Severity:** HIGH  
**Required validation:** Realni B2B `articleList/get` payload koji uključuje warehouse breakdown.

---

### EG-07 — Sales location routing: institucijalno znanje bez dokumentacije

**Impact:** Order export ne može odrediti `salesLocationId` (3 ili 5) bez mehanizma mapiranja. "Jedini poznati izvor je Josip" je single point of failure koji nije primjeren arhitekturalnom dokumentu — nije dokumentirano koji atribut artikla ili kategorije nosi informaciju o prodajnom mjestu.  
**Severity:** HIGH  
**Required validation:** Ekstrakcija logike iz starog B2B sustava (Josip / ZGData) i dokumentacija konkretnog mapiranja.

---

### EG-08 — 9 produkcijskih endpointa vs. 3 "potvrđena endpoint tipa" u discovery

**Impact:** discovery-findings.md (NC-03) bilježi 3 endpoint tipa. Plugin analiza pokazuje da produkcija koristi 9 GET endpointa per import run (`articleList`, `articleVariationList`, `articleImageList`, `articleVariationImageList`, `brandList`, `attributeList`, `attributeItemList`, `articleAttributeList`, `articleVariationAttributeList`). Discrepancy između "3 potvrđena" i "9 produkcijskih" nije adresirana u dokumentima.  
**Impact:** Cron/performance planning koji pretpostavlja manji broj poziva može biti pogrešan. Memory management za B2B skalabilnost ovisi o broju i veličini poziva.  
**Severity:** MEDIUM  
**Required validation:** Potvrda hoće li B2B koristiti isti skup od 9 endpointa ili prošireni set.

---

### EG-09 — OIB validacija i izvor nije dokumentiran

**Impact:** Blueprint definira `billingOib` kao obavezno polje u B2B order payloadu. Data ownership matrix kaže da dolazi iz "registracijske forme". Ali: nema opisa forme za OIB, nema HR OIB format validacije, nema potvrde da Apros ne pruža OIB kao dio partner payloada, nema definisanog fallback-a ako kupac nije unio OIB.  
**Severity:** MEDIUM  
**Required validation:** Dream Point odluka o registracijskoj formi + Apros potvrda o OIB u partner payloadu.

---

## 4. Contradiction Review

Pregled mjesta gdje dokumenti međusobno kontradiktorni ili internalno nedosljedni.

---

### CO-01 — "Inkrementalni sync preferiran" vs. produkcija radi full sync svaki run

**Dokumenti:** `erp-discovery-findings.md` (potvrđene poslovne domene, sekcija "Sinkronizacija i rollout") vs. `b2b-erp-plugin-analysis.md` (Sekcija 10)

**Konflikt:** Discovery bilježi "Cron-based sinkronizacija; inkrementalni sync (samo izmijenjeni podaci) je preferirani model" kao potvrđenu poslovnu činjenicu. Plugin analiza potvrđuje da produkcijska B2C implementacija radi **full sync svaki run** i eksplicitno napominje: "Nema Apros endpointa za 'changed since timestamp' — potvrda potrebna."

**Rizik:** Projekt status matrix bilježi "Pricing mehanizam ⚠️ Podržano dokazima, nije potvrđeno — Srednje povjerenje", ali delta sync (koji je jednako kritičan za performance) nije odvojena stavka u status matriku. Ako Apros ne podržava delta sync, full sync na 10.000 artikala + varijacija + slika + atributa = memorijska i vremenski intenzivna operacija svaki sat.

---

### CO-02 — Odvojenost WP naloga ≠ potvrda 1:1 sif_kup

**Dokumenti:** `project-status-matrix.md` (Sekcija 1.7, "CONFIRMED FACTS") vs. `b2b-erp-adaptation-blueprint.md` (Sekcija 4, "ASSUMPTION")

**Konflikt:** Status matrix bilježi "Svaki korisnik registruje zaseban nalog, čak i ako pripada istoj firmi" u CONFIRMED FACTS. Blueprint ispravno bilježi "sif_kup je 1:1 s WP korisnikom" kao ASSUMPTION. Problem: čitatelj može interpretirati potvrđenu činjenicu kao validaciju 1:1 pretpostavke — što je logički netočno. Firma s 5 zaposlenika može imati 5 zasebnih WP naloga koji svi dijele isti `sif_kup`.

**Rizik:** Ako netko donosi implementacijske odluke na osnovu status matrixa bez čitanja blueprinta, može propustiti ovu razliku i izgraditi user-meta model bez potvrde kardinaliteta.

---

### CO-03 — Auth model: ACF polja postoje, ali se nikad ne koriste

**Dokumenti:** `b2b-erp-plugin-analysis.md` (Sekcija 4) vs. `b2b-erp-adaptation-blueprint.md` (Sekcija 2, CurlClient klasifikacija)

**Konflikt:** Plugin analiza identificira kritičan nalaz: `CurlClient` ne šalje nikakve auth headere. `username` i `password` konfiguracija postoji ali nije spojena na API pozive. Blueprint klasificira `CurlClient` kao "REUSE AS-IS" i kaže "samo dodati auth header inject". Ovo je ispravna preporuka, ali ne adresira fundamentalnu nepoznanicu: zašto B2C radi bez auth headera? Je li Apros B2C API nezaštićen? IP-whitelisted? Ili postoji auth koji prolazi drugačijim kanalom?

**Rizik:** Ako se B2B API autenticira na način koji nije "dodaj header", blueprint CurlClient strategija je pogrešna. Potencijalni scenarij: B2B Apros API zahtijeva OAuth 2.0 koji B2C ne treba jer B2C radi bez auth.

---

### CO-04 — Bloker klasifikacije se razlikuju između checklist-a i blueprinta

**Dokumenti:** `erp-validation-checklist.md` (sekcija 3.1, Priority 3) vs. `b2b-erp-adaptation-blueprint.md` (Sekcija 9, DP-01 = HIGH severity P1)

**Konflikt:** `sif_kup` kardinalitet je u validation checklist-u stavljen pod "Priority 3 — Operational Questions" (ne blokiraju početak implementacije). Blueprint ga ispravno klasificira kao P1 bloker s HIGH severity i napominje da je to "retroaktivno nemigrabilna promjena".

**Rizik:** Ako tim prati checklist kao vodič za prioritizaciju, može zanemariti `sif_kup` kardinalitet kao "operativni detalj" i tek naknadno otkriti da zahtijeva redesign storage modela.

---

### CO-05 — advance_only/free_shipping u checklist-u vs. u blueprintu

**Dokumenti:** `erp-validation-checklist.md` (2.4, Priority 2) vs. `b2b-erp-adaptation-blueprint.md` (Sekcija 4, Customer Architecture)

**Konflikt:** Checklist 2.4 pita stižu li `advance_only` i `free_shipping` u approval webhook-u ili zasebno. Blueprint (Sekcija 4 i Data Ownership Matrix) tretira `advance_only` kao podatak koji dolazi pri approval-u ("Apros → WC (pri approval)") bez eksplicitne napomene da je format nevalidiran. Postoji razlika između pitanja "kako dolazi?" i pretpostavke da "dolazi pri approval".

**Rizik:** Ako `advance_only` dolazi samo periodičnim sync-om (ne pri approval), payment restrictions mogu biti netočne za novog korisnika koji je upravo odobren — sve dok sljedeći partner sync ne povuče podatke.

---

### CO-06 — B2B endpoint inventory: 3 vs. 9

**Dokumenti:** `erp-discovery-findings.md` (NC-03, "Tri potvrđena endpoint tipa") vs. `b2b-erp-plugin-analysis.md` (Sekcija 3, 9 GET endpointa)

**Konflikt:** NC-03 iz Leo Benkek emaila potvrđuje 3 endpoint tipa. Plugin analiza pokazuje da produkcija koristi 9 zasebnih GET poziva po import runu. Blueprint Faza 1 govori o "skaliranju cron arhitekture za 10.000 artikala" bez eksplicitnog adresiranja broja API poziva. Ova razlika može bitno utjecati na performance planning i Apros API rate limit razmatranja.

---

## 5. Blocker Review

### Provjera postojećih blokirajućih stavki

| Bloker | Dokumentirana klasifikacija | Validnost klasifikacije | Napomena |
|--------|---------------------------|------------------------|---------|
| **BL-01** Apros API pristup | KRITIČAN | ✅ Ispravno | Bez pristupa nijedna tehnička validacija nije moguća |
| **BL-03** Pricing model | HIGH | ⚠️ Nedovoljno ozbiljno | Blueprint klasificira AP-01 kao P0. Status matrix ima "HIGH" ali efekt pogrešne pretpostavke je kompletan rework pricing komponente — ovo je de facto P0 |
| **BL-04** Order endpoint | HIGH | ⚠️ Nedovoljno ozbiljno | Blueprint klasificira AP-02 kao P0 Integracijski bloker. Trebalo bi biti P0 u svim dokumentima |
| **BL-05** Auth mehanizam | HIGH | ⚠️ Nedovoljno ozbiljno | Blueprint klasificira AP-03 kao P0 Infrastrukturalni bloker. Status matrix koristi "HIGH" — nekonzistentno |
| **BL-02** Struktura varijanti | MEDIUM (downgraded) | ✅ Ispravno | B2C endpoint već ima varijante, opravdana downgrade |
| **BL-06** Josip / prodajno mjesto | Interni, bez severity | ❌ Nedovoljno kategoriziran | Blueprint ima DP-02 (P1), ali status matrix nema BL-06 u aktivnim blokerima. Single point of failure institucionalnog znanja |

### Novi blokeri koji nisu eksplicitno u status matriku

| ID | Opis | Preporučena klasifikacija |
|----|------|--------------------------|
| **NEW-01** | Dream Point workshop nije održan — blokiraju UX IA, checkout, payment metode | P1 (blokira Faze 3–5 indirektno) |
| **NEW-02** | `sif_kup` kardinalitet (V-06) nije u aktivnim blokerima status matrixa | P1 — reklasifikacija iz "Priority 3" |
| **NEW-03** | Warehouse stock payload format (V-08) nije u aktivnim blokerima | P1 za Fazu 2 |

---

## 6. Risk Register

### P0 — Kritični rizici (blokiraju implementaciju)

---

**P0-01 — Pricing model nevalidiran uz konfliktne signale**

- **Probability:** HIGH (5 od 5 — potvrđeni konflikt između izvora)
- **Impact:** Kompletan rework pricing engine-a ako se odabere krivi model
- **Mitigation:** AP-01 sesija s realnim payload primjerom; odabir modela tek nakon Apros validacije; ne implementirati pricing dok P0-01 nije razriješen
- **Napomena:** Ovo je jedini P0 rizik koji kombinira visoku vjerojatnost (konflikt je potvrđen) s visokim utjecajem (kompletna rework)

---

**P0-02 — Order endpoint potpuno nepoznat**

- **Probability:** N/A (nepoznat, ne radi se o riziku pojave već o missing knowledge)
- **Impact:** Order export (Faza 5) ne može biti implementiran; retry bez idempotency = duplirane narudžbe u ERP-u
- **Mitigation:** AP-02 sesija; order export Faza 5 striktno blokirana dok BL-04 nije razriješen

---

**P0-03 — Auth model nepoznat; B2C produkcija ne šalje auth**

- **Probability:** N/A (missing knowledge)
- **Impact:** Nijedan outbound Apros API poziv ne može biti sigurno implementiran; OAuth scenarij multiplicira implementacijski scope
- **Mitigation:** AP-03 sesija; CurlClient auth extension ne može biti finaliziran dok auth model nije poznat

---

### P1 — Visoki rizici (blokiraju kritične faze)

---

**P1-01 — sif_kup 1:N scenarij: retroaktivno nemigrabilna promjena**

- **Probability:** MEDIUM (nije isključen, nije potvrđen)
- **Impact:** Ako se potvrdi 1:N, user-meta storage model mora biti migriran na company CPT model; nema bezbolne migracije
- **Mitigation:** DP-01 pitanje mora biti prvi prioritet na Dream Point workshopu; ne počinjati partner sync implementaciju dok nije potvrđeno
- **Napomena:** Ovo je rizik koji bi trebao biti P0 po utjecaju, ali je P1 po vjerojatnosti (postoji scenarij da je 1:1)

---

**P1-02 — Sales location routing: institucijalno znanje bez dokumentacije**

- **Probability:** HIGH (mehanizam trenutno nije nigdje zapisan)
- **Impact:** Order export ne može odrediti `salesLocationId`; kriva vrijednost šalje narudžbu na pogrešno prodajno mjesto u Apros-u
- **Mitigation:** Hitno angažiranje Josipa / ZGData za ekstrakciju logike iz starog B2B sustava; dokumentirati mapiranje

---

**P1-03 — Warehouse stock payload format nevalidiran**

- **Probability:** HIGH (B2C nema warehouse breakdown; B2B zahtjev je novi)
- **Impact:** Warehouse storage model (Opcija A vs B) odabran bez potvrde da Apros šalje structured warehouse data; moguće je da Apros šalje agregirani stock čak i za B2B
- **Mitigation:** AP-07 validacija s realnim payload primjerom; Faza 2 warehouse implementacija striktno čeka potvrdu

---

**P1-04 — Dream Point workshop nije održan; UX arhitektura blokirana**

- **Probability:** HIGH (po definiciji — workshop se nije desio)
- **Impact:** Role sistem (DP-A01), MOQ (DP-B01), split shipment (DP-B02), payment metode (DP-A03) su pitanja čiji odgovori mogu zahtijevati rework IA-e, checkout-a i account arhitekture ako dođu kasno
- **Mitigation:** Workshop kao prioritet 1 paralelno s pripremom Apros sesije

---

**P1-05 — Approval webhook body: arhitektura ovisi o jednom API pozivu**

- **Probability:** MEDIUM-HIGH (format nevalidiran)
- **Impact:** Ako `sif_kup`, rabat i `advance_only` ne stižu u approval webhook-u → potreban je odvojeni partner sync mehanizam koji multiplicira kompleksnost; payment restrictions mogu biti netočne za novog korisnika
- **Mitigation:** AP-05 sesija; ne implementirati onboarding flow dok format nije potvrđen

---

### P2 — Operativni rizici (utječu na kvalitetu, ne blokiraju start)

---

**P2-01 — Full sync performance na 10.000 artikala + varijacija + slike**

- **Probability:** HIGH (B2C radi full sync; delta sync nevalidiran)
- **Impact:** Hourly cron može biti previše spor ili memorijski intenzivan za B2B katalog; API rate limit od Apros-a može ograničiti frekvenciju
- **Mitigation:** AP-06 delta sync validacija; ako delta nije dostupan, dizajnirati batch/streaming sync od početka

---

**P2-02 — advance_only/free_shipping flag update za aktivnog partnera**

- **Probability:** MEDIUM (promjene su rijetke ali moguće)
- **Impact:** Ako flag dolazi samo pri approval-u (ne periodično), promjena kreditnih uvjeta za aktivnog partnera neće se reflektirati u WC dok admin ručno ne ažurira
- **Mitigation:** AP-08 validacija mehanizma; definirati proces ručnog ažuriranja dok se ne implementira periodični partner sync

---

**P2-03 — Order status jednosmjernost (pretpostavka, nije potvrđena)**

- **Probability:** MEDIUM (nije potvrđeno da Apros ne šalje status update)
- **Impact:** Ako Apros šalje status update (confirmed, delivered, cancelled), a WC nema inbound endpoint, korisnik u My Account vidi statični status koji ne odražava ERP stanje
- **Mitigation:** AP-14 / checklist 3.6 sesija; definirati My Account UX na osnovu potvrđene jednosmjernosti

---

**P2-04 — OIB validacija i izvor nedefiniran**

- **Probability:** MEDIUM
- **Impact:** Prazan ili pogrešan OIB u Apros order payloadu može uzrokovati odbijanje narudžbe u ERP-u za B2B kupce
- **Mitigation:** Dream Point odluka o registracijskoj formi; HR OIB format validacija u formi; fallback strategija za kupce koji ne unese OIB

---

**P2-05 — Deaktivacijski webhook nije implementiran i nije definiran**

- **Probability:** MEDIUM (partneri prestaju poslovati)
- **Impact:** Deaktivirani partneri zadržavaju B2B pristup dok admin ručno ne interveni; na većim partner bazama ovo je operativni rizik
- **Mitigation:** Definirati ručni deaktivacijski proces; AP-09 pitanje o deaktivacijskom webhooку

---

## 7. Architecture Stability Assessment

### Ocjena: CONDITIONALLY STABLE

**Stabilni elementi:**

- **Reuse strategija** je solidna i zasnovana na produkcijskom kodu. Plugin analiza je detaljna i klasifikacija komponenti (REUSE/MODIFY/REWRITE) je dobro argumentirana.
- **Product import arhitektura** (variable product model, identifikacijski ključevi, image pipeline, change detection) je potvrđena produkcijskim ponašanjem.
- **Visibility sustav** je potvrđen kao vlasništvo WC CMS-a — ovo je važna arhitekturalna potvrda koja eliminira cijelu klasu integracijskog scope-a.
- **Rollout strategija** (3 faze: Izipizi → Lifestyle → Igračke) je dobro planirana.
- **Data ownership matrix** je jasna i konzistentna između dokumenata.
- **Blueprint Faza 1** (Infrastructure Preparation) nema blokirajućih pretpostavki i može početi.

**Uvjetno nestabilni elementi:**

- **Pricing engine**: arhitektura nije odabrana jer ovisi o nerazriješenom konfliktu. Svaki od tri modela je interno konzistentan, ali nijedan ne može biti implementiran. Odgoda P0-01 validacije direktno blokira Fazu 4.
- **Partner sync storage**: 1:1 pretpostavka je eksplicitno dokumentirana kao assumption, ali implementacijska sekcija (Faza 3) tretira je kao osnovu za dizajn. Ako Dream Point workshop potvrdi 1:N, Faza 3 dizajn mora biti redone.
- **Order export**: Blueprint Faza 5 je dobro konceptualizirana ali cijeli payload builder ovisi o AP-01 (pricing) i AP-02 (order format) koji su P0 blokeri.

**Nestabilni elementi:**

- **Auth infrastruktura**: Nijedan outbound API poziv nije "implementacijski siguran" dok auth model nije poznat. `CurlClient` proširenje je placeholder — konkretna implementacija je nepoznata.
- **Warehouse stock**: Opcija A vs B storage odabir je preuredan, ali nema podataka da Apros uopće šalje per-warehouse data. Ovo je može biti arhitektura za problem koji ne postoji u API-ju.

**Stabilizacija je moguća** nakon:
1. Apros API sesija s realnim payload primjerima
2. Dream Point workshopa
3. Ekstrakcije sales location routing logike (Josip)

---

## 8. Pre-Implementation Checklist

### MANDATORY — ne počinjati Faze 3–5 bez

- [ ] **Apros API pristup** (sandbox ili production) — BL-01; bez ovoga nijedna validacija nije moguća
- [ ] **AP-01 razriješen** — wholesalePrice semantika potvrđena s realnim payload primjerom; pricing model odabran
- [ ] **AP-02 razriješen** — order endpoint URL, format, obavezna polja, idempotency, response format dokumentirani
- [ ] **AP-03 razriješen** — auth mehanizam (API key / OAuth / Basic / IP whitelist) potvrđen; CurlClient proširenje implementirano i testirano
- [ ] **AP-04 razriješen** — partner endpoint format (sif_kup, pravni oblik, ugovorni uvjeti, dostavne lokacije) dokumentiran
- [ ] **AP-05 razriješen** — approval webhook body kompletna lista polja potvrđena
- [ ] **DP-01 razriješen** — sif_kup kardinalitet potvrđen od Dream Point-a; storage model finaliziran
- [ ] **DP-02 razriješen** — sales location routing logika ekstrahirana od Josipa i dokumentirana
- [ ] **Dream Point workshop održan** — role sistem, MOQ, payment metode, split shipment minimalno odgovoreni
- [ ] **Interne odluke INT-01 do INT-06** — search strategija, filter layout, quick order UX pattern dogovoreni

### MANDATORY za Fazu 2 (Product Sync Adaptation)

- [ ] **AP-07 razriješen** — warehouse stock payload format potvrđen s realnim primjerom; Opcija A ili B finalizirana
- [ ] **BL-02 razriješen** — B2B `articleList/get` payload primjer koji potvrđuje varijante format

### RECOMMENDED — visok prioritet, ali ne blokiraju start Faze 1

- [ ] Apros sandbox environment uspostavljen (ne samo production API pristup)
- [ ] **AP-06** — delta sync podrška potvrđena; ako ne postoji, batch/streaming sync dizajniran za full sync na 10k artikala
- [ ] Error response format (checklist 3.3) — potrebno za retry logiku
- [ ] OIB/VAT ID tok definiran (registracijska forma + validacija)
- [ ] Deaktivacijski webhook status potvrđen (AP-09 / checklist 3.2)
- [ ] Order status bidirectionality potvrđena (AP-14 / checklist 3.6)
- [ ] Apros API rate limits dokumentirani (checklist 3.7)
- [ ] Foto/media sync format potvrđen — stabilni URL-ovi ili re-download svaki sync? (checklist 4.2)

---

## 9. Zaključak

### Je li projekt spreman za ulaz u implementaciju?

**Ne — ne za core faze (3, 4, 5).**

**Da — za Fazu 1** (Infrastructure Preparation). Reuse strategija, baseline plugin skeleton, namespace/bootstrap, CurlClient placeholder, b2bArticle filter i monitor plugin kopija ne ovise o blokirajućim pretpostavkama.

### Što definiše granicu između discovery i implementacije?

Projekt neće biti spreman za ulaz u Faze 3–5 dok:

1. Apros API pristup ne postoji i ne može se validirati nijedna P0 stavka
2. Pricing model ostaje između dva konfliktna iskustvena signala bez arbitraže payloadom
3. `sif_kup` kardinalitet ostaje pretpostavka u dokumentu koji tretira user-meta kao storage osnovu
4. Sales location routing logika ostaje kod Josipa, ne u dokumentaciji

### Ocjena arhitekturalnog rada do sada

Arhitekturalni corpus koji je nastao u discovery fazi je **kvalitetan i transparentan** o granicama svog znanja. Blueprint eksplicitno bilježi koje su pretpostavke (ASSUMPTION oznake), koji su blokeri, i koje su ovisnosti. Discovery findings dokument jasno razdvaja potvrđene činjenice od nevalidiranih pretpostavki. Ovo je dobra inžinjerska praksa.

Slabosti koje su identificirane u ovom auditu nisu greške u reasoning-u — one su **last-mile validation gaps** koji se ne mogu popuniti bez izravnog Apros API pristupa i Dream Point workshopa. Projekt čeka na vanjske inpute, ne na interni rad.

Jedina strukturalna zabrinutost je **nekonzistentnost u bloker klasifikaciji** između validation checkliste (Priority 3) i blueprinta (P1) za sif_kup kardinalitet — ovaj gap treba biti usklađen u oba dokumenta.

---

*Dokument generiran na osnovu pregleda 6 arhitekturalnih dokumenata.*  
*Implementacijska stanja i pretpostavke odražavaju situaciju na dan 2026-06-08.*
