# Dreampoint B2B — Discovery Status & Decision Matrix

**Verzija:** 1.2 | **Datum:** 2026-06-03 | **Ažurirano:** 2026-07-02 (Final Architecture Reassessment)
**Namjena:** Project management, workshop priprema, onboarding novih članova tima
**Kompanija:** Dream Point | **Integrator:** ZGData (Leopold Benkek) | **ERP:** Apros (Zagreb Date)

> **Revizija 2026-07-02:** Apros je dao direktan odgovor na pricing, autentikaciju, order endpoint, partner approval flow i dostavne lokacije. Detalji: `docs/erp-discovery-findings.md` → "Discovery Revision — Apros Response Integration Update". Ovaj dokument je ažuriran u skladu s tim odgovorima; puni payload primjeri i dalje nedostaju (vidi `docs/apros-session-final-pack.md` → "Still Required From Apros"). ADR-ovi za pricing i partner approval arhitekturu: `docs/decisions.md`.

> **Kako čitati ovaj dokument:**
> Sekcija 0 = finalna reprocjena arhitekture (najnovije). Sekcija 1 = što znamo. Sekcija 2–3 = što moramo pitati i od koga. Sekcija 4 = što tim sam odlučuje. Sekcija 5 = gdje smo sada. Sekcija 6 = šta je sljedeće.

---

## 0. Final Architecture Reassessment (2026-07-02)

> Reprocjena nakon integracije Apros odgovora. Zamjenjuje ranije procjene u Sekciji 5 gdje se razlikuju — Sekcija 5 je zadržana radi historijske sljedivosti.

### 0.1 Overall Project Readiness — RECALCULATED

| Faza | Prethodna procjena | Nova procjena | Razlog promjene |
|------|--------------------|--------------:|------------------|
| Faza 1 — Infrastructure | ✅ 100% spremno | ✅ 100% spremno | Bez promjene |
| Faza 2 — Product Sync | ⚠️ ~70% | ⚠️ ~70% | Bez promjene — čeka WH-01 (warehouse format), nepovezano s ovim odgovorom |
| Faza 3 — Partner Sync | 🔴 0% (webhook blokiran) | ⚠️ ~55% | Arhitektura poznata (polling/import); čeka PL-01, AP-07 payload, DP-01 |
| Faza 4 — Pricing Engine | 🔴 ~20% (model nepoznat) | ⚠️ ~65% | Model odabran (A+C); čeka AP-01 payload |
| Faza 5 — Order Export | 🔴 ~25% (auth+polja nepoznati) | ⚠️ ~60% | Auth resolved, obavezna polja poznata; čeka AP-06 payload + DP-02 |
| Faza 6 — Validation/Testing | 🔴 0% | 🔴 0% | **Bez promjene — BL-01 (Apros sandbox) i dalje ne postoji; ništa se ne može end-to-end testirati** |

**Ponderisana ukupna spremnost projekta: ~58%** (prosjek 6 faza; Faza 6 vuče prosjek naniže jer je potpuno neovisna o ovoj rundi odgovora).

**Ključni nalaz:** Arhitekturalna spremnost je skočila sa ~36% na ~58% ovom rundom odgovora. Međutim, **BL-01 (nepostojanje Apros sandbox/API pristupa) je jedini faktor koji određuje kada se bilo šta od ovoga stvarno može validirati i pustiti u produkciju** — i taj faktor je **potpuno nepromijenjen** ovim odgovorom. Arhitekturalni napredak ne prevodi se automatski u go-live napredak.

### 0.2 Implementation Risk — RECALCULATED

| Oblast | Prethodni rizik | Novi rizik | Napomena |
|--------|-----------------:|-----------:|----------|
| Pricing (arhitektura) | 🔴 VISOK | 🟢 NIZAK | Model odabran, konflikt razriješen |
| Pricing (payload/implementacija) | — | 🟡 SREDNJI | Field-level greške i dalje moguće bez payload primjera |
| Order export (arhitektura) | 🔴 VISOK | 🟡 SREDNJI | Obavezna polja poznata |
| Order export (idempotency/duplikati) | 🔴 VISOK | 🔴 VISOK | **Bez promjene** — i dalje najveći poslovni rizik projekta (duplirane narudžbe) |
| Auth | 🔴 VISOK | 🟢 NIZAK | Resolved — API Key |
| Partner approval/sync (arhitektura) | 🔴 VISOK | 🟢 NIZAK | Resolved — polling/import |
| Partner storage model (DP-01) | 🟡 SREDNJI | 🟡 SREDNJI | Bez promjene — i dalje nerazriješena interna odluka s rework rizikom |
| Sandbox/pristup (BL-01) | 🔴 KRITIČAN | 🔴 KRITIČAN | **Bez promjene** — jedini faktor koji blokira svaku daljnju validaciju |

**Ukupni rizik projekta: SREDNJI-VISOK (down from VISOK).** Sniženje je isključivo na arhitekturalnom nivou; operativni/pristupni rizik (BL-01) i finansijski rizik (order duplikati) ostaju na istom nivou kao prije ove runde odgovora.

### 0.3 Blocker Re-Ranking (svi aktivni blokeri, po preostaloj težini)

