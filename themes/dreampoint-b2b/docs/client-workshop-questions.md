# CLIENT WORKSHOP QUESTIONS — Dreampoint B2B
**Kreiran:** 2026-06-03
**Izvor analize:** UX Foundation Dokument + ERP Discovery Findings + Leo Benkek email + Marko bilješke

> Ovaj dokument sažima 45-stranični discovery dokument na akcijski set pitanja.
> Pitanja koja su već odgovorena **nisu uključena** — vidi sekciju "Isključeno" ispod.

---

## ISKLJUČENO — Već odgovoreno

Sljedeće stavke iz UX dokumenta su uklonjene jer imaju potvrđene odgovore:

| Pitanje | Izvor odgovora |
|---------|---------------|
| Kreiranje i aktivacija B2B računa | Leo email + Marko: web forma → admin prepišu u ERP → ERP signal → CMS odobrenje |
| Svaki korisnik ima zaseban nalog (čak i ista firma) | Marko bilješka u UX doc, potvrđeno |
| Rollout faze (Izipizi → Lifestyle → Igračke) | ERP Discovery Findings |
| Paralelni rad starog i novog B2B-a | ERP Discovery Findings: ne, nema paralelnog rada |
| Prikaz cijena (neto bez PDV-a na listingu, PDV u košarici) | ERP Discovery Findings — potvrđeno |
| Rabati — mehanizam (Rabat 1 po brandu, ugovorni uvjeti endpoint) | Leo Benkek email + ERP Discovery |
| Cijene po državama (finalna neto cijena za strane kupce) | Leo Benkek email |
| Vidljivost artikala — vlasništvo (B2B CMS, ne Apros) | Leo Benkek email — eksplicitno potvrđeno |
| 4 skladišta (Glavno, Igračke, Naočale, Lifestyle) | ERP Discovery Findings |
| Automatsko brand-to-warehouse mapiranje | ERP Discovery Findings |
| Finansijski pregled (view order + PDF stampa) | Marko: "dogovoreno sa Marinom — Jekaa model" |
| Out of stock — default ponašanje (prikazati bez stanja) | Marko: "Jekaa model — sve ostalo treba novi estimate" |
| B2B atribut eligibilnosti (`b2bArticle`) | ERP Discovery Findings |
| Kataloški broj artikla na PDP-u | ERP Discovery Findings |
| Onboarding logika (web forma → email Točka sna → ručni Apros → atribut odobrenje) | Leo Benkek email |

---

## PITANJA ZA CLIENT WORKSHOP — Dream Point

### Blok A — Korisnici i permisije (KRITIČAN — direktno utiče na IA i UX arhitekturu)

**A-01 — Role sistem**
Postoje li različite role korisnika unutar sistema, ili su svi B2B korisnici funkcionalno jednaki?
- Ako postoje role: koje su konkretne razlike (može li npr. employee vidjeti cijene? kreirati narudžbu? pregledati fakture)?
- Ako nema rola: potvrdi da svi korisnici imaju identičan pristup.

*Zašto je kritično:* Role sistem direktno određuje navigaciju, account strukturu i permissions arhitekturu. Pogrešna pretpostavka znači rework IA-e.

---

**A-02 — Firma s više korisnika — shared order history**
Svaki korisnik ima zaseban nalog (potvrđeno). Međutim:
- Da li zaposleni iz iste firme trebaju vidjeti narudžbe ostalih korisnika iste firme?
- Ili je order history strogo per-user?

*Zašto je kritično:* "Moj račun" i order history UX su potpuno drugačiji ovisno o odgovoru.

---

**A-03 — Approval flow za narudžbe**
Da li postoji interni approval flow unutar firme (npr. zaposleni kreira narudžbu, manager odobrava)?
- Ako da: koji su statusi, ko odobrava, šta se dešava s narudžbom u međuvremenu?
- Ako ne: potvrdi da svaki korisnik može direktno potvrditi narudžbu.

---

### Blok B — Ordering logika (VISOK PRIORITET — utiče na checkout i cart UX)

