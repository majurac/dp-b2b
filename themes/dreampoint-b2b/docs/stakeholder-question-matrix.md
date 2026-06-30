# Dreampoint B2B — Stakeholder Question Matrix

**Datum:** 2026-06-03 | **Namjena:** Workshop i meeting dokument — čita se samostalno, bez potrebe za drugim dokumentima.

---

## 1. WHAT IS CONFIRMED

### Katalog i artikli
- ~10.000 artikala na B2B platformi
- `b2bArticle` atribut (true/false) određuje B2B eligibilnost
- `wholesalePrice` proširenje dolazi iz Apros-a na postojećem `articleList/get` endpointu
- Isti endpoint koji B2C koristi — proširen, ne novi
- B2C endpoint već sadrži varijacije, atribute i slike
- Atributi: NOVI PROIZVOD, POSEBNA PONUDA (ERP-driven)
- Kataloški broj prikazuje se na stranici artikla

### Cijene i rabati
- Listing: neto cijena s uključenim rabatom, bez PDV-a
- Košarica / checkout: puni iznos s PDV-om
- Domaći partneri: Rabat 1 po brandu — postotak rabata dostupan iz `ugovorni uvjeti` endpointa (sif_kup + brand + %)
- Strani partneri: finalna neto cijena iz `cjenici po državama` endpointa — ne % rabat
- Porezna kategorija: iz "pravnog oblika" koji Apros šalje po kupcu

> ⚠️ **Nerazriješeno (AP-01):** Egzaktna veza između `wholesalePrice` i Rabat 1 nije potvrđena. Dva nezavisna iskustvena izvora daju konfliktne interpretacije. Vidi Sekciju 2 — AP-01.

### Skladišta
- 4 skladišta u sistemu: Glavno (1), Igračke (3), Naočale (4), Lifestyle (5)
- Stanje prikazano po skladištu, ne agregirano
- Automatsko mapiranje branda na matično skladište — stanje se vuče samo s matičnog
- Default za out of stock: prikaži artikal bez mogućnosti narudžbe

### Partneri i pristup
- `sif_kup` = jedinstveni ID poslovnog partnera u Apros-u
- Dream Point određuje kome se otvara B2B pristup — ne Apros
- Svaki korisnik registruje zaseban nalog čak i ako pripada istoj firmi

### Onboarding flow (potvrđen)
1. Partner popunjava web registracionu formu
2. Email notifikacija na "Točku sna"
3. Admin ručno otvara partnera u Apros-u
4. Apros dodjeljuje atribut odobrenja i šalje signal CMS-u

### Vidljivost
- Vidljivost artikala po kupcima je u vlasništvu **B2B CMS-a** — Apros je ne kontrolira i ne šalje
- Visibility sistem je implementiran i testiran

### Narudžbe i rollout
- Narudžbe: jednosmjerne (WooCommerce → Apros)
- Routing: igračke → prodajno mjesto 3, lifestyle → prodajno mjesto 5
- Nema paralelnog rada starog i novog B2B-a
- Rollout: 1. Izipizi → 2. Lifestyle & Outdoor → 3. Igračke (~99% trenutnih narudžbi)

### Finansije
- Korisnik vidi: pregled narudžbi + PDF stampa (Jekaa model, dogovoreno s Marinom)
- Fakture, dugovanja, kreditni limit: **nisu eksplicitni zahtjev**

---

## 2. QUESTIONS FOR APROS

*Zahtijevaju API pristup ili tehničku potvrdu od Apros tima. Ne mogu biti riješena interno.*

---

### Prioritet 1 — Arhitekturalni blokeri (odluke o arhitekturi nisu moguće bez odgovora)

| # | Pitanje | Zašto je kritično |
|---|---------|------------------|
| AP-01 | Potvrdite egzaktnu vezu između `wholesalePrice`, Rabat 1 i finalne B2B cijene za kupca. Je li `wholesalePrice` ista za sve partnere (bazna/katalog cijena) ili per-partner (Apros preračunava po kupcu)? Primjenjuje li se Rabat 1 uvijek ili samo za određene partnere? Dostavite realni payload primjer za partnera s Rabat 1 i za partnera bez. | Najveća arhitekturalna nepoznanica. Postoje dva konfliktna implementacijska iskustva o semantici `wholesalePrice`. Svaki model zahtijeva potpuno drugačiju pricing arhitekturu. Payload primjer je jedini način razrješenja. |
| AP-02 | Po čemu Apros identifikuje artikle pri sync-u — SKU, interni ID ili oboje? Je li identifikator jedinstven na razini **varijante** ili samo parent-a? | Osnova svakog sync mehanizma — bez ovoga insert/update/deduplication nije moguće implementirati. |
| AP-03 | Kada Apros poziva WP approval endpoint, šalje li samo `email`/`user_id` — ili uključuje i `sif_kup`, price listu i `advance_only` flag? | Ako `sif_kup` ne stiže u approval webhook-u, potreban je odvojeni partner sync. Direktno utiče na broj moving parts-a. |
| AP-04 | Kako su varijante strukturirane u `articleList/get` payload-u? Zasebne stavke ili ugniježđene unutar parent-a? Ima li svaka varijanta vlastiti SKU i cijenu? | WooCommerce variable products zahtijevaju točno poznavanje strukture prije pisanja sync adaptera. |

