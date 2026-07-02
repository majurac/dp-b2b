# Architectural Decisions — Dreampoint B2B

Log arhitekturalnih odluka (ADR — Architecture Decision Record) za projekat. Svaka odluka dokumentuje kontekst, samu odluku, i posledice. Odluke se ne brišu kad zastare — označavaju se kao Superseded uz referencu na odluku koja ih zamenjuje.

**Format:** Status / Context / Decision / Consequences / Related

---

## ADR-001 — Pricing Architecture (Model A domaći + Model C strani)

**Datum:** 2026-07-02
**Status:** Accepted (arhitektura) — payload finalizacija u toku
**Vlasnik:** ERP integracija (Apros)

### Context

Semantika `wholesalePrice` polja u Apros `articleList/get` endpointu je bila nerazriješena od Maja 2026 — dva nezavisna iskustvena izvora (Leo Benkek email, integrator; Milenko Stojaković, developer B2C iskustva) davala su direktno konfliktne interpretacije:

- **Leo Benkek:** `wholesalePrice` = bazna/katalog cijena, ista za sve partnere; Rabat 1 je obavezan per-partner mehanizam za finalnu cijenu (Model A)
- **Milenko Stojaković:** `wholesalePrice` = generalno već finalna B2B cijena; Rabat 1 je rijedak opcionalni sloj (Model B)

Ovaj konflikt (V-01 u `docs/erp-discovery-findings.md`) je bio najveći arhitekturalni bloker projekta (BL-03, P0) — svaki model zahtijeva potpuno drugačiju pricing engine arhitekturu (storage, runtime kalkulacija, caching).

Dodatno, tretman stranih kupaca (bez Rabat 1 mehanizma, cjenik po državama) je zahtijevao zaseban paralelni model (Model C), nezavisno od domaćeg pricing pitanja.

### Decision

Nakon direktnog Apros odgovora (2026-07-02):

- **Domaći kupci → Model A.** `wholesalePrice` je bazna veleprodajna cijena. Rabat 1 je postotni popust po partneru i brandu (iz `partnerBrandDiscountList` / ugovorni uvjeti endpointa). Finalna cijena = `wholesalePrice − Rabat 1 (%)`.
- **Strani kupci → Model C.** Nema Rabat 1 mehanizma. Finalna neto cijena dolazi direktno iz `countryPriceList`. PDV tretman ovisi o pravnoj/poreznoj kategoriji partnera.
- **Model B se ne implementira.** Dokumentiran je u `docs/b2b-erp-adaptation-blueprint.md` Sekciji 5 isključivo radi povijesnog konteksta konflikta — ne predstavlja aktivnu arhitekturalnu opciju.
- Modeli A i C su **paralelni slojevi po tax profilu korisnika** (`domestic` / `foreign` / `tax_exempt`), ne alternative koje se biraju globalno.

### Consequences

- Pricing engine (Faza 4 / Korak 11) može biti arhitekturalno dizajniran i implementiran (filter hook, storage struktura, caching strategija) bez čekanja na dodatnu Apros validaciju modela.
- **Payload primjer i dalje nedostaje** — egzaktni field nazivi i realne vrijednosti za `partnerBrandDiscountList` i `countryPriceList` nisu dostavljeni. Finalna implementacija i QA (potvrda field mappinga, edge case-ovi) ostaju blokirani do payload primjera — vidi `docs/apros-session-final-pack.md` → "Still Required From Apros".
- BL-03 downgraded: HIGH (arhitekturalni rizik) → MEDIUM (payload finalizacija).
- AP-01 u `docs/apros-question-resolution-matrix.md`: PARTIALLY RESOLVED.
- Storage model (Model A: `_wholesale_price` + `_b2b_brand_rabat` user meta; Model C: `_wholesale_price_country` JSON) je zaključan — promjena nakon implementacije Faze 4 bi zahtijevala rework.

### Related

