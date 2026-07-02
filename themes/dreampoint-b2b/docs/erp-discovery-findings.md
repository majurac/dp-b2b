# ERP Discovery Findings — Dream Point B2B ↔ Apros

**Discovery izvor:** Email korespondencija, Maj 2026
**Sudionici:** Armin Lusija (integrator), Leo Benkek / ZGData (tehnički), Marin Gelo / Dream Point (klijent), Apros tim (ERP)
**Companion dokument:** `docs/erp-validation-checklist.md` — konkretna pitanja za prvu Apros tehničku sesiju

> Ovaj dokument bilježi što je POTVRĐENO, što je PRETPOSTAVLJENO ali nevalidirano, i što blokira implementaciju.
> Ne sadrži arhitekturalne odluke, kod, niti DB dizajn.

---

## Status ERP integracije

**Faza:** Discovery / Validation — implementacija NIJE počela i ne smije početi.

**Blokirajući uvjet:** Sve stavke `[!]` u `docs/erp-validation-checklist.md` moraju imati potvrđene odgovore od Apros-a prije nego se napiše i jedan red Phase 4 koda.

---

## Potvrđena poslovna pravila

Eksplicitno potvrđeno od strane klijenta (Dream Point) i/ili Apros-a na osnovu mail korespondencije i sastanka u Maju 2026.

### Artikli i katalog

- ~10.000 artikala na novom B2B-u — ugovorno definirano, nije tehničko ograničenje
- Atribut na artiklu označava B2B eligibilnost (`b2bArticle`)
- `wholesalePrice` je proširenje koje Apros dodaje u `ArticleList`
- Atributi iz prethodnog sustava (oznaka "2", Arminov sustav) zadržavaju se na novom B2B-u
- Dva nova ERP-driven atributa na artiklu: "Novi proizvod na B2B DA/NE" i "Posebna ponuda"
- Kataloški broj artikla prikazuje se na stranici artikla — dolazi iz Apros-a; prikaz kataloškog broja ima prioritet nad SKU-om

### Partneri i korisnici

- `sif_kup` je jedinstveni identifikator poslovnog partnera u Apros-u
- Dva nova Apros atributa na razini partnera: `B2B KUPAC DA/NE`, `B2B E-MAIL`
- Dream Point određuje kome se otvara B2B pristup — Apros nije autoritet
- Inicijalna lista partnera: Dream Point šalje Excel Apros-u; nije automatski import svih Apros partnera
- Ugovorni uvjeti (koji definiraju rabat) Apros šalje Arminu; statični su — promjene (postotak rabata, odgoda plaćanja, dodatni rabat) idu kroz Apros i iznimno su rijetke
- Aktivne države kupaca: Hrvatska, Slovačka, Crna Gora, Bosna i Hercegovina, Slovenija
- ~60 ukupnih kupaca; ~10 aktivnih kupaca
- Migracija korisnika: Apros exportira postojeće korisnike; WooCommerce ih importira

### Cijene i rabati

- Apros šalje već izračunate (finalne) cijene — jača evidencija za Model B (vidi nevalidirane pretpostavke V-01)
- Iznos popusta **ne prikazuje se na produktnim ribbon-ima** — popust je vidljiv samo na fakturama
- "Rabat 1" šalje Apros po brandu; primjenjuje se na sve artikle tog branda
- **Promocijska cijena ima prioritet nad Rabat 1** — ako artikal ima promotivnu cijenu, ona se koristi umjesto rabata
- Promocije i posebne ponude dolaze iz Apros-a; postojat će zasebna promotivna stranica
- Prikaz na listingu: neto cijena s rabatom, bez PDV-a
- Prikaz u košarici: puni iznos s PDV-om
- Strani kupci: učitavanje cijena s cjenika za odgovarajuću državu
- PDV se ne primjenjuje na strane kupce
- Porezna kategorija kupca čita se iz "pravnog oblika" koji Apros šalje po kupcu

### Plaćanje

- Jedina metoda plaćanja u WooCommerce-u: **transakcijski račun (virman)**
- Nema CMS-level logike za odgođeno plaćanje — odgoda se upravlja izvan WooCommerce-a
- Napomena: `advance_only` flag od Apros-a ostaje relevantan za buduće proširenje; za inicijalnu implementaciju plaćanje je jedinstveno

