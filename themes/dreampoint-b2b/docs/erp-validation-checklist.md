# ERP Discovery & Validation Checklist — Apros

Dokument kreiran na osnovu ERP Assumption Inventory (2026-06-02).
Koristiti tokom prvih tehničkih sesija sa Apros timom kada ERP pristup postane dostupan.

Cilj: validovati pretpostavke pre nego što se napiše i jedan red Phase 4 koda.

**Companion:** `docs/erp-discovery-findings.md` — potvrđena poslovna pravila, nevalidirane pretpostavke i implementacijski blokeri (ažurirano 2026-06-03 na osnovu mail korespondencije Maj 2026).

---

## Kako koristiti ovaj dokument

- Svaka stavka ima **Question** (konkretan odgovor koji tražimo), **Why it matters**, **Validates** (ID-evi pretpostavki iz Assumption Inventory-ja) i **Impact** (šta se menja ako je odgovor drugačiji od očekivanog)
- Odgovori se upisuju inline u ovaj fajl odmah po dobijanju
- Stavke označene kao `[!]` su blokirajuće — faza 4 ne počinje bez odgovora na njih

---

## Priority 1 — Architecture Blockers

Ova pitanja određuju arhitekturu. Bez odgovora nije moguće doneti ni jednu implementacionu odluku.

---

### 1.1 Pricing Model — Per-Partner Cena `[!]`

**Question:**
Na koji način Apros isporučuje cene specifične za pojedinog poslovnog partnera?
- Šalje li se jedna cena po artiklu po price listi (partner ima dodeljenu price listu)?
- Ili se šalje individualna cena direktno po partneru (`sif_kup`)?
- Koje polje u payload-u nosi cenu?
- Postoji li više vrsta cena u istom payload-u (veleprodajn cena, akcijska cena, cena za strane kupce)?

**Why it matters:**
WooCommerce nema native podršku za per-partner price liste. Arhitekturalna odluka (custom user meta + filter vs. treće strane pricing plugin) direktno zavisi od toga kako Apros strukturira i isporučuje cene. Ovo je najveća arhitekturalna nepoznanica u čitavom projektu.

**Validates:** PR-1, PR-2, PR-3, PR-7, PR-9, PR-10, PR-11, PR-13

**Impact if answer differs:**
Ako Apros šalje individualnu cenu po partneru (ne po price listi), arhitektura je jednostavnija — user meta po korisniku. Ako postoji više price lista ili strane valute, potrebna je ili custom tabela ili pricing plugin. Pogrešna pretpostavka ovde znači kompletan rework pricing implementacije.

---

### 1.2 Product Identifier `[!]`

**Question:**
Po čemu Apros identifikuje artikle pri sync-u?
- Da li je identifikator **SKU** (šifra artikla)?
- Ili Apros-ov interni numerički ID?
- Je li identifikator jedinstven na razini varijante ili samo na razini parent produkta?
- Šalje li Apros i SKU i interni ID, ili samo jedan?

**Why it matters:**
Svaki product sync mehanizam (insert, update, deduplication, idempotency) mora biti baziran na jednom pouzdanom identifikatoru. Ako WooCommerce i Apros ne dele isti identifier, potreban je crosswalk mapping koji kompleksira arhitekturu.

**Validates:** P-4, P-11

**Impact if answer differs:**
Ako Apros koristi interni ID koji nije isti kao WC SKU, svaki artikl mora imati `_erp_product_id` meta koji se čuva na WP strani. Ako su identifikatori konzistentni, crosswalk nije potreban. Pogrešna pretpostavka ovde uzrokuje duple produkte ili neuspešne update-ove pri prvom sync-u.

---

### 1.3 Approval Webhook Body — Proširena Polja `[!]`

**Question:**
Kada Apros poziva `POST /wp-json/dreampoint-b2b/v1/approve-user`, šalje li webhook body samo `email`/`user_id`, ili i dodatna polja?
- Šalje li Apros **`sif_kup`** (interni identifikator partnera) u istom webhook pozivu?
- Šalje li Apros dodeljenu **price listu** u webhook pozivu?
- Šalje li Apros **`advance_only`** flag pri odobrenju?
- Postoje li drugi relevantni podaci o partneru koji stižu zajedno s odobrenjem?