---

### Prioritet 2 — Integracijski blokeri (implementacija faze 4 nije moguća bez odgovora)

| # | Pitanje | Zašto je kritično |
|---|---------|------------------|
| AP-05 | Pull ili push model za product sync? Full sync ili delta? Ako delta — kako Apros označava promijenjene stavke? | Arhitektura sinca je potpuno drugačija za svaki model. |
| AP-06 | URL, metoda i format payload-a za order endpoint. Vraća li Apros order ID u responsu? Je li endpoint **idempotent**? | Order sync je highest-risk operacija. Bez idempotency garancije retry logika kreira duplirane narudžbe. |
| AP-07 | Format i polja liste dostavnih lokacija po partneru. Može li partner imati više lokacija? Koja je default? Na koji trigger se ažurira? | Direktno određuje storage model i checkout UI za odabir adrese dostave. |
| AP-08 | Stižu li `advance_only` i free shipping flag u approval webhook-u ili zasebno? Mogu li se naknadno promijeniti za aktivnog partnera? | Checkout payment restrictions ovise o tome je li ovo one-time ili živući flag. |
| AP-09 | Auth metoda za WP → Apros pozive (API key, OAuth, Basic auth). Postoji li IP whitelist? | OAuth zahtijeva znatno više implementacijskog rada. IP whitelist može zahtijevati infrastrukturalne izmjene. |

---

### Prioritet 3 — UX-kritični operativni detalji

| # | Pitanje | UX implikacija |
|---|---------|---------------|
| AP-10 | Kada Apros rezerviše stanje — pri dodavanju u korpu, checkoutu ili tek ERP potvrdom? | Stock messaging i checkout flow ponašanje |
| AP-11 | Šalje li Apros EAN/barcode u `articleList/get`? | Omogućava search by barcode — proširuje B2B search mogućnosti |
| AP-12 | Dozvoljava li Apros narudžbe za artikle van zalihe (backordering)? | Out of stock UX — blokada narudžbe vs. preorder flow |
| AP-13 | Koji mehanizam ažurira rabat za **aktivnog** partnera (novi webhook ili periodični sync)? | Edge case — cijena u aktivnoj korpi može biti zastarjela |
| AP-14 | Šalje li Apros status update natrag u WC (potvrđeno, isporučeno, stornirano)? | Order status u My Account — jednosmjeran ili dvosmjeran prikaz |

---

## 3. QUESTIONS FOR DREAM POINT

*Poslovne odluke koje zahtijevaju odgovor klijenta. Grupisane po prioritetu.*

---

### Blok A — Kritične poslovne odluke (direktno blokiraju UX arhitekturu)

---

**A-01 — Role sistem**
Postoje li različite role korisnika, ili su svi B2B korisnici funkcionalno jednaki?

Ako postoje role: koje su konkretne razlike u mogućnostima (kreiranje narudžbi, pregled cijena, pregled faktura, upravljanje korisnicima)?

**Poslovni impact:** Direktno određuje navigaciju, permissions sistem i account strukturu. Naknadno uvođenje rola = rework cjelokupne IA-e i checkoutа.

---

**A-02 — Shared order history unutar iste firme**
Svaki korisnik ima zaseban nalog. Trebaju li zaposlenici iste firme **vidjeti narudžbe ostalih** korisnika iste firme?

**Poslovni impact:** Order history i "Moj račun" UX su fundamentalno drugačiji za per-user vs. per-company model. Naknadno mijenjanje znači rework account arhitekture.

---

**A-03 — Metode plaćanja**
Koje metode plaćanja su planirane i za koje partnere?
- Virman (svi?), odgođeno plaćanje (kredit partneri?), avansno plaćanje?

**Poslovni impact:** Checkout flow ne može biti dizajniran bez ovoga.

---

### Blok B — Ordering workflow (visok prioritet)

---

**B-01 — Minimalne količine i packaging (MOQ)**
Naručuje li se artikal uvijek po komadu — ili po kutiji, kartonu, display jedinici?
Postoje li minimalne količine ili quantity stepovi (npr. višekratnik od 6)? Variraju li pravila po kategoriji?

**Poslovni impact:** Quantity input UX, quick order tabela i cart validacije su potpuno drugačiji sa MOQ logikom. Retroaktivno uvođenje = rework ordering flow-a.

---

**B-02 — Split shipment — što korisnik vidi**
Korisnik naručuje artikle iz različitih skladišta. Što želite?
- Jedna narudžba koja se interno razdvaja (korisnik ne vidi podjelu)?
- Korisnik eksplicitno naručuje iz pojedinih skladišta zasebno?

**Poslovni impact:** Checkout flow i cart arhitektura se fundamentalno razlikuju.

---