### Količine

- **Nema minimalnih količina** (MOQ)
- **Nema maksimalnih količina**
- **Nema quantity stepova**
- Nativno WooCommerce upravljanje količinama

### Zalihe — rezervacija

- Nativno WooCommerce ponašanje prihvaćeno: **first completed order wins**
- Nema custom mehanizma rezervacije zaliha

### Zalihe i skladišta

- 4 skladišta sudjeluju u B2B-u: 1. Glavno, 3. Igračke, 4. Naočale, 5. Lifestyle
- Stanje prikazano po skladištu — ne agregirano
- Automatsko mapiranje branda na matično skladište: ako brand postoji na više skladišta, stanje se vuče samo s matičnog

### Narudžbe

- WooCommerce kreira narudžbu; Apros prima narudžbu — jednosmjeran tok
- Obavezna polja payload-a: `sif_kup`, odabrana dostavna lokacija, stavke narudžbe, količine
- Narudžbe se smještaju na prodajno mjesto prema vrsti robe (igračke = prodajno mjesto 3, lifestyle = 5)
- Zaključnice se obrađuju i kreiraju po pravilima postojećeg B2B-a — nema promjene u workflow-u

### Vidljivost — vlasništvo sustava

- Vidljivost artikala po kupcima upravljana je u B2B CMS-u, **ne u Apros-u**
- Apros ne vodi niti šalje vidljivost na B2B sustav
- Ovo potvrđuje arhitekturalni model implementiranog visibility sustava

### Sinkronizacija i rollout

- Cron-based sinkronizacija; inkrementalni sync (samo izmijenjeni podaci) je preferirani model
- **Nema paralelnog rada** — novi B2B ide u pogon samo kad je sve spremno; stari i novi webshop ne rade istovremeno
- **Nema migracije brojača narudžbi** — novi B2B kreće s nultim brojevima narudžbi
- Rollout u 3 faze: 1. Izipizi → 2. Lifestyle & Outdoor → 3. Igračke
- Trenutno ~99% narudžbi na starom B2B-u su igračke

---

## Nevalidirane pretpostavke

Prihvaćene u internoj analizi; **nisu potvrđene od Apros-a**. Ne smiju postati osnova implementacijskih odluka.

| ID | Pretpostavka | Zašto je kritično |
|----|-------------|------------------|
| V-01 | `wholesalePrice` je neto cijena s rabatom, ne bazna cijena | Ako je bazna, WC mora primjenjivati Rabat 1 dinamički → potpuno drugačija pricing arhitektura |
| V-02 | Rabat 1 dolazi uz partner endpoint ili zasebnim endpointom | Bez izvora podataka, pricing pipeline ne može biti implementiran |
| V-03 | Varijante su jasno odvojene od parent-a u `ArticleList` payload-u | Ako nisu, sync mora inferirati razliku — složeno i sklono greškama |
| V-04 | Apros order endpoint prima definiranu JSON strukturu | Format endpointa potpuno nepoznat — implementacija ne može početi |
| V-05 | Apros order endpoint vraća Apros order ID u response | Bez toga retry logika je opasna (duplikati narudžbi) |
| V-06 | `sif_kup` je 1:1 s WP korisnikom | Ako je 1:N (više WP korisnika dijeli sif_kup), pricing arhitektura se mijenja |
| V-07 | Delta sync podržan od Apros-a za artikle i partnere | Bez toga → full sync na 10.000 artikala pri svakom cron pozivu |
| V-08 | Stock payload je po skladištu s jasnom strukturom i identifikatorom | Multi-warehouse sync nema definiran model bez validacije |
| V-09 | "Pravni oblik" se može mapirati na WC tax klase | PDV izuzetak za strane kupce ne može biti implementiran bez ovoga |
| V-10 | Prodajno mjesto (3 ili 5) određuje se po atributu dostupnom u order payload-u | Ovisi o internom znanju iz starog B2B-a — jedini poznati izvor je Josip |
| V-11 | Dostavne lokacije imaju stabilan Apros location ID koji se može koristiti kao storage ključ | Svaki sync ciklus ne smije uništiti WC storage dostavnih lokacija |

---