**Why it matters:**
Trenutna implementacija webhook-a prima samo `email` ili `user_id`. Ako Apros šalje `sif_kup` i price listu u istom pozivu, implementacija je kompletna u jednom koraku. Ako ne, potreban je odvojeni sync mehanizam samo za partnerske meta podatke — što znači više moving parts i više točaka kvara.

**Validates:** CU-7, UA-12, PR-12, PAY-5, FS-5

**Impact if answer differs:**
Ako `sif_kup` ne stiže u approval webhook-u, potreban je odvojeni partner sync koji mapira Apros partner na WP korisnika. Bez ovog mappinga, per-partner discount i pricing sync su nemogući.

---

### 1.4 Variable Products u Apros Payload-u `[!]`

**Question:**
Kako Apros strukturira varijante (varijable proizvode) u `articleList/get` payload-u?
- Šalje li Apros parent produkt i svaku varijantu kao odvojene stavke u listi?
- Ili su varijante ugnježdene unutar parent artikla?
- Koji atribut razlikuje varijantu od parent-a u payload-u?
- Postoji li za svaku varijantu vlastiti SKU i vlastita cijena?

**Why it matters:**
WooCommerce variable products imaju drugačiju DB strukturu od simple produkta. Ako Apros ne razlikuje parent od varijante na razini payload-a, sync kôd mora inferirati tu razliku — što je kompleksno i sklono greškama.

**Validates:** P-6

**Impact if answer differs:**
Ako varijante nisu jasno odvojene od parent-a u Apros payload-u, potrebna je transformacijska logika unutar sync adaptera. Ovo može značajno kompleksirati import kôd i povećati rizik od neispravnog kreiranja/updateanja varijanti.

---

## Priority 2 — Integration Blockers

Ova pitanja određuju kako se integracija tehnički gradi. Bez odgovora nije moguće implementirati Phase 4.

---

### 2.1 Product Sync Model — Push vs. Pull, Full vs. Delta `[!]`

**Question:**
- Šalje li **Apros** promjene na WP endpoint (push model) ili **WP poziva Apros** API periodično (pull model)?
- Je li sync **full** (svaki put svi artikli) ili **delta** (samo promjene od zadnjeg sync-a)?
- Ako je delta: na koji način Apros označava promijenjene stavke (timestamp? version ID? change feed?)?
- Koji je maksimalni broj artikala u jednom API odgovoru?

**Why it matters:**
Pull model i push model zahtijevaju potpuno drugačiju arhitekturu. Full sync na velikom katalogu može biti memorijski intenzivan. Delta sync zahtijeva od Apros-a da Trackira promjene — što nije uvijek podržano.

**Validates:** BG-4, P-5, P-7, S-4, S-5

**Impact if answer differs:**
Ako Apros ne podržava delta sync, full sync mora biti optimiziran za velik katalog (paginacija, batch processing, memory management). Ako Apros koristi push model, WP treba inbound product webhook endpoint — što je suprotno od pull pretpostavke.

---

### 2.2 Order Sync API `[!]`

**Question:**
- Koji je **URL** Apros API endpoint-a za primanje WooCommerce narudžbi?
- Koji je **format payload-a** koji Apros očekuje (JSON? XML? custom struktura)?
- Koja polja su **obavezna** u order payload-u (partner ID, order lines, lokacija dostave, iznos, valuta)?
- Vraća li Apros **confirmation payload** s Apros order ID-em?
- Je li Apros endpoint **idempotent** (može li WP sigurno poslati istu narudžbu dva puta bez dupliciranja)?

**Why it matters:**
Order sync je highest-risk operacija u čitavoj integraciji. Duplirane narudžbine su poslovni problem. Bez idempotency garancije niti Apros order ID-a, retry logika postaje opasna.

**Validates:** OR-3, OR-4, OR-5, OR-6, OR-7, OR-8, OR-12, ERR-8

**Impact if answer differs:**
Ako Apros ne vraća confirmation order ID, WP ne može pouzdano preveriti da li je sync uspio. Ako endpoint nije idempotent, retry strategija mora biti konzervativnija (bez automatskog retry-a, s manualnom eskalacijom).

---

### 2.3 Delivery Location Data Model `[!]`

**Question:**
- Koji je **format** u kojem Apros šalje listu mjesta dostave po partneru?
- Koja polja sadrži jedna lokacija (naziv, adresa, grad, poštanski broj, zemlja, Apros location ID)?
- Šalje li Apros oznaku **default lokacije** za partnera?
- Može li partner imati **jednu ili više** lokacija dostave?
- Na koji trigger se ažurira lista lokacija (na approval webhook? Periodični sync? Na zahtjev?)?

