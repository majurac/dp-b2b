# Dreampoint B2B — Discovery Status & Decision Matrix

**Verzija:** 1.0 | **Datum:** 2026-06-03
**Namjena:** Project management, workshop priprema, onboarding novih članova tima
**Kompanija:** Dream Point | **Integrator:** ZGData (Leopold Benkek) | **ERP:** Apros (Zagreb Date)

> **Kako čitati ovaj dokument:**
> Sekcija 1 = što znamo. Sekcija 2–3 = što moramo pitati i od koga. Sekcija 4 = što tim sam odlučuje. Sekcija 5 = gdje smo sada. Sekcija 6 = šta je sljedeće.

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
  - Lista primatelja (dostavne lokacije po partneru) + ugovorni uvjeti + cjenici po državama
- B2C endpoint za artikle **već sadrži** varijacije, atribute i slike — B2B proširuje isti endpoint

### 1.2 Katalog proizvoda

- ~10.000 artikala na novom B2B sistemu (ugovorno definisano)
- Svaki artikal ima atribut **`b2bArticle`** (true/false) koji određuje B2B eligibilnost
- **`wholesalePrice`** je Apros proširenje dodano na `articleList/get`
- Kataloški broj artikla prikazuje se na stranici artikla (PDP)
- Atributi: **NOVI PROIZVOD**, **POSEBNA PONUDA** — dolaze iz ERP-a
- Atributi prethodnog sistema (Arminov) zadržavaju se na novom B2B-u
- Promotivna funkcionalnost: artikli ili grupe artikala mogu biti dodani u posebne ponude/akcije

### 1.3 Cijene i rabati

| Kontekst | Šta se prikazuje |
|----------|-----------------|
| Listing | Neto cijena **s uključenim rabatom**, bez PDV-a |
| Košarica / checkout | Puni iznos **s PDV-om** |
| Strani kupci — listing | Finalna neto cijena iz cjenika po državama (ne % rabat) |

- **Apros šalje već izračunate cijene** — jača evidencija za Model B u pricing arhitekturi
- **Iznos popusta ne prikazuje se na ribbon-ima** — vidljiv samo na fakturama
- **Rabat 1** dolazi iz **ugovornih uvjeta** endpoint-a: struktura = `sif_kup` + brand + postotak rabata
- Rabat se dodjeljuje po brandu i primjenjuje na sve artikle tog branda
- **Promocijska cijena ima prioritet nad Rabat 1** — ako artikal ima aktivnu promocijsku cijenu, ona se koristi, ne rabat
- Promotivne ponude dolaze iz Apros-a; na platformi će postojati zasebna promotivna stranica
- Porezna kategorija kupca čita se iz "pravnog oblika" koji Apros šalje po kupcu
- Promjene rabata (postotak, odgoda plaćanja) idu kroz Apros i iznimno su rijetke

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

Potvrđen flow:
1. Novi partner popunjava web registracionu formu (native WooCommerce)
2. Email notifikacija stiže na "Točku sna" (interni kontakt Dream Point-a)
3. Partner se ručno otvara u Apros-u
4. Apros dodjeljuje atribut odobrenja za B2B Webshop
5. Apros šalje signal CMS-u — korisnik dobija pristup

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

**AP-01 — Egzaktna veza između wholesalePrice, Rabat 1 i finalne B2B cijene** `[!]`

**Pitanje:** Potvrdite egzaktnu vezu između `wholesalePrice`, Rabat 1 i finalne cijene za B2B kupca:
- Je li `wholesalePrice` ista vrijednost za sve partnere (bazna/katalog cijena), ili je per-partner (Apros preračunava finalnu cijenu po kupcu)?
- Primjenjuje li se Rabat 1 uvijek, samo za određene partnere, ili je opcionalni dodatni sloj?
- Dostavite realni payload primjer koji prikazuje obje vrijednosti za jednog partnera **s** Rabat 1 i jednog **bez**.

**Zašto je važno:** Ovo je najveća arhitekturalna nepoznanica. Postoje dva konfliktna implementacijska iskustva o semantici `wholesalePrice` — jedan izvor sugerira baznu cijenu, drugi finalnu per-partner cijenu. Svaki model zahtijeva potpuno drugačiju pricing arhitekturu u WooCommerceu. Payload primjer je jedini način razrješenja.

**Pogođena oblast:** Pricing engine, product sync, cart, checkout

**Stanje:** Konfliktni iskustveni signali — povjerenje u pricing model smanjeno. BL-03 ostaje HIGH prioritet bloker.

---

**AP-02 — Identifikator artikla pri sync-u** `[!]`

**Pitanje:** Po čemu Apros identifikuje artikle? Je li to SKU (šifra artikla), Apros interni numerički ID ili oboje? Je li identifikator jedinstven na razini varijante ili samo na razini parent-a?

**Zašto je važno:** Svaki product sync mehanizam (insert, update, deduplication, idempotency) mora biti baziran na jednom pouzdanom ključu. Neispravan pretpostavka = duplovi ili neuspješni update-i.

**Pogođena oblast:** Product sync, deduplication, SKU search

---