## Kritična otvorena pitanja

Pitanja koja blokiraju arhitekturalne odluke — ne mogu se riješiti bez pristupa Apros API-ju.

1. **Struktura varijanti** — dolaze li kao zasebne stavke ili ugniježđene unutar parent artikla u `ArticleList`?
2. **Pricing delivery** — je li `wholesalePrice` završna neto cijena, ili bazna cijena? Kojim formatom i endpointom dolazi Rabat 1?
3. **Order endpoint** — koji je URL, HTTP metoda i format payload-a koji Apros očekuje?
4. **Auth mehanizam** — kojom metodom WP se autenticira prema Apros-u (API key, OAuth, Basic auth)?
5. **sif_kup kardinalitet** — model korisničkih računa još nije razriješen; dva scenarija: A (jedan korisnik, više dostavnih adresa) ili B (više korisnika, više dostavnih adresa); vidi DP-01
6. **Partner endpoint** — koji Apros endpoint isporučuje listu partnera s `sif_kup`, `B2B KUPAC`, pravnim oblikom i ugovornim uvjetima?
7. **Prodajno mjesto (Josip)** — kako stari B2B mapira narudžbu na prodajno mjesto 3 ili 5? Po kojim atributu artikla?
8. **Isti artikal u dva skladišta** — problem Lafaboo/Jekaa nije definiran; tko donosi odluku i koji je model?
9. **Order idempotency** — vraća li Apros order ID u confirmation? Je li endpoint idempotent?
10. **Pravni oblik vrijednosti** — koje su moguće vrijednosti "pravnog oblika" i kako se mapiraju na porezne kategorije?
11. **Invoice splitting mehanizam** — Apros dijeli fakture; nije poznato po kojem kriteriju Apros određuje kada dolazi do razdvajanja; treba procijeniti je li AP-06 potrebno proširiti ili dodati novu validacijsku stavku

---

## ERP implementacijski blokeri

Ovi uvjeti moraju biti ispunjeni prije nego Phase 4 može početi. Svaki bloker ima ID u `docs/erp-validation-checklist.md`.

| Bloker | Opis | Checklist ref |
|--------|------|---------------|
| BL-01 | Apros API pristup (sandbox ili production) ne postoji — nijedna validacija nije moguća | Sve [!] stavke |
| BL-02 | Struktura varijanti u `ArticleList` payload-u nepoznata | 1.4 |
| BL-03 | Pricing model: `wholesalePrice` vs. Rabat 1 mehanizam — najveća arhitekturalna nepoznanica | 1.1 |
| BL-04 | Apros order endpoint URL i format payload-a nepoznati | 2.2 |
| BL-05 | Auth mehanizam WP → Apros nije definiran | 2.5 |
| BL-06 | Josip / mehanizam mapiranja narudžbe na prodajno mjesto — jedini izvor internog znanja | Nema checklist stavke — interni |

---

## Discovery zaključak

Iz mail korespondencije utvrđeno je da su **poslovne domene i pravila dobro razumljeni** na visokoj razini. Klijent i Apros su dali konzistentne odgovore o tome što se želi. Nema proturječnih poslovnih zahtjeva.

**Tehnički detalji koji su neophodni za implementaciju — payload formati, API struktura, auth mehanizam, varijante, pricing delivery — u potpunosti su nevalidirani.**

Implementacija ne može početi do prve Apros tehničke API sesije gdje se odgovara na stavke Priority 1 (1.1–1.4) i Priority 2 (2.1–2.5) iz `docs/erp-validation-checklist.md`.

---

## Discovery Revision — Leo Benkek Email (rano B2B planiranje)

**Izvor:** Leopold Benkek (ZGData) — rezime B2B integration planning sastanka
**Datum dodavanja:** 2026-06-03
**Klasifikacija izvora:** Rani interni planinski dokument — nastao prije Maj 2026 mail korespondencije. Nije Apros tehnička validacija, ali dolazi od tehničkog integratora koji je direktno pregovarao s Apros (Zagreb Date) timom.

> Nalazi iz ovog emaila ne zamjenjuju validaciju Apros API sesijom. Blokeri ostaju aktivni dok Apros ne potvrdi direktno.

---

### Novo potvrđene činjenice