**Why it matters:**
GLS plugin za ispis otpremnica treba primiti lokaciju dostave odabranu na checkoutu. Bez jasnog data modela nije moguće ni dizajnirati ni implementirati WC storage za lokacije, niti checkout UI za odabir.

**Validates:** DL-6, DL-7, DL-8, DL-9, DL-10, OR-2

**Impact if answer differs:**
Broj i struktura lokacija određuju storage model (user meta vs. custom post type vs. custom tabela). Ako lokacije mogu imati složenu strukturu ili ih može biti mnogo po partneru, user meta nije prikladan.

---

### 2.4 `advance_only` i Free Shipping — Mehanizam Dostave `[!]`

**Question:**
- Šalje li Apros **`advance_only`** flag kao dio approval webhook body-a, ili dolazi zasebnim sincronizacijskim kanalima?
- Šalje li Apros **free shipping flag** na isti način?
- Mogu li se ovi flagovi naknadno **promijeniti** za postojećeg partnera (npr. kompanija koja dobije kredit uklanja `advance_only`)?
- Ako se mijenjaju: koji je mehanizam ažuriranja (novi webhook poziv? periodični partner sync?)?

**Why it matters:**
Ako ovi flagovi stižu u approval webhook-u, implementacija je trivijalna — jednom se postave i gotovo. Ako dolaze zasebno ili se mijenjaju, potreban je odvojeni partner update mehanizam koji trenutno ne postoji.

**Validates:** PAY-4, PAY-5, PAY-6, FS-1, FS-5, FS-7

**Impact if answer differs:**
Ako `advance_only` može biti promijenjen za aktivnog partnera bez novog webhook poziva, WP ne može jamčiti konzistentnost payment restrictions. Potreban je ili partner sync cron ili Apros webhook za promjenu statusa.

---

### 2.5 Outbound Autentikacija (WP → Apros) `[!]`

**Question:**
- Kojom metodom WP treba se autentificirati pri pozivu Apros API-ja (API key u header-u? OAuth 2.0? Basic auth? JWT? Bearer token?)?
- Postoje li odvojeni credentials za različite operacije (product read, order write)?
- Na koji URL se šalju outbound pozivi (Apros base URL, verzija API-ja)?
- Postoji li **IP whitelist** na Apros strani koji ograničava odakle webhook pozivi mogu stizati na WP?

**Why it matters:**
Outbound auth model određuje kako se credentials čuvaju i kako se koriste. Ako Apros koristi OAuth, potreban je token refresh mehanizam — što je složenije od statičnog API key-a. IP whitelist određuje infrastrukturalne zahtjeve na serveru.

**Validates:** AUTH-3, AUTH-4, AUTH-5, AUTH-6, AUTH-7

**Impact if answer differs:**
OAuth zahtijeva znatno više implementacijskog rada od API key modela. IP whitelist može zahtijevati konfiguraciju na Hetzner serveru koji domaćinu nije trivijalan.

---

## Priority 3 — Operational Questions

Ova pitanja su važna za produkcijsku stabilnost ali ne blokiraju početak implementacije.

---

### 3.1 `sif_kup` Binding i Partner Identifikacija

**Question:**
- Koje je polje u Apros payload-u koje jednoznačno identificira poslovnog partnera (`sif_kup`)?
- Je li `sif_kup` numerički ID, alfanumerički kod, ili nešto drugo?
- Može li jedna kompanija imati **više WP korisnika** koji dijele isti `sif_kup`?
- Može li jedan WP korisnik biti vezan za **više `sif_kup`** unosa?

**Why it matters:**
`sif_kup` je ključ za per-partner discount i pricing sync. Bez jasne 1:1 ili 1:N veze između WP korisnika i Apros partnera, sync logika mora raditi s manje certitude.

**Validates:** CU-3, CU-5, CU-10, PR-12

**Impact if answer differs:**
Ako je odnos 1:N (više WP korisnika na isti `sif_kup`), pricing i discount sync mora biti per-company, ne per-user. Storage model se mijenja.

---

### 3.2 Deaktivacijski Webhook — Partner Closure

**Question:**
- Kada se Apros partner **deaktivira ili zatvori**, šalje li Apros webhook koji WP može primiti?
- Ako da: isti endpoint `approve-user` s drugačijim poljem, ili odvojen endpoint?
- Ako ne: kojim mehanizmom admin saznaje da treba maknuti pristup korisniku?