- `docs/erp-discovery-findings.md` — Discovery Revision — Apros Response Integration Update (NC-06, NC-07)
- `docs/apros-question-resolution-matrix.md` — AP-01
- `docs/b2b-erp-adaptation-blueprint.md` — Sekcija 5 (Pricing Architecture), Sekcija 9 (AP-01)
- `docs/b2b-erp-migration-plan.md` — Korak 11

---

## ADR-002 — Partner Approval Architecture (polling/import, ne webhook)

**Datum:** 2026-07-02
**Status:** Accepted
**Vlasnik:** ERP integracija (Apros) / Customer-Partner Architecture

### Context

Raniji arhitekturalni dizajn (`docs/b2b-erp-adaptation-blueprint.md`, planirano prije 2026-07-02) je pretpostavljao da Apros po odobrenju partnera poziva WP inbound webhook (`POST /wp-json/dreampoint-b2b/v1/approve-partner`) koji nosi `sif_kup`, ugovorne uvjete i/ili `advance_only`/`free_shipping` flagove. Ova pretpostavka je bila nevalidirana (NC-05 u discovery findings — potvrđen samo konceptualni tok, ne tehnički mehanizam) i predstavljala je P1 bloker (ranije AP-05 u internoj blueprint numeraciji; AP-03 u kanonskoj `apros-question-resolution-matrix.md`).

### Decision

Apros je direktno potvrdio (2026-07-02): **approval webhook ne postoji.** Potvrđeni tok:

```
web registracija → email notifikacija (Točka sna) → ručno kreiranje partnera u Apros-u
  → Apros postavlja atribut B2B KUPAC = DA
  → partner se pojavljuje na partner list endpointu (nema signala prema WP)
```

Partner sinkronizacija se implementira kao **cron-based polling/import job** — WP periodično poziva partner list endpoint, detektira nove/promijenjene `B2B KUPAC = DA` zapise, i za svaki pokreće partner data fetch + B2B rola dodjelu. Inbound REST endpoint receiver (`approve-partner`) se **ne implementira** u ranije planiranom obliku.

Kao direktna posledica, `advance_only`/`free_shipping` propagacija (ranije AP-08) je reklasificirana kao **OUT OF SCOPE** za inicijalnu implementaciju — pitanje se temeljilo isključivo na webhook mehanizmu koji ne postoji.

### Consequences

- `docs/b2b-erp-adaptation-blueprint.md` Sekcija 4 (Approval Lifecycle) je prepravljena — koraci [5]-[6] (webhook) zamijenjeni koracima [5]-[7] (polling job detekcija i fetch).
- `docs/b2b-erp-migration-plan.md` Korak 10 preimenovan iz "Approval webhook endpoint" u "Partner polling/import job" — može startati **odmah**, bez daljnje Apros validacije (prethodno: 🔴 NE, sada: ✅ DA).
- Korak 9 (partner sync adapter) prelazi iz potpuno blokiranog u djelomično odblokiran — arhitektura poznata, ostaje payload finalizacija za delivery locations (AP-07) i partner list format (PL-01, bez kanonskog AP ekvivalenta).
- Operativna implikacija: onboarding kašnjenje sada ovisi o cron frekvenciji polling joba, ne o webhook pouzdanosti — frekvencija nije arhitekturalni bloker, samo operativna optimizacija.
- Deaktivacijski mehanizam (ranije pretpostavljen kao mogući budući webhook) je sada konzistentno tretiran kao dio istog polling modela — nema posebnog deaktivacijskog kanala.
- AP-03 u `docs/apros-question-resolution-matrix.md`: RECLASSIFIED. AP-08: OUT OF SCOPE.

### Related

- `docs/erp-discovery-findings.md` — Discovery Revision — Apros Response Integration Update (NC-10)
- `docs/apros-question-resolution-matrix.md` — AP-03, AP-08
- `docs/b2b-erp-adaptation-blueprint.md` — Sekcija 4 (Customer/Partner Architecture), Sekcija 9 (AP-03)
- `docs/b2b-erp-migration-plan.md` — Korak 9, Korak 10
- `docs/project-status-matrix.md` — Sekcija 1.7 (Registracija i onboarding)