| Rang | ID | Bloker | Zašto je i dalje #1 razlog za brigu na ovoj poziciji |
|------|-----|--------|------------------------------------------------------|
| 1 | **BL-01** | Apros API/sandbox pristup ne postoji | Blokira validaciju SVIH payload primjera i cijelu Fazu 6 — jedini blocker koji ova runda odgovora nije dotakla |
| 2 | **AP-06** | Order endpoint — URL, response format, idempotency | Najveći finansijski rizik (duplirane narudžbe); obavezna polja poznata, ali finalizacija nemoguća bez ovoga |
| 3 | **AP-01** | Pricing payload (`partnerBrandDiscountList`, `countryPriceList`) | Model poznat, ali field-level greška u implementaciji = pogrešna cijena naplaćena kupcu |
| 4 | **DP-01** | sif_kup kardinalitet (1:1 vs 1:N) | Interna odluka, ali određuje storage arhitekturu cijele Faze 3; migracija nakon starta je skupa |
| 5 | **AP-07** | Delivery locations payload | Blokira checkout UI finalizaciju i Fazu 3 storage strukturu |
| 6 | **DP-02 / BL-06** | Sales location routing mehanizam | Isti bloker praćen pod dva ID-a u različitim dokumentima — jedini poznati izvor je Josip; greška šalje narudžbu na pogrešno prodajno mjesto |
| 7 | **PL-01** | Partner list endpoint format | Nema kanonski AP-ID; blokira Fazu 3 finalizaciju uz AP-07 |
| 8 | **WH-01** | Warehouse stock payload format | Nema kanonski AP-ID; blokira Fazu 2 finalizaciju |
| 9 | **AP-05** | Delta sync podrška | Čisto operativna optimizacija — cron dizajn radi i bez ovoga |

**Promjena u odnosu na prethodni rang:** AP-09 (auth) i AP-03 (approval) su ispali sa liste — resolved. AP-08 ispao — out of scope. BL-03/BL-04/BL-05 (stari nazivi) su zamijenjeni preciznijim AP-01/AP-06/AP-07 stavkama. **BL-01 je jedini blocker koji je bio #1 i prije i ostaje #1 sada** — ništa u ovoj rundi odgovora nije promijenilo njegov status.

### 0.4 New Top 5 Unknowns (post Apros-response)

1. **BL-01 — Kada i kako Dream Point/integrator dobija Apros sandbox pristup?** Bez datuma za ovo, cijeli payload-zavisni rad (rang 2, 3, 5, 7, 8 iznad) nema realan timeline.
2. **AP-06 payload — Koji je URL, HTTP metoda i response format B2B order endpointa, i je li idempotent?** Najviši finansijski rizik od svih preostalih nepoznanica.
3. **AP-01 payload — Koji su egzaktni field nazivi u `partnerBrandDiscountList` i `countryPriceList`?** Model je poznat, implementacija nije moguća bez ovoga.
4. **DP-01 — Jedan WP korisnik po firmi ili više?** Čisto interna odluka (ne treba Apros), a i dalje neodgovorena — lakše i jeftinije riješiti sada nego nakon Faze 3.
5. **AP-07 payload — Koja su adresna polja u `partnerDeliveryLocationList` i je li Apros location ID stabilan između sync ciklusa?** Utječe i na Fazu 3 storage i na checkout UI.