**Why it matters:**
Trenutno postoji samo approval webhook. Nema unapprove-from-ERP mehanizma. Ako partner prestane poslovati, admin mora ručno ukinuti pristup — što je operativni rizik na veće baze partnera.

**Validates:** CU-8

**Impact if answer differs:**
Ako Apros ne šalje deaktivacijski signal, process mora biti ručni ili periodički audit. Ako šalje, potreban je novi REST endpoint ili proširenje postojećeg.

---

### 3.3 Error Response Format

**Question:**
- Koji je **format Apros API error response-a** (HTTP status kod + JSON body? XML? plain text)?
- Šalje li Apros standardizirane error kodove za specifična stanja (npr. partner not found, duplicate order, auth error)?
- Postoji li Apros sandbox/test environment za testiranje error scenarija?

**Why it matters:**
WP retry logika i admin obavijesti o greškama ovise o tome može li WP parsirati Apros grešku i razumjeti je li grješka transijentna (retry) ili permanentna (escalate).

**Validates:** ERR-7, ERR-8, ERR-9

**Impact if answer differs:**
Ako Apros error format nije JSON ili nije standardiziran, WP mora raditi s heurističkim error parsing-om — što je krhko i teško testirati.

---

### 3.4 Stock Sync Model

**Question:**
- Je li stock sync **real-time** (Apros push per promjeni) ili **batch** (periodično)?
- Podržava li Apros **multi-warehouse** (više skladišta s odvojenim zalihama)?
- Koji je format stock payload-a (SKU + qty, ili Apros product ID + qty)?
- Što se događa s WC backordering kada zaliha padne na 0 prema Apros-u — može li partner i dalje naručiti?

**Why it matters:**
Real-time stock sync zahtijeva inbound webhook i kompleksniji prijem. Multi-warehouse model zahtijeva agregaciju ili selekciju skladišta pri svakom stock update-u.

**Validates:** S-3, S-5, S-6, S-7

**Impact if answer differs:**
Multi-warehouse podrška drastično komplicira stock sync. WC nativno ne podržava per-warehouse stock — potrebna je custom implementacija ili plugin.

---

### 3.5 `dp_skip_visibility` Auth Hardening

**Question:**
- Hoće li Apros pozivi na WC REST product API (za potrebe sync-a) biti autentificirani WP Application Passwordom?
- Ako da: koji WP korisnički račun će se koristiti za Apros API pozive?
- Ili će sync raditi u pozadini (Action Scheduler) bez REST poziva?

**Why it matters:**
Trenutni `dp_skip_visibility` bypass je vezan za query arg koji nije autentikovan sekundarno. Ako Apros REST pozivi dolaze s autentikacijom, bypass može biti vezan za tu autentikaciju umjesto za neautentikovan param.

**Validates:** VIS-6, VIS-7, VIS-8, AUTH-8

**Impact if answer differs:**
Ako Apros REST pozivi dolaze bez WP auth i oslanjaju se na `dp_skip_visibility` param, security rupa ostaje otvorena i mora biti adresirana posebnom mjeram.

---

### 3.6 Order Status Sync — Bidirectionality

**Question:**
- Nakon što Apros primi narudžbu, šalje li Apros **status update** natrag u WC (npr. "potvrđeno", "isporučeno", "stornirano")?
- Ako da: koji su to Apros statusi i kako se mapiraju na WC order statuse?
- Je li status sync jednosmjeran (WC → Apros) ili dvosmjeran?

**Why it matters:**
Ako Apros šalje status update, WC mora imati inbound status webhook i mapping logiku. Bez ovoga, korisnik ne vidi realtime status narudžbe u My Account.

**Validates:** OR-9

**Impact if answer differs:**
Dvosmjeran status sync zahtijeva dodatni WP REST endpoint i definiran status mapping. Jednosmjeran sync je znatno jednostavniji.

---

### 3.7 Sync Frequency i Cron Schedule

**Question:**
- Koji je preporučeni interval za product sync cron (hourly? svakih 15 minuta? dnevno)?
- Je li stock sync na istom cronskom intervalu ili odvojenom?
- Ima li Apros API **rate limit** (max poziva po minuti ili satu)?

**Why it matters:**
Cron interval određuje freshness podataka na WC strani. Rate limit određuje batch veličinu i paginacijske strategije.