**NC-01 — Vidljivost je u vlasništvu B2B CMS-a (ne Apros-a)**
Email eksplicitno: "Kroz B2B CMS će se upravljati vidljivošću artikala po kupcima — te postavke nećemo voditi u APross-u i slati na B2B."
Arhitekturalna važnost: implementirani visibility sustav je arhitekturalno ispravan u modelu vlasništva. Apros ne kontrolira per-customer vidljivost.
*(Dodata u "Potvrđena poslovna pravila → Vidljivost" iznad.)*

**NC-02 — ArticleList je postojeći B2C endpoint, ne novi**
Email: "Za sada bi ipak ostao na starim endpointima za artikle jer već za B2C imamo varijacije, atribute, slike i ostalo, samo će se na postojeći endpoint `articleList/get` dodati `b2bArticle` i `wholesalePrice`."
Implikacija: B2B ne uvodi novi artikal endpoint — proširuje se poznati B2C endpoint s dva nova polja.

**NC-03 — Tri potvrđena endpoint tipa**
1. `articleList/get` (postojeći B2C, proširen)
2. Lista poslovnih partnera
3. Lista primatelja / dostavnih lokacija po partneru (s ugovornim uvjetima i cjenicima)

**NC-04 — Dvoslojni pricing mehanizam (domaći vs. strani kupci)**
Email opisuje dvije različite isporuke cijena unutar "Liste primatelja":
- (a) **Ugovorni uvjeti**: sif_kup + brand + postotak rabata → mehanizam Rabat 1 za domaće partnere
- (b) **Cjenici po državama**: šalje se **finalna neto cijena**, ne postotak → strani kupci ne dobivaju rabat na bazi, dobivaju gotovu cijenu

Ovo je precizniji opis nego što je prethodno bio dostupan.

**NC-05 — Onboarding tok opisan**
Web forma → email notifikacija na Točku sna → ručno otvaranje u Apros → odobrenje B2B atributom u Apros-u.
*(Razrađuje i potvrđuje PE-03.)*

---

### Pretpostavke sada podržane dokazima

| ID | Pretpostavka | Prethodni status | Novi status | Razlog |
|----|-------------|-----------------|-------------|--------|
| V-03 | Varijante su jasno odvojene od parent-a u ArticleList payload-u | Nevalidirana pretpostavka | **Podržano dokazima** | B2C endpoint već ima varijacije — B2B proširuje isti endpoint |
| V-02 | Rabat 1 dolazi uz partner endpoint ili zasebnim endpointom | Nevalidirana pretpostavka | **Podržano dokazima** | Ugovorni uvjeti (sif_kup + brand + postotak rabata) eksplicitno navedeni |
| PE-03 | Onboarding kupaca je ručan | Nevalidirana izjava | **Podržano dokazima** | Detaljni tok opisan u emailu |

---

### Refinement — V-01 (wholesalePrice semantika)

**Status: Konfliktni dokazi — prioritetno pitanje za Apros sesiju.**

---

**Originalna pretpostavka (V-01):** `wholesalePrice` je neto cijena s rabatom, ne bazna cijena.

**Leo Benkek email (implicira — nije eksplicitno):**
`wholesalePrice` je na razini artikla (isti za sve partnere). Rabat (`postotak rabata`) je zasebno na razini partnera, per brand, iz ugovornih uvjeta. Ova struktura **implicira** da je `wholesalePrice` bazna/katalog cijena, a Rabat 1 je mehanizam koji daje finalnu per-partner cijenu.

**Milenko Stojaković (implementacijsko iskustvo — 2026-06-03):**
"VPC (wholesalePrice) is generally the final B2B product price, but an additional Rabat 1 may also be applied. They have separate meanings."
Ovo **implicira** da je `wholesalePrice` obično već finalna B2B cijena — Rabat 1 je opcionalni dodatni sloj koji se primjenjuje u određenim slučajevima.

**Konflikt:**

| Izvor | Interpretacija wholesalePrice | Uloga Rabat 1 |
|-------|------------------------------|--------------|
| Leo Benkek email | Bazna/katalog cijena (ista za sve) | Obavezan per-partner mehanizam da se dobije finalna cijena |
| Milenko Stojaković | Generalno finalna B2B cijena | Opcionalni dodatni sloj (nije uvijek prisutan) |