**B-03 — Quick reorder i bulk ordering**
Je li Quick reorder eksplicitni zahtjev (u scope-u)?
- Ponoviti prethodnu narudžbu jednim klikom?
- CSV/Excel import narudžbi?
- Favorites / saved order liste?

**Poslovni impact:** Svaka od ovih funkcionalnosti ima zasebni development scope i UX flow. Moraju biti zaključane u scope-u rano.

---

**B-04 — Out of stock — smije li se naručiti?**
Default: artikal se prikazuje bez stanja, bez mogućnosti narudžbe (Jekaa model).
- Je li preorder ikad dozvoljen?
- "Notify me" opcija — zahtjev ili ne?
- Razlikuje li se ponašanje po kategoriji?

**Poslovni impact:** Preorder i notify su zasebni UX flow-ovi koji zahtijevaju dodatni scope.

---

**B-05 — Approval flow za narudžbe**
Postoji li interni approval flow unutar firme (zaposleni kreira → manager odobrava)?
Ako da: koji su statusi i šta se dešava s narudžbom dok čeka odobrenje?

**Poslovni impact:** Bez definirane approval logike — implementira se direktni checkout. Naknadno uvođenje mijenja checkout, account i notification arhitekturu.

---

**B-06 — Promjena cijene u aktivnoj korpi**
Šta se dešava ako se cijena promijeni dok je artikal u korpi?
- Tiho ažurirati cijenu?
- Prikazati warning i tražiti potvrdu korisnika?
- Blokirati checkout?

**Poslovni impact:** Direktno utiče na korisničko povjerenje i checkout UX.

---

### Blok C — Operacije i logistika

---

**C-01 — Mobile kanal**
Koliko je mobile važan za vaše B2B korisnike?
- Koje akcije rade na mobileu (kompletno naručivanje, quick reorder, pregled stanja)?

**Poslovni impact:** Određuje mobile UX strategiju i responsive prioritete. Ako je mobile punopravni kanal — zasebni mobile UX scope.

---

**C-02 — Deaktivacija partnera**
Šta se dešava kada partner prestane biti B2B kupac?
- Šta je s aktivnim narudžbama u toku?
- Ostaje li order history vidljiv korisniku?
- Tko pokreće deaktivaciju (Apros automatski ili Dream Point admin ručno)?

---

### Blok D — Sekundarne UX odluke

---

**D-01 — Admin upravljanje homepageom i sadržajem**
Ko i kako upravlja bannerima i sadržajem na homepageu?
NOVI PROIZVOD i POSEBNA PONUDA — automatski iz ERP-a ili admin može override-ati?

---

**D-02 — Promotivne akcije**
Koje tipove promocija sistem treba podržavati (flash sale, brand-level akcija, ERP-driven posebna ponuda)?
Ko kreira i odobrava akcije?

---

**D-03 — Relacije među proizvodima**
Treba li sistem prikazivati related products, upsell, cross-sell ili bundle proizvode?
Ako da: ko ih definira?

---

## 4. INTERNAL TEAM DECISIONS

*Odluke koje ne zahtijevaju input klijenta. Vlasnik ih rješava samostalno.*

| # | Odluka | Preporučeni pravac | Vlasnik | Rok |
|---|--------|-------------------|---------|-----|
| INT-01 | **Search strategija** — SKU-first ili naziv-first? Autocomplete? Typo tolerance? | SKU-first za B2B; Relevanssi za naziv. Autocomplete: SKU + naziv u rezultatu. | Dev + UX | Prije IA faze |
| INT-02 | **Filter layout** — sidebar vs. sticky header? URL state? Prioritet filtera za mix kategorija? | Sidebar s clear-all; brand + kategorija + dostupnost + skladište; URL state za dijeljive pretrage | UX | Prije low-fi faze |
| INT-03 | **Multi-warehouse korpa** — može li jedna korpa imati artikle iz više skladišta? | Da, uz UI indikaciju porijekla; split interno; ovisi i o odgovoru na B-02 | Dev + Marko | Nakon B-02 odgovora |
| INT-04 | **Basket price change** — tiho update, warning ili blokada? | Warning s confirm opcijom | UX + Marko | Nakon B-06 odgovora |
| INT-05 | **Quick order UX pattern** — quantity-first tabela ili search-first? | Quantity-first tabela s inline search kao sekundarnom navigacijom | UX (Marko ima plan) | Prije low-fi faze |
| INT-06 | **Desktop-first pretpostavka** — potvrda ili revizija | Desktop-first potvrđena; mobile-friendly sekundarno; validirati s klijentom (C-01) | Marko + team | Prije IA faze |
| INT-07 | **Pokazni artikli za UX validaciju** — 5–10 artikala različite kompleksnosti | Marko predlaže set, klijent potvrđuje; mora pokriti: simple, variant, multi-warehouse, promotional, pricing-edge | Marko + Dream Point | Prije low-fi faze |

---

*Za detalje: `docs/project-status-matrix.md` (executive overview), `docs/erp-validation-checklist.md` (kompletna Apros checklist), `docs/erp-discovery-findings.md` (discovery historija)*