**Validates:** BG-5, BG-6, S-4, V-7

**Impact if answer differs:**
Strogi rate limit zahtijeva paginaciju i throttling u sync kodu. Previsoka frekvencija može preopteretiti Apros ili WP server.

---

## Priority 4 — Nice To Know

Ova pitanja poboljšavaju kvalitetu integracije ali ne blokiraju implementaciju.

---

### 4.1 Apros Sandbox Environment

**Question:**
- Postoji li Apros sandbox/staging environment koji WP može koristiti za testiranje?
- Jesu li sandbox podaci (partneri, artikli, narudžbe) odvojeni od produkcije?
- Kako se pristupa sandbox environment-u (isti API URL, drugi credentials)?

**Validates:** V-6, AUTH-5

**Impact if answer differs:**
Bez sandbox-a, integracija se mora testirati na produkcijskim podacima, što uvodi rizik lažnih narudžbi ili neispravnih partner podataka.

---

### 4.2 Photos i Media Sync

**Question:**
- Šalje li Apros **URL-ove slika** u product payload-u, ili binarne podatke?
- Ako URL: ostaje li URL stabilan između sync-ova, ili se mijenja?
- Koji su podržani formati slika?
- Koliko slika po artiklu?

**Validates:** P-9

**Impact if answer differs:**
Ako Apros URL-ovi nisu stabilni (mijenjaju se pri svakom eksportu), WP ne može cachirati slike i mora ih re-downloadati pri svakom sync-u — što može biti sporo na velikom katalogu.

---

### 4.3 Token Rotation Policy

**Question:**
- Može li se `DP_ERP_WEBHOOK_SECRET` rotirati bez downtime-a?
- Koji je preporučeni interval rotacije tokena (godišnje? po potrebi?)?
- Postoji li procedura za dogovorenu rotaciju s Apros timom?

**Validates:** AUTH-7

**Impact if answer differs:**
Ako token rotation zahtijeva koordiniranu promjenu na obje strane istovremeno, downtime window mora biti planiran.

---

### 4.4 Apros Logging i Audit Trail

**Question:**
- Logira li Apros primljene WP narudžbe s timestampom i statusom?
- Mogu li Apros logs biti konzultirani u slučaju disputed sync-a?
- Postoji li Apros-ov dashboard za pregled primljenih narudžbi?

**Validates:** ERR-7, LOG-5

**Impact if answer differs:**
Ako Apros nema audit trail, jedini izvor istine za failed/disputed sync je WP log. Ovo povećava važnost WP-side logging kvalitete.

---

### 4.5 Parcijalne Narudžbine i Backorder Handling

**Question:**
- Može li Apros primiti **parcijalnu** narudžbinu (neke stavke dostupne, neke na backordering)?
- Što se događa s WC narudžbinom ako Apros ne može ispuniti sve stavke?
- Šalje li Apros notification za out-of-stock stavke?

**Validates:** OR-10, S-6

**Impact if answer differs:**
Ako Apros ne podržava parcijalne narudžbine, WC mora biti konfiguriran tako da ne dozvoli ordering stavki kojih nema na zalihi u Apros-u — što zahtijeva real-time stock sync.

---

### 4.6 Kategorije Kreirane u Apros-u

**Question:**
- Na koji način Apros označava kategoriju artikla u payload-u (ID? naziv? hijerarhijski kod?)?
- Jesu li kategorije kreirane u Apros-u i sync-ane u WC, ili admin ručno kreira kategorije u WC?
- Postoji li hijerarhija (parent → child kategorije)?

**Validates:** P-3, P-10

**Impact if answer differs:**
Ako kategorije dolaze iz Apros-a, sync mora kreirati ili ažurirati WC taksonomiju `product_cat` — što zahtijeva mapping između Apros identifikatora i WC term slugova.

---

## Zaključna Napomena

Sve stavke označene `[!]` moraju imati popunjene odgovore **prije** nego što se pristupi Phase 4 planiranju.

Pri prvom kontaktu s Apros timom, preporuča se redom:
1. Pitanja 1.1 do 1.4 (arhitekturalni odgovori koji imaju najširi uticaj)
2. Pitanja 2.1 do 2.5 (integracijski detalji)
3. Ostatak prioriteta 3 i 4 (operativni detalji)

Odgovori se upisuju inline ispod svakog pitanja, s datumom i izvorom.