**B-01 — Packaging i minimalne količine**
Ima li sistem pravila o minimalnim količinama ili pakovanjima?
- Naručuje li se artikal po komadu, kutiji, kartonu, display jedinici?
- Postoje li quantity stepovi (npr. može se naručiti samo višekratnik od 6)?
- Jesu li ova pravila ista za sve kategorije ili variraju po brandu/kategoriji?

*Zašto je kritično:* Packaging logika direktno utiče na quantity input UX, quick order tabelu, add-to-cart validacije i error stateove.

---

**B-02 — Split shipment — korisnikovo iskustvo**
Korisnik može naručivati artikle iz različitih skladišta. Šta žele kao iskustvo?
- Jedna narudžba koja se interno razdvaja (korisnik ne vidi razdvajanje)?
- Ili korisnik eksplicitno naručuje "iz Skladišta X" i "iz Skladišta Y" zasebno?
- Da li se prikazuje napomena "artikli iz različitih skladišta šalju se zasebno"?

---

**B-03 — Quick reorder i bulk ordering**
Je li Quick reorder eksplicitni poslovni zahtjev (dakle: mora biti u scope-u)?
- Da li korisnici žele ponoviti prethodnu narudžbu jednim klikom?
- Je li CSV/Excel import narudžbi eksplicitni zahtjev?
- Favorites/order liste — zahtjev ili nice-to-have?

---

**B-04 — Out of stock — smije li se naručiti?**
Default je prikazati artikal bez stanja (bez mogućnosti narudžbe). Potvrdi:
- Je li preorder (narudžba van zalihe) ikad dozvoljena?
- Ako je van zalihe: prikazati "Notify me" opciju ili samo informaciju?
- Postoji li razlika u ponašanju po kategoriji (npr. igračke nikad preorder, lifestyle možda)?

---

**B-05 — Metode plaćanja**
Koje metode plaćanja su planirane i za koje tipove partnera?
- Virman (standardno)?
- Odgođeno plaćanje (kredit)?
- Avansno plaćanje (advance_only flag)?
- Da li svi partneri imaju iste opcije ili se razlikuju po ugovornim uvjetima?

*Napomena:* `advance_only` flag dolazi iz Apros-a, ali klijent treba potvrditi koje kombinacije su realne i kako se prikazuju u checkoutu.

---

**B-06 — Edge case — promjena cijene u korpi**
Šta se dešava ako se cijena artikla promijeni dok je u korpi?
- Tiho ažurirati cijenu?
- Prikazati warning korisniku?
- Blokirati checkout dok korisnik ne potvrdi novu cijenu?

---

### Blok C — Admin i upravljanje sadržajem (SREDNJI PRIORITET)

**C-01 — Upravljanje homepageom i bannerima**
Ko i kako upravlja sadržajem na homepageu?
- Banneri: ručno CMS ili automatski iz ERP-a?
- "Novi proizvod" i "Posebna ponuda" oznake: ERP automatski ili admin ručno može override-ati?
- Ko ima admin pristup CMS-u za sadržaj?

---

**C-02 — Promotivne akcije**
Koje tipove promocija sistem treba podržavati?
- Flash sale (vremenski ograničena)?
- Grupna akcija (npr. brand-level popust)?
- ERP-driven posebna ponuda?
- Ko kreira i tko odobrava akcije?

---

### Blok D — Mobile (SREDNJI PRIORITET)

**D-01 — Mobile usage**
Koliko je mobile važan za vaše B2B korisnike?
- Koje akcije korisnici rade na mobileu (quick reorder? pregled stanja? naručivanje)?
- Da li je mobile sekundarni kanal (desktop primarni) ili punopravni kanal?

*Odgovor direktno određuje responsive strategiju i mobile UX prioritizaciju.*

---

### Blok E — Edge case scenariji (NIŽI PRIORITET — ali treba odgovor)