Oba izvora su implementacijsko iskustvo, ne potvrda od Apros-a. Nijedan ne može biti tretiran kao arhitekturalna osnova.

**Moguće pomirenje (niti potvrđeno niti isključeno):**
`wholesalePrice` može biti per-partner finalna cijena (Apros je preračunava per-kupac na Apros strani), s Rabat 1 kao rijetkim dodatnim slojem. To bi značilo da WC samo prikazuje vrijednost — ali zahtijeva potvrdu da je payload zaista per-partner, a ne jedan per-artikal za sve.

**Arhitekturalna implikacija koja se mora potvrditi:**
- Model A (Leo): `wholesalePrice` (baza) × (1 − Rabat 1%) = finalna neto cijena → WC mora računati per-partner
- Model B (Milenko): `wholesalePrice` = već finalna cijena → WC samo prikazuje; Rabat 1 je rijedak dodatak
- Oba modela imaju potpuno drugačiju pricing implementacijsku arhitekturu

**Klasifikacija V-01 nakon konflikta:** Nevalidirana pretpostavka — konfliktni iskustveni signali. Povjerenje u pricing model SMANJENO u odnosu na period neposredno nakon Leo emaila. Zahtijeva payload primjer s realnim vrijednostima.

**Precizno pitanje za Apros sesiju:**
*"Potvrdite egzaktnu vezu između `wholesalePrice`, Rabat 1 i finalne cijene za B2B kupca. Je li `wholesalePrice` ista vrijednost za sve partnere (bazna cijena) ili je per-partner (već preračunata finalna cijena)? Primjenjuje li se Rabat 1 uvijek, samo za određene partnere, ili nikad automatski? Dostavite realni payload primjer koji prikazuje obje vrijednosti za jednog partnera s Rabat 1 i jednog bez."*

---

### Što ostaje neriješeno

Sljedeće stavke email **ne adresira** — blokeri ostaju nepromijenjeni:

- **BL-01**: Apros API pristup — ništa se ne može validirati bez sandbox-a
- **BL-04 / V-04**: Order endpoint URL, format, payload — nije pomenuto
- **BL-05 / 2.5**: Auth mehanizam WP → Apros — nije pomenuto
- **V-05**: Order idempotency / Apros order ID u responsu — nije pomenuto
- **V-06**: `sif_kup` kardinalitet (1:1 vs. 1:N) — nije pomenuto
- **V-09**: "Pravni oblik" → WC tax class mapping — nije pomenuto
- **V-10**: Prodajno mjesto mapping (Josip) — nije pomenuto
- **V-11**: Stabilnost Apros location ID-a između sync ciklusa — nije pomenuto
- **V-08**: Stock payload format po skladištu — nije pomenuto

---

### Reklasifikacija rizika

| Bloker/Pretpostavka | Prethodni rizik | Novi rizik | Promjena |
|---------------------|----------------|-----------|----------|
| BL-02 (struktura varijanti nepoznata) | HIGH bloker | **MEDIUM** | B2C endpoint već ima varijacije; B2B proširuje isti endpoint; format je vjerojatno poznat integratoru |
| V-03 (varijante u payload-u) | Nevalidirana pretpostavka | Podržano dokazima | Više nije čista pretpostavka |
| V-02 (Rabat 1 isporuka) | Nevalidirana pretpostavka | Podržano dokazima | Endpoint tip i struktura navedeni |
| PE-03 (ručni onboarding) | Nevalidirana izjava | Podržano dokazima | Detaljan tok potvrđen |
| BL-01, BL-03, BL-04, BL-05 | HIGH blokeri | HIGH blokeri | **Bez promjene** |

BL-02 nije eliminiran — potrebna je validacija stvarnog B2B payload formata, ali historijski B2C endpoint maturity značajno smanjuje vjerovatnoću iznenađenja.

---

## Implementacijsko iskustvo — Prethodna Apros B2C integracija

**Izvor:** Milenko Stojaković, 2026-06-03
**Kontekst:** Prethodno iskustvo s Dream Point ↔ Apros B2C integracijom.