**AP-03 — Approval webhook — sadržaj body-a** `[!]`

**Pitanje:** Kada Apros poziva WP approval endpoint, šalje li samo email/user_id — ili uključuje i `sif_kup`, dodijeljenu price listu i `advance_only` flag?

**Zašto je važno:** Ako `sif_kup` i pricing podaci stižu u approval webhook-u → implementacija je kompletna u jednom koraku. Ako ne → potreban je odvojeni partner sync mehanizam.

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

**AP-06 — Order sync API** `[!]`

**Pitanje:** URL, HTTP metoda i format payload-a koji Apros očekuje za primanje narudžbi. Vraća li Apros Apros order ID u responsu? Je li endpoint idempotent?

**Proširenje (invoice splitting):** Apros vrši splitting faktura — po kojem kriteriju? Šalje li se WC-u jedan ili više response-a? Mora li WC kreirati više narudžbi ili jedna narudžba može rezultirati više Apros faktura?

**Zašto je važno:** Order sync je highest-risk operacija. Bez idempotency garancije → retry logika je opasna (duplirane narudžbe). Invoice splitting može dodatno komplicirati order export arhitekturu.

**Pogođena oblast:** Checkout, order creation, retry logic, error handling, invoice splitting

---

**AP-07 — Data model dostavnih lokacija** `[!]`

**Pitanje:** Format, polja i trigger za listu lokacija po partneru. Može li partner imati više lokacija? Je li jedna označena kao default?

**Pogođena oblast:** Checkout shipping selection, delivery address UX

---

**AP-08 — advance_only i free shipping flag** `[!]`

**Pitanje:** Stižu li `advance_only` i free shipping flag u approval webhook-u ili zasebnim sync kanalima? Mogu li se naknadno promijeniti za aktivnog partnera?

**Pogođena oblast:** Payment method restrictions, checkout flow

---

**AP-09 — Outbound autentikacija (WP → Apros)** `[!]`

**Pitanje:** Auth metoda (API key, OAuth, Basic auth, JWT). Odvojeni credentials za različite operacije? Base URL i API verzija. IP whitelist?

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
| Pricing mehanizam (konceptualni) | ⚠️ Podržano dokazima, nije potvrđeno | Srednje |
| ERP payload formati | ❌ Nevalidiran | Nisko |
| API endpoint specifikacije | ❌ Nevalidirane | Nisko |
| Auth mehanizam WP → Apros | ❌ Nevalidiran | Nisko |
| UX zahtjevi | ⚠️ Djelomično definirani | Srednje |

---

### ERP status

**Faza:** Discovery / Validation — implementacija NIJE počela i ne smije početi.

**Aktivni blokeri (sve četiri moraju biti riješene):**

| ID | Bloker | Rizik |
|----|--------|-------|
| BL-01 | Apros API pristup (sandbox) ne postoji | ⛔ KRITIČAN — bez ovoga nijedna validacija nije moguća |
| BL-03 | Pricing model nevalidiran (`wholesalePrice` semantika) | 🔴 VISOK — najveća arhitekturalna nepoznanica |
| BL-04 | Order endpoint URL i format payload-a nepoznati | 🔴 VISOK — implementacija order sync-a nije moguća |
| BL-05 | Auth mehanizam WP → Apros nije definiran | 🔴 VISOK — security arhitektura nije moguća |

**Downgradirani blokeri:**

| ID | Bloker | Originalni rizik | Novi rizik | Razlog |
|----|--------|-----------------|-----------|--------|
| BL-02 | Struktura varijanti u payload-u | VISOK | SREDNJI | B2C endpoint već ima varijante — isti endpoint se proširuje |

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
| Pricing konceptualno | 🔴 Nisko | Konfliktni iskustveni signali — `wholesalePrice` semantika nerazriješena; vidi BL-03 |
| Varijante i sync | 🟡 Srednje | B2C maturity daje signal, format nevalidiran |
| Order endpoint | 🔴 Nisko | Potpuno nepoznat |
| Auth mehanizam | 🔴 Nisko | Potpuno nepoznat |
| UX zahtjevi | 🟡 Srednje | Ključna B2B pitanja otvorena |

---

### Najveći rizici projekta

1. **Pricing arhitektura** — semantika `wholesalePrice` nerazriješena: dva nezavisna iskustvena izvora daju konfliktne interpretacije (bazna cijena vs. finalna per-partner cijena); svaki model zahtijeva potpuno drugačiju arhitekturu; jedino razrješenje je realni Apros payload primjer
2. **Order endpoint format** — kompletno nepoznat; može zahtijevati specifičnu transformaciju koja komplicira checkout flow
3. **Role sistem** — ako klijent zahtijeva role/approval flow nakon početka UX faze → rework IA-e
4. **Split shipment logika** — nije definirano ko (WooCommerce ili Apros) razdvaja narudžbe; obje strane moraju biti usklađene
5. **Scope creep** — B2B sistemi historijski rastu; quick reorder, CSV import, approval flow su potencijalni dodaci koji moraju biti zaključani u scope-u rano

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
| `memory/project_erp_discovery.md` | ERP discovery stanje — kompaktan pregled za brzi onboarding |