**E-01 — Deaktivacija partnera**
Šta se dešava kada partner prestane biti B2B kupac?
- Šta je s aktivnim narudžbama u toku?
- Šta je s historijom narudžbi — ostaje vidljiva?
- Tko pokreće deaktivaciju (Apros automatski ili Dream Point admin ručno)?

**E-02 — Relacije među proizvodima**
Treba li sistem prikazivati related products, upsell ili cross-sell prijedloge?
- Ako da: ko ih definira (CMS ručno ili automatski algoritam)?
- Bundle proizvodi — postoje li?

---

## PITANJA ZA APROS TEHNIČKU SESIJU

*Ova pitanja su već u `docs/erp-validation-checklist.md` — ovdje su istaknuta samo ona s direktnom UX implikacijom.*

| Ref | Pitanje | UX implikacija |
|-----|---------|---------------|
| 1.1 | Je li `wholesalePrice` bazna cijena ili neto-s-rabatom? | Određuje pricing display arhitekturu |
| 1.4 | Kako su varijante strukturirane u `articleList/get` payload-u? Zaseban SKU po varijanti? | Product card + quick order UX |
| 2.2 | Order endpoint format — Apros prima jednu narudžbu ili može primiti split po prodajnom mjestu? | Checkout flow i split shipment UX |
| 3.4 | Kada Apros rezerviše stanje (u korpi, checkoutu ili tek ERP potvrdom)? | Stock display i checkout messaging |
| 3.4 | Da li Apros šalje EAN/barcode u ArticleList? | Search by barcode mogućnost |
| 3.4 | Da li Apros dozvoljava narudžbu artikala kojih nema na stanju? | Out of stock UX — preorder vs. blokada |
| 3.2 | Mehanizam ažuriranja rabata za aktivnog partnera | Edge case — prikaži warning u korpi? |

---

## INTERNE ODLUKE TIMA — Još su otvorene

| ID | Odluka | Ko odlučuje | Napomena |
|----|--------|-------------|----------|
| INT-01 | Search prioritet: SKU-first vs. naziv-first? Autocomplete? Recent searches? | Dev + UX | Relevanssi je aktivan — konfiguracija je interna odluka |
| INT-02 | Filter layout: sidebar vs. header sticky? URL state ponašanje? Kombinovanje filtera? | UX | Naglašena kompleksnost zbog mix-a kategorija (igračke + naočale + lifestyle) |
| INT-03 | Multi-warehouse korpa: može li jedna korpa imati artikle iz više skladišta? | Dev + Marko | Tehničke implikacije na Apros order endpoint — zavisi i od B-02 odgovora |
| INT-04 | Basket price change handling: tiho update, warning ili blokada? | UX + Marko | Tek nakon klijentovog odgovora na B-06 |
| INT-05 | Quick order UX pattern: quantity-first tabela ili search-first? | UX (Marko ima plan) | Razjasniti s timom prije low-fi faze |
| INT-06 | Desktop-first pretpostavka — potvrditi ili revidirati | Marko + client (D-01) | Pretpostavka u UX doc-u; treba client validation |
| INT-07 | Pokazni artikli: 5–10 artikala za UX wireframe validaciju — ko ih bira? | Marko + Dream Point | Potrebno dogovoriti s klijentom koji artikli pokrivaju dovoljnu kompleksnost |

---

## SAŽETAK

| Kategorija | Broj pitanja |
|-----------|-------------|
| Već odgovoreno — isključeno | 15 |
| Pitanja za klijenta (Dream Point workshop) | 13 |
| Pitanja za Apros tehničku sesiju | 7 |
| Interne odluke tima | 7 |
| **Ukupno otvorenih** | **27** |

**Preporučeni slijed workshops-a:**
1. Blok A + B sa Dream Point klijentom (blokiraju IA i checkout UX)
2. Interne odluke INT-01 do INT-05 (blokiraju low-fi wireframe fazu)
3. Apros tehnička sesija — ERP validacija (blokiraju Phase 4 implementaciju)
4. Blok C + D + E sa klijentom (sekundarni, ne blokiraju IA)