> Ove stavke nisu potvrđene činjenice za B2B integraciju. Bilježe se radi očuvanja znanja i kao signali za prioritizaciju pitanja na prvoj Apros tehničkoj sesiji. Klasifikacijska oznaka je obavezna — ne mijenjati bez nove validacije.

| ID | Tvrdnja | Klasifikacija |
|----|---------|---------------|
| PE-01 | Ako Apros izloži potrebne endpointe, implementacijska složenost se očekuje manja | Radna pretpostavka |
| PE-02 | Prethodna B2C implementacija koristila: product sync, popuste po grupama/državama, slanje narudžbi | Potvrđena implementacijska činjenica (B2C) |
| PE-03 | Onboarding kupaca je prema izjavi bio ručan | Nevalidirana izjava |
| PE-04 | Pricing složenost može biti niža nego što se prethodno pretpostavljalo | Radna pretpostavka — izvodi se iz PE-05 |
| PE-05 | Prema iskustvu, Apros je prihvaćao WooCommerce-izračunate cijene umjesto internog preračunavanja | Implementacijsko iskustvo (B2C) |
| PE-06 | Rukovanje skladištima vjerojatno se odvija interno unutar Apros-a | Radna pretpostavka |
| PE-07 | VPC (wholesalePrice) je generalno finalna B2B cijena proizvoda, ali Rabat 1 se može primijeniti kao dodatni sloj; imaju zasebna značenja | Implementacijsko iskustvo (B2C) — 2026-06-03 |

### Utjecaj na aktivne blokere

**PE-05 i PE-07 utječu na BL-03** (pricing model — najveća arhitekturalna nepoznanica), ali u različitim smjerovima:

- **PE-05** sugerira da Apros prihvaća WC-izračunate cijene → manji vlastiti pricing engine
- **PE-07** sugerira da je `wholesalePrice` već finalna cijena → WC samo prikazuje, ne računa

**PE-07 je u napetosti s interpretacijom iz Leo Benkek emaila** (koji je implicirao da je wholesalePrice bazna cijena a Rabat 1 obavezan per-partner mehanizam). Vidi sekciju "Refinement — V-01" za detalje konflikta.

Oba signala zajedno ne daju konzistentan model — dva nezavisna izvora implementacijskog iskustva govore različito o semantici `wholesalePrice`. To **povećava prioritet AP-01 pitanja** za prvu Apros sesiju i zahtijeva realni payload primjer kao jedini način razrješenja.

**BL-03 ostaje aktivan bloker.** Povjerenje u pricing model je SMANJENO u odnosu na period neposredno nakon Leo emaila — konflikt izvora zahtijeva direktnu Apros validaciju.

Svi ostali blokeri (BL-01 do BL-06) ostaju nepromijenjeni.

---

## Discovery Revision — Apros Response Integration Update

**Izvor:** Direktan odgovor Apros tima (pricing, autentikacija, order endpoint, partner approval flow, dostavne lokacije)
**Datum dodavanja:** 2026-07-02
**Klasifikacija izvora:** Prva direktna Apros tehnička potvrda — viša evidencijska razina od svih prethodnih izvora (Leo Benkek email, Milenko Stojaković iskustvo). Konflikt oko V-01 semantike `wholesalePrice` je ovime razriješen za arhitekturalnu razinu; egzaktni payload primjeri (field nazivi, realne vrijednosti) i dalje nedostaju.

> Discovery ostaje formalno zatvoren. Ovo je nova evidencija koja se dodaje na postojeći nalaz, ne ponovno otvaranje diskusije.

---

### Novo potvrđene činjenice

**NC-06 — Pricing model za domaće kupce razriješen (Model A potvrđen)**
`wholesalePrice` = bazna veleprodajna neto cijena. Rabat 1 = postotni popust po partneru i brandu. Finalna cijena = `wholesalePrice − Rabat 1`. Ovo razrješava konflikt iz sekcije "Refinement — V-01" u korist Leo Benkek interpretacije (Model A) — Milenko Stojakovićevo iskustveno zapažanje (Model B) se odnosilo na B2C kontekst i ne primjenjuje se na B2B domaći tok.

**NC-07 — Pricing model za strane kupce razriješen (Model C potvrđen)**
Nema Rabat 1 mehanizma za strane kupce. Finalna neto cijena dolazi direktno iz `countryPriceList`. PDV tretman ovisi o pravnoj/poreznoj kategoriji partnera (isto kao ranije potvrđeno).