*(Časna napomena: DP-02/BL-06 — mehanizam mapiranja prodajnog mjesta — bio bi #6; ostaje jednako hitan kao #5 gore, ali je striktno "Top 5" lista po instrukciji.)*

### 0.5 Implementation Percentage That Can Start Today

Na osnovu 15 implementacijskih koraka u `docs/b2b-erp-migration-plan.md`:

| Kategorija | Koraci | Broj | % |
|---|---|---:|---:|
| ✅ Potpuno odblokirano (može odmah, bez ograničenja) | 1, 2, 3, 4, 5, 7, 8, 10, 14 | 9 | **60%** |
| ⚠️ Djelomično odblokirano (arhitektura/skeleton može odmah, finalizacija čeka payload) | 6, 9, 11, 12, 13 | 5 | **33%** |
| 🔴 Potpuno blokirano | 15 (E2E validacija — čeka Apros sandbox) | 1 | **7%** |

**→ 60% implementacije može startati danas bez ikakvih ograničenja. Kombinovano sa djelomično odblokiranim koracima, ~93% roadmapa ima neki oblik aktivnog posla koji može početi danas — jedino Faza 6 (finalna E2E validacija) je u potpunosti blokirana, i to isključivo zbog BL-01.**

### 0.6 Proposed Updated Phase Boundaries

Promjena u odnosu na plan iz `docs/b2b-erp-migration-plan.md` — cilj je razdvojiti "arhitekturu koja je poznata" od "finalizacije koja čeka payload", umjesto da cijela faza čeka na jedan payload primjer.

- **Faza 1 — Infrastructure + Auth + Partner Polling Skeleton.** Auth implementacija (Korak 14) i partner polling/import job skeleton (Korak 10) se **pomjeraju iz kasnijih faza u Fazu 1** — oba su potpuno resolved i ne moraju čekati.
- **Faza 2 — Product Sync.** Nepromijenjeno — i dalje čeka WH-01.
- **Faza 3 — Partner Sync.** Nepromijenjeno u obimu, ali sada eksplicitno djelomično startabilna (storage skeleton + polling logika mogu početi; finalna polja čekaju PL-01 + AP-07 + DP-01).
- **Faza 4 podijeljena:**
  - **Faza 4a — Pricing Engine Skeleton** (može početi odmah): filter hook arhitektura, storage šema (Model A + Model C), caching strategija, PDV logika po tax profilu.
  - **Faza 4b — Pricing Field Finalization** (čeka AP-01 payload): mapiranje stvarnih field naziva, QA s realnim partner podacima.
- **Faza 5 podijeljena:**
  - **Faza 5a — Order Export Builder Skeleton** (može početi odmah): Action Scheduler job struktura, payload builder s potvrđenim obaveznim poljima (`sif_kup`/`partnerId`, `partnerDeliveryLocationId`, items+količine), retry/admin UI.
  - **Faza 5b — Order Export Finalization** (čeka AP-06 payload + DP-02): URL, response parsing, idempotency guard, sales location routing.
- **Faza 6 — Validation & Testing.** Nepromijenjeno — gate ostaje Apros sandbox (BL-01); ne pomjera se ranije bez obzira na arhitekturalni napredak.

**Preporuka:** Ne čekati BL-01 da bi se počeo posao — Faze 1, 2 (djelomično), 3 (djelomično), 4a i 5a pokrivaju većinu tima dok se sandbox pristup rješava paralelno.

---

## 1. CONFIRMED FACTS

*Samo potvrđene informacije iz više nezavisnih izvora. Ništa ovdje nije pretpostavka.*

---

### 1.1 ERP sistem i endpointi

- ERP: **Apros** (Zagreb Date)
- Integracijski model: **WooCommerce povlači podatke iz Apros-a** (pull model, ne push)
- Tri potvrđena endpoint tipa:
  - `articleList/get` — postojeći B2C endpoint, proširen s `b2bArticle` i `wholesalePrice`
  - Lista poslovnih partnera
  - `partnerDeliveryLocationList` — dostavne lokacije po partneru + ugovorni uvjeti + cjenici po državama ✅ POTVRĐENO 2026-07-02
- B2C endpoint za artikle **već sadrži** varijacije, atribute i slike — B2B proširuje isti endpoint
- **Autentikacija: API Key** — isti pristup kao Jekaa B2C ✅ POTVRĐENO 2026-07-02

### 1.2 Katalog proizvoda

- ~10.000 artikala na novom B2B sistemu (ugovorno definisano)
- Svaki artikal ima atribut **`b2bArticle`** (true/false) koji određuje B2B eligibilnost
- **`wholesalePrice`** je Apros proširenje dodano na `articleList/get`
- Kataloški broj artikla prikazuje se na stranici artikla (PDP)
- Atributi: **NOVI PROIZVOD**, **POSEBNA PONUDA** — dolaze iz ERP-a
- Atributi prethodnog sistema (Arminov) zadržavaju se na novom B2B-u
- Promotivna funkcionalnost: artikli ili grupe artikala mogu biti dodani u posebne ponude/akcije

### 1.3 Cijene i rabati

**✅ Pricing arhitektura POTVRĐENA od Apros-a (2026-07-02)** — vidi `docs/erp-discovery-findings.md` NC-06/NC-07:

| Segment | Model | Mehanizam |
|---------|-------|-----------|
| Domaći kupci | **Model A** | `wholesalePrice` (bazna cijena) − Rabat 1 (% po partneru/brandu) = finalna cijena |
| Strani kupci | **Model C** | `countryPriceList` isporučuje gotovu finalnu neto cijenu, bez Rabat 1 |

| Kontekst | Šta se prikazuje |
|----------|-----------------|
| Listing | Neto cijena **s uključenim rabatom**, bez PDV-a |
| Košarica / checkout | Puni iznos **s PDV-om** |
| Strani kupci — listing | Finalna neto cijena iz `countryPriceList` (ne % rabat) |

- **Iznos popusta ne prikazuje se na ribbon-ima** — vidljiv samo na fakturama
- **Rabat 1** dolazi iz **ugovornih uvjeta** endpoint-a: struktura = `sif_kup` + brand + postotak rabata
- Rabat se dodjeljuje po brandu i primjenjuje na sve artikle tog branda
- **Promocijska cijena ima prioritet nad Rabat 1** — ako artikal ima aktivnu promocijsku cijenu, ona se koristi, ne rabat
- Promotivne ponude dolaze iz Apros-a; na platformi će postojati zasebna promotivna stranica
- Porezna kategorija kupca čita se iz "pravnog oblika" koji Apros šalje po kupcu
- Promjene rabata (postotak, odgoda plaćanja) idu kroz Apros i iznimno su rijetke

**Još potrebno:** realni payload primjer (`articleList/get` + ugovorni uvjeti za istog domaćeg partnera; `countryPriceList` primjer za stranog partnera; `partnerBrandDiscountList` format). Bez ovoga pricing engine implementacija ne može biti finalizirana. Vidi `docs/apros-session-final-pack.md` → "Still Required From Apros".

### 1.4 Skladišta

- 4 skladišta u B2B sistemu:

| ID | Naziv |
|----|-------|
| 1 | Glavno skladište |
| 3 | Skladište igračaka |
| 4 | Skladište naočala |
| 5 | Lifestyle skladište |

- Stanje se prikazuje **po skladištu** — ne agregirano
- Brandovi imaju **matično skladište** — stanje se vuče samo s matičnog, čak i ako artikal postoji na više
- Default ponašanje za out of stock: artikal se prikazuje bez stanja, bez mogućnosti narudžbe

### 1.5 Partneri i kupci

- **`sif_kup`** = jedinstven identifikator poslovnog partnera u Apros-u
- Novi Apros atributi na razini partnera: `B2B KUPAC DA/NE`, `B2B E-MAIL`
- **Dream Point određuje ko dobija B2B pristup** — Apros nije autoritet za pristup
- Inicijalna lista partnera: Dream Point šalje Excel Apros-u; nije automatski import svih Apros partnera
- Ugovorni uvjeti su statički — promjene (postotak rabata, odgoda plaćanja) idu kroz Apros, iznimno rijetko
- **Aktivne države kupaca:** Hrvatska, Slovačka, Crna Gora, Bosna i Hercegovina, Slovenija
- **~60 ukupnih kupaca; ~10 aktivnih**
- **Migracija korisnika:** Apros exportira postojeće korisnike; WooCommerce importira

### 1.6 Vidljivost artikala — vlasništvo

- **Vidljivost artikala po kupcima je u vlasništvu B2B CMS-a** (WooCommerce/WordPress)
- Apros **ne upravlja** per-customer vidljivošću i ne šalje vidljivost na B2B
- Vidljivost sistem (bucket rules, per-user catalog access) je implementiran i testiran u B2B CMS-u

### 1.7 Registracija i onboarding

**⚠️ REKLASIFICIRANO 2026-07-02:** Apros nije potvrdio approval webhook. Nema koraka "Apros šalje signal CMS-u" — sinkronizacija partnera je **periodic polling/import**, ne webhook-driven.

Potvrđen flow:
1. Novi partner popunjava web registracionu formu (native WooCommerce)
2. Email notifikacija stiže na "Točku sna" (interni kontakt Dream Point-a)
3. Partner se ručno otvara u Apros-u
4. Apros dodjeljuje atribut odobrenja za B2B Webshop (`B2B KUPAC = DA`)
5. Partner se pojavljuje na partner list endpointu — CMS ga preuzima periodičnim pollingom/importom, ne webhook signalom

**Implikacija:** Partner sync arhitektura mora biti dizajnirana kao cron-based polling job koji provjerava partner list endpoint za nove/promijenjene `B2B KUPAC = DA` zapise, ne kao inbound webhook receiver.

**Višestruki korisnici po firmi:** Svaki korisnik registruje zaseban nalog, čak i ako pripada istoj firmi.

### 1.8 Narudžbe i rollout

- Narudžbe su jednosmjerne: WooCommerce kreira → Apros prima
- Routing narudžbi: igračke = prodajno mjesto 3, lifestyle = prodajno mjesto 5
- Zaključnice se obrađuju po pravilima postojećeg B2B-a
- Cron-based sinkronizacija; inkrementalni sync preferiran
- **Nema paralelnog rada** — novi B2B ide u pogon tek kad je sve spremno; stari i novi webshop ne rade istovremeno
- **Nema migracije brojača narudžbi** — novi B2B kreće od nule
- **Invoice splitting vrši Apros** — mehanizam određivanja kada dolazi do razdvajanja faktury nije poznat; potencijalno proširenje AP-06 (vidi Sekciju 2)

### 1.9 Količine i rezervacija

- **Nema minimalnih ni maksimalnih količina**
- **Nema quantity stepova**
- Nativno WooCommerce upravljanje količinama
- Rezervacija zaliha: **nativno WC ponašanje — first completed order wins**; nema custom rezervacijskog mehanizma

### 1.10 Plaćanje

- **Jedina metoda plaćanja: transakcijski račun (virman)**
- Nema CMS-level logike za odgođeno plaćanje — odgoda se upravlja izvan WooCommerce-a

**Rollout faze:**

| Faza | Kategorija | Napomena |
|------|-----------|----------|
| 1 | Izipizi | Prva faza |
| 2 | Lifestyle & Outdoor | Druga faza |
| 3 | Igračke | ~99% trenutnih B2B narudžbi |

### 1.9 Finansijski pregled

- Korisnik vidi: pregled narudžbi + PDF stampa narudžbe (Jekaa model, dogovoreno s Marinom)
- Fakture, dugovanja, kreditni limit: **nisu eksplicitni zahtjev** — svako proširenje zahtijeva posebni estimate

---

## 2. QUESTIONS FOR APROS

*Samo pitanja koja zahtijevaju API pristup, endpoint validaciju ili tehničku potvrdu.*

---

### PRIORITET 1 — Arhitekturalni blokeri ⛔

Bez odgovora na ova pitanja nije moguće donijeti ni jednu implementacionu odluku.

---

**AP-01 — Egzaktna veza između wholesalePrice, Rabat 1 i finalne B2B cijene** ✅ PARTIALLY RESOLVED (payload pending)

**Pitanje:** Potvrdite egzaktnu vezu između `wholesalePrice`, Rabat 1 i finalne cijene za B2B kupca:
- Je li `wholesalePrice` ista vrijednost za sve partnere (bazna/katalog cijena), ili je per-partner (Apros preračunava finalnu cijenu po kupcu)?
- Primjenjuje li se Rabat 1 uvijek, samo za određene partnere, ili je opcionalni dodatni sloj?
- Dostavite realni payload primjer koji prikazuje obje vrijednosti za jednog partnera **s** Rabat 1 i jednog **bez**.

**Zašto je važno:** Ovo je najveća arhitekturalna nepoznanica. Postoje dva konfliktna implementacijska iskustva o semantici `wholesalePrice` — jedan izvor sugerira baznu cijenu, drugi finalnu per-partner cijenu. Svaki model zahtijeva potpuno drugačiju pricing arhitekturu u WooCommerceu. Payload primjer je jedini način razrješenja.

**Pogođena oblast:** Pricing engine, product sync, cart, checkout

**Stanje (ažurirano 2026-07-02):** RAZRIJEŠENO na arhitekturalnoj razini — Apros potvrdio: domaći kupci = Model A (`wholesalePrice − Rabat 1`), strani kupci = Model C (`countryPriceList`). BL-03 downgraded na MEDIUM. Realni payload primjer (egzaktni field nazivi, vrijednosti) i dalje nedostaje — vidi `docs/apros-session-final-pack.md` → "Still Required From Apros".

---

**AP-02 — Identifikator artikla pri sync-u** `[!]`

**Pitanje:** Po čemu Apros identifikuje artikle? Je li to SKU (šifra artikla), Apros interni numerički ID ili oboje? Je li identifikator jedinstven na razini varijante ili samo na razini parent-a?

**Zašto je važno:** Svaki product sync mehanizam (insert, update, deduplication, idempotency) mora biti baziran na jednom pouzdanom ključu. Neispravan pretpostavka = duplovi ili neuspješni update-i.

**Pogođena oblast:** Product sync, deduplication, SKU search

---

**AP-03 — Approval webhook — sadržaj body-a** 🔄 RECLASSIFIED (polling/import model, nema potvrđenog webhooka)

**Pitanje (originalno):** Kada Apros poziva WP approval endpoint, šalje li samo email/user_id — ili uključuje i `sif_kup`, dodijeljenu price listu i `advance_only` flag?

**Odgovor Apros-a (2026-07-02):** Nema approval webhooka. Tok je: web registracija → email notifikacija → ručno kreiranje partnera u Apros-u → `B2B KUPAC = DA` → partner se pojavljuje na partner list endpointu. WP mora periodičnim pollingom/importom preuzimati partner listu i detektirati nove/promijenjene `B2B KUPAC = DA` zapise.

**Zašto je važno:** Partner sync arhitektura se mijenja iz "inbound webhook receiver" u "cron-based polling/import job" — ovo je arhitekturalna promjena za Fazu 3 (vidi `docs/b2b-erp-migration-plan.md` Korak 9/10 i `docs/b2b-erp-adaptation-blueprint.md` Sekciju 4).

**Pogođena oblast:** Partner onboarding, pricing sync, payment restrictions

---

**AP-04 — Struktura varijanti u ArticleList payload-u** `[!]`

**Pitanje:** Kako Apros strukturira varijante u `articleList/get`? Parent i varijante kao odvojene stavke, ili varijante ugnježdene unutar parent-a? Koji atribut razlikuje varijantu od parent-a? Ima li svaka varijanta vlastiti SKU i vlastitu cijenu?

**Zašto je važno:** WooCommerce variable products imaju drugačiju DB strukturu. B2C endpoint već ima varijante — ali format mora biti potvrđen za B2B kontekst.

**Pogođena oblast:** Product sync, variant handling, quick order UX, product card

---

### PRIORITET 2 — Integracijski blokeri ⛔

---

**AP-05 — Product sync model** `[!]`

**Pitanje:** Pull ili push model? Full sync ili delta? Ako delta — kako Apros označava promijenjene stavke? Koji je max broj artikala po API odgovoru?

**Pogođena oblast:** Sync arhitektura, cron schedule, memory management

---

**AP-06 — Order sync API** ✅ PARTIALLY RESOLVED (payload pending)

**Pitanje:** URL, HTTP metoda i format payload-a koji Apros očekuje za primanje narudžbi. Vraća li Apros Apros order ID u responsu? Je li endpoint idempotent?

**Odgovor Apros-a (2026-07-02):** Obavezna polja potvrđena: `sif_kup`/`partnerId`, `partnerDeliveryLocationId`, stavke narudžbe s količinama. URL, HTTP metoda, success/error response format i idempotency i dalje nisu dostavljeni.

**Proširenje (invoice splitting):** Apros vrši splitting faktura — po kojem kriteriju? Šalje li se WC-u jedan ili više response-a? Mora li WC kreirati više narudžbi ili jedna narudžba može rezultirati više Apros faktura? — nije adresirano ovim odgovorom.

**Zašto je važno:** Order sync je highest-risk operacija. Bez idempotency garancije → retry logika je opasna (duplirane narudžbe). Invoice splitting može dodatno komplicirati order export arhitekturu.

**Pogođena oblast:** Checkout, order creation, retry logic, error handling, invoice splitting

---

**AP-07 — Data model dostavnih lokacija** ✅ PARTIALLY RESOLVED (payload pending)

**Pitanje:** Format, polja i trigger za listu lokacija po partneru. Može li partner imati više lokacija? Je li jedna označena kao default?

**Odgovor Apros-a (2026-07-02):** Partner može imati više dostavnih lokacija. **Nema default lokacije** — korisnik bira lokaciju pri naručivanju. Endpoint: `partnerDeliveryLocationList`. Puni payload primjer (adresna polja, stabilnost location ID-a između sync ciklusa) i dalje nedostaje.

**Pogođena oblast:** Checkout shipping selection, delivery address UX

---

**AP-08 — advance_only i free shipping flag** 🚫 OUT OF SCOPE

**Pitanje (originalno):** Stižu li `advance_only` i free shipping flag u approval webhook-u ili zasebnim sync kanalima? Mogu li se naknadno promijeniti za aktivnog partnera?

**Status (ažurirano 2026-07-02):** Reklasificirano kao izvan scope-a za inicijalnu implementaciju — approval webhook na kojem se ova pretpostavka temeljila ne postoji (vidi AP-03). `advance_only` ostaje dokumentiran u arhitekturi kao potencijalno buduće proširenje, ali se ne validira niti implementira u ovoj fazi.

**Pogođena oblast:** Payment method restrictions, checkout flow (odgođeno)

---

**AP-09 — Outbound autentikacija (WP → Apros)** ✅ RESOLVED

**Pitanje:** Auth metoda (API key, OAuth, Basic auth, JWT). Odvojeni credentials za različite operacije? Base URL i API verzija. IP whitelist?

**Odgovor Apros-a (2026-07-02):** **API Key** — isti pristup kao postojeća Jekaa B2C integracija. Odvojeni credentials po operaciji, base URL/API verzija i IP whitelist nisu eksplicitno adresirani ovim odgovorom, ali auth metoda kao arhitekturalni bloker je zatvorena.

**Pogođena oblast:** Security arhitektura, infrastrukturalni zahtjevi

---

### PRIORITET 3 — Operativni detalji s UX implikacijom

| Ref | Pitanje | UX implikacija |
|-----|---------|---------------|
| AP-10 | Kada Apros rezerviše stanje (korpa / checkout / ERP potvrda)? | Stock display i checkout messaging |
| AP-11 | Šalje li Apros EAN/barcode u ArticleList? | Search by barcode / SKU |
| AP-12 | Prima li Apros narudžbe za artikle van zalihe (backordering)? | Out of stock UX — blokada vs. preorder |
| AP-13 | Mehanizam ažuriranja rabata za aktivnog partnera (nova notifikacija ili periodični sync)? | Edge case u korpi — warning korisniku |
| AP-14 | Šalje li Apros status update natrag u WC (potvrđeno, isporučeno, stornirano)? | Order status UX u My Account |

---

## 3. QUESTIONS FOR DREAM POINT

*Pitanja za client workshop. Grupirana po prioritetu.*

---

### A. Kritične poslovne odluke (BLOKIRAJU UX arhitekturu)

---

**DP-A01 — Role sistem i model korisničkih računa** ⚠️ DJELOMIČNO — model korisničkih računa revidiran

**Status:** Model korisničkih računa nije razriješen — postoje dva potvrđena scenarija:

- **Scenarij A:** Jedan korisnik, više dostavnih adresa
- **Scenarij B:** Više korisnika (zaposlenika iste firme), više dostavnih adresa

Odabir scenarija direktno mijenja storage arhitekturu (user meta vs. Company CPT). Mora biti potvrđen **prije Faze 3**. Vidi DP-01 u sekciji blokirajućih stavki.

**Originalno pitanje o rolama (approval flow, permissions razlike):** Ostaje otvoreno — ako postoji approval flow ili hijerarhija korisnika unutar firme, to se preklapa s izborom Scenarija A ili B.

---

**DP-A02 — Shared order history unutar iste firme**

**Pitanje:** Svaki korisnik ima zaseban nalog (potvrđeno). Da li zaposleni iste firme trebaju vidjeti narudžbe svih korisnika iste firme? Ili je history strogo per-user?

**Zašto je važno:** "Moj račun" i order history UX su potpuno drugačiji ovisno o odgovoru.

**Impact ako ostane bez odgovora:** Implementira se per-user model. Shared order history zahtijeva naknadni rework.

---

**DP-A03 — Metode plaćanja i dostupnost po partnerima** ✅ DJELOMIČNO RAZRIJEŠENO

**Odgovor (potvrđeno):** Jedina metoda plaćanja u WooCommerce-u je **transakcijski račun (virman)**. Odgođeno plaćanje se upravlja izvan WooCommerce-a na poslovnoj razini — nema CMS-level logike.

**Ostaje otvoreno:** `advance_only` flag iz Apros-a — relevantnost za checkout flow u inicijalnoj implementaciji je smanjena, ali flag se zadržava u arhitekturi za buduće proširenje (AP-08 i dalje aktivan).

---

### B. Ordering workflow (VISOK PRIORITET)

---

**DP-B01 — Packaging i minimalne količine (MOQ)** ✅ RAZRIJEŠENO

**Odgovor (potvrđeno):** Nema minimalnih količina, nema maksimalnih količina, nema quantity stepova. Nativno WooCommerce upravljanje količinama na svim artiklima.

---

**DP-B02 — Split shipment — korisnikovo iskustvo**

**Pitanje:** Korisnik naručuje artikle iz različitih skladišta. Šta žele?
- Jedna narudžba koja se interno razdvaja (korisnik ne vidi podijelu)?
- Korisnik eksplicitno naručuje iz pojedinih skladišta zasebno?
- Prikazuje li se napomena "artikli iz različitih skladišta šalju se zasebno"?

**Zašto je važno:** Checkout flow i cart UX se fundamentalno razlikuju.

**Impact ako ostane bez odgovora:** Pretpostavlja se transparent split (korisnik ne vidi). Svaka promjena utiče na checkout arhitekturu.

---

**DP-B03 — Quick reorder i bulk ordering**

**Pitanje:** Je li Quick reorder eksplicitni zahtjev (u scope-u)?
- Ponoviti prethodnu narudžbu jednim klikom?
- CSV/Excel import narudžbi?
- Favorites / order liste?

**Zašto je važno:** Ove funkcionalnosti imaju zasebni development scope i UX flow.

**Impact ako ostane bez odgovora:** Implementiraju se kao sekundarne — naknadno dodavanje mijenja IA i navigation.

---

**DP-B04 — Out of stock — smije li se naručiti?**

**Pitanje:** Default je prikazati artikal bez stanja (bez narudžbe). Potvrdi ili korigiraj:
- Je li preorder ikad dozvoljen?
- Ako van zalihe: "Notify me" opcija ili samo informacija?
- Postoji li razlika po kategoriji?

**Impact ako ostane bez odgovora:** Implementira se Jekaa model (prikazati bez narudžbe). Preorder i notify zahtijevaju zasebni scope.

---

**DP-B05 — Promjena cijene u korpi**

**Pitanje:** Šta se dešava ako cijena artikla promijeni dok je u korpi?
- Tiho ažurirati cijenu?
- Prikazati warning korisniku (potvrdi novu cijenu)?
- Blokirati checkout dok korisnik ne potvrdi?

**Impact ako ostane bez odgovora:** Tim bira jednu od opcija interno — može biti u suprotnosti s poslovnim očekivanjima.

---

### C. Račun i permisije

---

**DP-C01 — Approval flow za narudžbe**

**Pitanje:** Postoji li interni approval flow (zaposleni kreira, manager odobrava)? Ako da: koji su statusi i šta se dešava s narudžbom dok čeka odobrenje?

**Impact ako ostane bez odgovora:** Nema approval flow-a. Naknadna implementacija mijenja checkout i account arhitekturu.

---

### D. Operacije i logistika

---

**DP-D01 — Mobilni kanal**

**Pitanje:** Koliko je mobile važan za vaše B2B korisnike? Koje akcije rade na mobileu (kompletno naručivanje, quick reorder, pregled stanja)?

**Zašto je važno:** Određuje mobile UX strategiju i responsive prioritete.

**Impact ako ostane bez odgovora:** Pretpostavlja se desktop-first. Ako je mobile važan kanal → zasebni mobile UX scope.

---

**DP-D02 — Deaktivacija partnera**

**Pitanje:** Što se dešava kada partner prestane biti B2B kupac?
- Aktivne narudžbe u toku?
- Order history — ostaje vidljiv korisniku?
- Tko pokreće deaktivaciju (Apros automatski ili Dream Point admin ručno)?

---

### D2. Nova pitanja — identificirana na workshopu Lipanj 2026

---

**DP-D3 — Bundle proizvodi** → PREBAČEN IZ ERP SCOPE-a

> **Reklasificirano:** Bundle product logika nije dio ERP integracije.
> Vlasnik: `uncledev-product-bundles`
> Vidi: `docs/bundle-products-transfer-note.md`

---

**DP-D4 — Invoice splitting mehanizam**

**Pitanje:** Apros dijeli fakture. Po kojem kriteriju Apros određuje kada dolazi do razdvajanja? Šalje li Apros jedan ili više odgovora natrag u WooCommerce? Treba li WC kreirati jednu ili više narudžbi u slučaju split-a?

**Zašto je važno:** Ako WC treba znati o split-u → AP-06 je potrebno proširiti. Ako je split isključivo interan u Apros-u i WC dobiva jedan response → nema implikacija na WC arhitekturu.

---

### E. Sekundarne UX odluke

---

**DP-E01 — Admin upravljanje homepageom i sadržajem**

**Pitanje:** Ko i kako upravlja bannerima i sadržajem homepagea? "Novi proizvod" i "Posebna ponuda" — automatski iz ERP-a ili admin može override-ati? Ko ima CMS admin pristup?

---

**DP-E02 — Promotivne akcije — tipovi i upravljanje**

**Pitanje:** Koje tipove promocija sistem treba podržavati (flash sale, brand-level akcija, ERP-driven posebna ponuda)? Ko kreira i odobrava akcije?

---

**DP-E03 — Relacije među proizvodima**

**Pitanje:** Treba li sistem prikazivati related products, upsell, cross-sell ili bundle proizvode? Ako da: ko ih definira (ručni CMS ili automatski)?

---

## 4. INTERNAL TEAM DECISIONS

*Odluke koje ne zahtijevaju input od klijenta ili Apros-a. Tim ih može riješiti samostalno.*

---

| ID | Odluka | Vlasnik | Preporučeni pravac | Rok |
|----|--------|---------|-------------------|-----|
| INT-01 | **Search strategija** — SKU-first ili naziv-first? Autocomplete? Recent searches? Typo tolerance? | Dev + UX | SKU-first za B2B kontekst; Relevanssi za naziv search. Autocomplete s prikazom SKU + naziva | Prije low-fi faze |
| INT-02 | **Filter layout i ponašanje** — sidebar vs. sticky header? URL state? Prioritet filtera za mix kategorija (igračke + naočale + lifestyle)? | UX | Sidebar s clear-all; filter po brandu, kategoriji, dostupnosti, skladištu; URL state za dijeljive pretrage | Prije low-fi faze |
| INT-03 | **Multi-warehouse korpa** — može li jedna korpa sadržavati artikle iz više skladišta? | Dev + Marko | Da, ali uz jasnu UI indikaciju porijekla; split se dešava interno; ovisi o DP-B02 odgovoru | Nakon DP-B02 |
| INT-04 | **Basket price change UX** — tiho update, warning ili blokada? | UX + Marko | Warning s confirm opcijom — balans između UX friction i business transparency | Nakon DP-B05 |
| INT-05 | **Quick order UX pattern** — quantity-first tabela ili search-first pristup? | UX (Marko ima plan) | Quantity-first tabela s inline search kao sekundarnom navigacijom | Prije low-fi faze |
| INT-06 | **Desktop-first pretpostavka** — potvrda ili revizija | Marko + team | Desktop-first potvrđena kao pretpostavka; mobile-friendly sekundarno; validirati s klijentom (DP-D01) | Prije IA faze |
| INT-07 | **Pokazni artikli za UX validaciju** — 5–10 artikala različite kompleksnosti | Marko + Dream Point | Marko predlaže set, klijent potvrđuje; mora pokriti: simple, variant, multi-warehouse, promotional, pricing-edge | Prije low-fi faze |

---

## 5. CURRENT PROJECT STATUS

---

### Discovery status

| Oblast | Status | Povjerenje |
|--------|--------|-----------|
| Poslovne domene i pravila | ✅ Kompletno | Visoko |
| Pricing mehanizam (arhitektura) | ✅ Potvrđeno od Apros-a (2026-07-02) — Model A domaći / Model C strani | Visoko (arhitektura); Nisko (payload) |
| ERP payload formati | ❌ Nevalidiran | Nisko |
| API endpoint specifikacije | ⚠️ Djelomično — obavezna polja poznata, puni format nepoznat | Srednje |
| Auth mehanizam WP → Apros | ✅ Potvrđeno — API Key (2026-07-02) | Visoko |
| Partner approval flow | ✅ Reklasificirano — polling/import, nema webhooka (2026-07-02) | Visoko |
| Dostavne lokacije | ⚠️ Djelomično — više lokacija, nema defaulta, endpoint poznat | Srednje |
| UX zahtjevi | ⚠️ Djelomično definirani | Srednje |

---

### ERP status

**Faza:** Discovery / Validation — implementacija NIJE počela i ne smije početi. Napomena: Apros je dao prvi direktan tehnički odgovor (2026-07-02) koji je razriješio arhitekturalnu razinu za pricing i auth te reklasificirao approval flow; implementacija Faze 4/5 i dalje čeka payload primjere.

**Aktivni blokeri:**

| ID | Bloker | Rizik |
|----|--------|-------|
| BL-01 | Apros API pristup (sandbox) ne postoji | ⛔ KRITIČAN — bez ovoga nijedna payload validacija nije moguća |
| BL-04 | Order endpoint — puni format payload-a/response-a nepoznat | 🟡 SREDNJI — obavezna polja poznata (`sif_kup`/`partnerId`, `partnerDeliveryLocationId`, stavke+količine), payload primjer nedostaje |

**Downgradirani/razriješeni blokeri (2026-07-02):**

| ID | Bloker | Originalni rizik | Novi rizik | Razlog |
|----|--------|-----------------|-----------|--------|
| BL-02 | Struktura varijanti u payload-u | VISOK | SREDNJI | B2C endpoint već ima varijante — isti endpoint se proširuje |
| BL-03 | Pricing model (`wholesalePrice` semantika) | VISOK | **SREDNJI** | Apros potvrdio Model A (domaći) + Model C (strani); payload primjer pending |
| BL-05 | Auth mehanizam WP → Apros | VISOK | **RESOLVED** | API Key, isti pristup kao Jekaa B2C |

---

### UX status

- **Faza:** Pre-foundation
- **Blokeri za UX Foundation:** DP-A01 (model korisničkih računa — Scenarij A ili B) mora biti potvrđen; DP-B01 je razriješen (nema MOQ)
- **IA design:** Nije počelo — čeka workshop + interne odluke INT-01 do INT-06
- **Low-fi wireframes:** Blokirani pending IA-om
- **Referentni sistem:** Izipizi Pro B2B (analiziran)

**Razriješeni blockers od workshopa Lipanj 2026:**
- DP-B01 (MOQ): ✅ zatvoreno — native WC
- DP-A03 (payment methods): ✅ djelomično zatvoreno — virman; `advance_only` zadržan
- AP-10 (stock reservation): ✅ zatvoreno — native WC first-order-wins
- AP-12 (backorder): ✅ zatvoreno — nema naručivanja bez zaliha (default)

---

### Workshop readiness

| Workshop | Status |
|---------|--------|
| Dream Point klijent workshop | ✅ Spreman — pitanja definirana (13 pitanja, grupisana) |
| Interne timske odluke | ✅ Spreman — 7 odluka s preporučenim pravcima |
| Apros tehnička sesija | ❌ Blokirana — BL-01 (nema API pristupa) je preduvjet |

---

### Povjerenje po oblasti

| Oblast | Povjerenje | Napomena |
|--------|-----------|---------|
| Poslovne domene | 🟢 Visoko | Potvrđeno iz više izvora |
| Pricing arhitektura | 🟢 Visoko | Apros potvrdio Model A (domaći) + Model C (strani), 2026-07-02 |
| Pricing payload (field nazivi, vrijednosti) | 🔴 Nisko | Realni payload primjer i dalje nedostaje |
| Varijante i sync | 🟡 Srednje | B2C maturity daje signal, format nevalidiran |
| Order endpoint | 🟡 Srednje | Obavezna polja poznata; puni payload/response format nepoznat |
| Auth mehanizam | 🟢 Visoko | API Key potvrđen, 2026-07-02 |
| Partner approval flow | 🟢 Visoko | Reklasificirano na polling/import, nema webhooka |
| Dostavne lokacije | 🟡 Srednje | Više lokacija/nema defaulta potvrđeno; payload primjer nedostaje |
| UX zahtjevi | 🟡 Srednje | Ključna B2B pitanja otvorena |

---

### Najveći rizici projekta

1. **Pricing payload finalizacija** — arhitektura je razriješena (Model A domaći / Model C strani), ali bez realnog payload primjera (field nazivi, vrijednosti) implementacija pricing engine-a ne može biti finalizirana
2. **Order endpoint format** — obavezna polja poznata, ali URL, HTTP metoda i response format nedostaju; može zahtijevati specifičnu transformaciju koja komplicira checkout flow
3. **Partner sync arhitektura (polling/import)** — zamjena webhook dizajna cron-based pollingom je arhitekturalna promjena za Fazu 3; mora biti odražena u migration planu prije implementacije
4. **Role sistem** — ako klijent zahtijeva role/approval flow nakon početka UX faze → rework IA-e
5. **Split shipment logika** — nije definirano ko (WooCommerce ili Apros) razdvaja narudžbe; obje strane moraju biti usklađene
6. **Scope creep** — B2B sistemi historijski rastu; quick reorder, CSV import, approval flow su potencijalni dodaci koji moraju biti zaključani u scope-u rano

---

## 6. NEXT STEPS

*Preporučeni redoslijed aktivnosti. Ne počinjati sljedeću fazu bez završetka prethodne.*

---

**KORAK 1 — Dream Point Client Workshop**
*Blokira: UX IA, checkout UX, account strukturu*

Prioritetna pitanja: DP-A01, DP-A02, DP-A03, DP-B01, DP-B02, DP-B03
Izlaz: Dogovoren role model, MOQ pravila, payment metode, split shipment odluka

---

**KORAK 2 — Interne timske odluke**
*Blokira: search, filter, quick order dizajn*

Prioritetne odluke: INT-01 (search), INT-02 (filteri), INT-05 (quick order UX), INT-06 (desktop-first)
Izlaz: Dogovorena IA osnova, preporučeni UX pravci, definisani pokazni artikli

---

**KORAK 3 — Apros API pristup**
*Preduvjet za: ERP Validation Wave*

Aktivnost: Dogovoriti sandbox ili production API pristup s Apros timom
Bez ovoga: nijedna od AP-01 do AP-09 validacija nije moguća

---

**KORAK 4 — ERP Validation Wave**
*Blokira: Phase 4 implementaciju*

Prioritetna pitanja za prvu sesiju: AP-01, AP-02, AP-03, AP-04 (arhitekturalni blokeri)
Drugu sesiju: AP-05, AP-06, AP-07, AP-08, AP-09 (integracijski blokeri)
Referencija: `docs/erp-validation-checklist.md` — kompletna lista s uticajnom analizom

---

**KORAK 5 — UX Foundation**
*Ulaz: Koraci 1–4 kompletni (ili barem Koraci 1–2)*

Obuhvata: Information architecture, user flowovi, prioritetni flowovi P1
Izlaz: Validirana IA osnova, approvana od klijenta

---

**KORAK 6 — Low-fi Wireframes**
*Ulaz: Approvana IA*

Obuhvata: Detaljni low-fi wireframeovi za sve P1 flowove
Napomena: Nakon odobrenja low-fi faze — velike strukturne izmjene nisu predviđene

---

**KORAK 7 — High-Fidelity Wireframes i UI Design**
*Ulaz: Odobreni low-fi*

Obuhvata: Layout refinement, vizuelni pravac, UI komponente, developer handoff

---

## APPENDIX — Dokumenti i reference

| Dokument | Sadržaj |
|---------|---------|
| `docs/erp-discovery-findings.md` | Potvrđene činjenice, nevalidirane pretpostavke, implementacijski blokeri, revision historija |
| `docs/erp-validation-checklist.md` | Kompletna lista pitanja za Apros sesiju, Priority 1–4, s impact analizom |
| `docs/client-workshop-questions.md` | Detaljni workshop pitanja s klasifikacijom i isključenim (već odgovorenim) stavkama |
| `docs/apros-question-resolution-matrix.md` | Autoritativni status AP-01 – AP-14 (ažurirano 2026-07-02) |
| `docs/apros-session-final-pack.md` | "Still Required From Apros" — preostali payload primjeri potrebni za finalizaciju implementacije |
| `docs/decisions.md` | ADR-ovi — Pricing Architecture (ADR-001), Partner Approval Architecture (ADR-002) |
| `docs/b2b-erp-adaptation-blueprint.md` | Arhitekturalni blueprint — Sekcija 9 renumerisana na kanonsku AP numeraciju (2026-07-02) |
| `memory/project_erp_discovery.md` | ERP discovery stanje — kompaktan pregled za brzi onboarding |