**NC-08 — Autentikacija potvrđena**
Auth mehanizam prema Apros API-ju: **API Key**. Isti pristup kao postojeća Jekaa B2C integracija.

**NC-09 — Order endpoint: obavezna polja potvrđena (payload primjer i dalje nedostaje)**
Obavezna polja u order payloadu: `sif_kup`/`partnerId`, `partnerDeliveryLocationId`, stavke narudžbe s količinama. Success/error response format nije dostavljen.

**NC-10 — Partner approval flow reklasificiran: nema potvrđenog webhooka**
Apros nije potvrdio approval webhook. Potvrđeni tok: web registracija → email notifikacija → ručno kreiranje partnera u Apros-u → atribut `B2B KUPAC = DA` → partner se pojavljuje na partner list endpointu. Ovo zamjenjuje raniju NC-05/PE-03 pretpostavku o webhook signalu (korak "[5] Apros šalje signal CMS-u" u ranijem opisu toka se povlači) — sinkronizacija partnera mora biti **periodic polling/import**, ne webhook-driven.

**NC-11 — Dostavne lokacije potvrđene**
Partner može imati više dostavnih lokacija. **Ne postoji default lokacija** — korisnik bira lokaciju pri naručivanju. Endpoint: `partnerDeliveryLocationList`.

---

### Razrješenje V-01 (wholesalePrice semantika)

**Status: RAZRIJEŠENO na arhitekturalnoj razini.** Prethodni konflikt (vidi "Refinement — V-01" iznad) je zatvoren direktnim Apros odgovorom:

| Segment | Model | Formula / mehanizam |
|---------|-------|---------------------|
| Domaći kupci | **Model A** | `wholesalePrice − Rabat 1 (%)` = finalna cijena |
| Strani kupci | **Model C** | `countryPriceList` isporučuje gotovu finalnu cijenu |

**Što i dalje nedostaje:** egzaktan payload primjer (`articleList/get` + ugovorni uvjeti response za istog partnera, `countryPriceList` format, `partnerBrandDiscountList` format). Bez ovoga implementacija pricing engine-a ne može biti finalizirana — vidi `docs/apros-session-final-pack.md` sekciju "Still Required From Apros".

---

### Ažurirane pretpostavke i blokeri

| ID | Stavka | Prethodni status | Novi status | Razlog |
|----|--------|-------------------|--------------|--------|
| V-01 | `wholesalePrice` semantika | Konfliktni iskustveni signali | **Razriješeno (arhitektura)** — payload primjer pending | Apros potvrdio Model A (domaći) + Model C (strani) |
| BL-03 | Pricing model | HIGH | **MEDIUM** — arhitektura poznata, payload primjer nedostaje | Isto |
| BL-04 | Order endpoint format | HIGH | **MEDIUM** — obavezna polja poznata, puni payload/response format nedostaje | NC-09 |
| BL-05 | Auth mehanizam WP → Apros | HIGH | **RESOLVED** — API Key, isti pristup kao Jekaa B2C | NC-08 |
| — | Partner approval webhook | Pretpostavljen (NC-05/PE-03) | **Rekvalificirano** — nema potvrđenog webhooka; polling/import model | NC-10 |
| — | Dostavne lokacije | Nevalidirano (V-11 djelomično) | **Djelomično razriješeno** — više lokacija, nema defaulta, endpoint poznat | NC-11 |

**BL-01 (Apros API pristup) i BL-06 (Josip / prodajno mjesto) ostaju nepromijenjeni** — ovaj odgovor ih ne adresira.

**V-11 (stabilnost Apros location ID-a između sync ciklusa) ostaje djelomično otvoreno** — potvrđeno je da lokacija nema default, ali stabilnost ID-a između sync ciklusa nije eksplicitno potvrđena.

---

### Što ostaje potrebno od Apros-a

Detaljna lista u `docs/apros-session-final-pack.md` → sekcija "Still Required From Apros". Sažetak: realni payload primjeri za pricing (domaći + strani partner), order request/response, `countryPriceList`, `partnerBrandDiscountList`, i `partnerDeliveryLocationList`.
