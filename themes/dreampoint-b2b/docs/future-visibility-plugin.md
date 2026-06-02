# Future UncleDev Visibility Plugin

Handoff dokument za buduće sesije i buduće projektno planiranje.
Ovo je konsolidacija zaključaka iz završenog review ciklusa — ne aktivni plan, ne implementation task.

Kreiran: 2026-06-02

---

## Executive Summary

DP-B2B Visibility System je validan kandidat za standalone UncleDev shared plugin pod nazivom `uncledev-catalog-visibility`. Core engine je već u velikoj meri reusabilan. Integration layer sadrži većinu preostalog coupling-a sa DP-B2B projektom. Ekstrakcija je mali napor sa niskim rizikom i niskim migrationimpact-om na DP-B2B. Ekstrakcija ne treba da se dogodi dok ne postoji drugi realni projekat koji treba sistem.

---

## Why Extraction Makes Sense

- Core engine (`Visibility_Engine`, `Visibility_Context`, `Bucket_Context`, `Query_Filter`, `Access_Guard`, `Access_Table`) je već generičan — nema specifičan DP-B2B business logic u jezgru
- Sistem je arhitekturalno zreo: prošao je testiranje, Redis caching, WooFilter Pro kompatibilnost, i više razvojnih faza
- Future projekti (B2B prodavnice, ERP-integrovane prodavnice, role-based katalozi, franchise modeli) imaju isti problem koji ovaj sistem rešava
- Extraction effort je mali — majority posla je refactoring prefiksa, uklanjanje theme hook-ova, i konfigurabilnost taxonomy-a
- Migration impact na DP-B2B je nizak — tema dobija isti funkcionalni sistem, samo servisan iz plugin-a umesto iz `inc/visibility/`

---

## Why Extraction Should Not Happen Yet

- Nema drugog realnog projekta koji treba ovaj sistem. Ekstrakcija bez drugog korisnika kreira plugin oblikovan pretpostavkama jednog projekta — te pretpostavke neće biti vidljive dok ih drugi projekat ne sukobi.
- Preuranjana ekstrakcija povećava maintenance burden bez odgovarajuće koristi.
- Plugin treba da bude oformljen kontaktom sa drugačijim projektnim kontekstom — DP-B2B specifičnosti postanu vidljive tek kada postoji poređenje.

---

## Proven Findings

### Arhitektura sistema (7 klasa)

| Klasa | Odgovornost |
|-------|-------------|
| `Dreampoint_B2B_Visibility_Engine` | Resolves i merge-uje bucket rules + user overrides u Visibility_Context. Dvoslojni cache: static request + Redis/WP object cache. |
| `Dreampoint_B2B_Visibility_Context` | Value object koji nosi pristupna pravila za jednog korisnika |
| `Dreampoint_B2B_Bucket_Context` | Value object koji nosi pravila jednog bucketa |
| `Dreampoint_B2B_Access_Table` | Upravlja custom DB tabelom (`{prefix}_dp_user_access`). Instalacija i upgrade putem `dbDelta()`. |
| `Dreampoint_B2B_Bucket_CPT` | Custom post type za bucket-e. Admin UI za upravljanje bucket pravilima. |
| `Dreampoint_B2B_Query_Filter` | Modifikuje WooCommerce WP_Query da filtrira katalog po visibility pravilima |
| `Dreampoint_B2B_Access_Guard` | Štiti direktne product URL-ove od neovlašćenog pristupa |

### Coupling analiza

**Generičan (reusabilan bez izmena):**
- Core engine logika (context resolution, cache invalidation)
- SQL enforcement pattern
- Dvoslojni caching model (request + Redis)
- Query filter i access guard pattern

**Coupling koji zahteva refactoring pre ekstrakcije:**

| Coupling | Lokacija | Šta treba promeniti |
|----------|----------|---------------------|
| `Dreampoint_B2B_` class prefix | Sve klase | → `UncleDev_Catalog_Visibility_` ili ekvivalent |
| `after_switch_theme` hook za instalaciju tabele | `inc/visibility.php` | → `register_activation_hook` |
| `product_brand` taxonomy slug (hardkodiran) | `class-query-filter.php` | → konfigurabilna opcija ili filter |
| ACF admin UI | `inc/custom-offer-admin.php` + ACF field groups | → soft dependency, graceful degradation bez ACF |
| `dreampoint-b2b` text domain | Sve translatable stringovi | → `uncledev-catalog-visibility` |

---

## Recommended Architecture

**Plugin naziv:** `uncledev-catalog-visibility`

Obrazloženje izbora: "catalog" opisuje WooCommerce kontekst bez vezivanja za B2B branding. Nije `b2b-visibility` (previše usko), nije `access-control` (previše široko — implicira opšti ACL sistem), nije `visibility` (previše generičan).

**Klase (post-ekstrakcija):** sve klase dobijaju `UncleDev_Catalog_Visibility_` prefiks ili ekvivalentan, projektno-neutralan namespace.

**Hook/filter API:** treba biti dizajniran kao stabilan javni ugovor pre prve produkcione upotrebe na drugom projektu. MAJOR version bump = dozvoljeni breaking change na ovom API-ju.

---

## Recommended Scope

**Visibility-only.** Plugin radi jednu stvar: kontroliše koji WooCommerce proizvodi su vidljivi kom korisniku, zasnovano na bucket pravilima i user-level override-ima.

**Šta NE ulazi u plugin:**
- B2B pricing / wholesale tiers
- ERP sinhronizacija
- Registration approval flow
- Custom checkout logika
- User role management

Ako UncleDev ekosistem razvije više B2B modula, oni ostaju zasebni plugini. Meta-plugin `uncledev-b2b-suite` može deklarisati zavisnosti, ali svaki modul mora biti deployabilan nezavisno.

---

## Dependency Model

| Zavisnost | Tip | Obrazloženje |
|-----------|-----|--------------|
| WooCommerce | **REQUIRED (hard)** | `Query_Filter` modifikuje WC WP_Query, `Access_Guard` štiti WC product URL-ove. Non-WC verzija ne postoji. |
| ACF | **OPTIONAL (soft)** | Core engine radi bez ACF-a. ACF se koristi za admin UI. U odsustvu ACF-a, plugin treba funkcionisati sa bazičnim WP Settings API UI. |
| Brand taxonomy | **CONFIGURABLE** | Trenutno `product_brand` — mora postati konfigurabilni taxonomy slug. Projekti bez brand taksonomije ne smeju biti blokirani. |

**Minimalna WC verzija:** mora biti utvrđena pre ekstrakcije (pregled `Query_Filter` API call-ova).

---

## Migration Impact Summary

**Impact na DP-B2B temu pri ekstrakciji:** nizak.

- `inc/visibility/` folder i `inc/visibility.php` se uklanjaju iz teme
- `inc/custom-offer-admin.php` prelazi u plugin
- `functions.php` loader blok se zamenjuje dependency check-om (`class_exists('UncleDev_Catalog_Visibility_Engine')` ili ekvivalent)
- Redis cache ključevi će se promeniti (novi class prefix) → jedan cache flush pri deploy-u
- WooFilter Pro integracija ostaje kompatibilna (filteri ostaju konzistentni)
- Test users (`vis_none`, `vis_full`, `vis_rule_cat`, `vis_rule_brand`, `vis_offer`) ostaju validni za verifikaciju

---

## Open Questions

Ova pitanja ostaju otvorena i moraju biti razrešena pre ili tokom ekstrakcije:

1. **Postoji li drugi B2B projekat u planu?** — Primarni trigger za ekstrakciju. Bez drugog korisnika, ekstrakcija je preuranjena.

2. **Koji ACF field groups pokrivaju bucket management admin UI?** — Određuje koliko je posla de-coupling ACF zavisnosti.

3. **Koja je minimalna WC verzija na kojoj `Query_Filter` radi?** — Direktno određuje compatibility floor plugina.

4. **Da li je `product_brand` taxonomy slug već konfigurabilna ili hardkodiran?** — Određuje koliko truda zahteva de-coupling taxonomy zavisnosti.

5. **Koji je mechanism distribucije plugina između projekata?** — Manualno kopiranje, privatni composer registry, ili WP plugin ZIP? Utiče na versioning discipline i upgrade workflow.

6. **Da li sistem radi korektno na projektima bez Redis-a?** — Treba dokumentovati graceful degradation na statički request cache.

---

## Trigger Conditions For Extraction

Ekstrakcija je opravdana kada su **sve** od sledećih tačaka istinite:

- [ ] Postoji drugi realni projekat koji treba catalog visibility sistem
- [ ] Plugin naziv je finalizovan (`uncledev-catalog-visibility` ili alternativa) — ime se ne menja nakon prve produkcione upotrebe van DP-B2B
- [ ] Coupling lista (prefiks, theme hooks, taxonomy, ACF) je kompletno razrešena
- [ ] Hook/filter API je dokumentovan kao stabilan ugovor
- [ ] Minimalna WC verzija je utvrđena i dokumentovana
- [ ] `register_activation_hook` + `dbDelta()` zamenjuju `after_switch_theme` hook

Svaki od ovih uslova koji nije ispunjen = ekstrakcija se odlaže.

---

## Future Work

Ovo nije lista zadataka za tekuću sesiju — ovo je reference lista za buduće planiranje kada trigger uslovi budu ispunjeni.

**Pre ekstrakcije (jednom kada trigger uslovi budu ispunjeni):**
1. Finalizovati plugin naziv i potvrditi da nema konfliktnih WordPress plugin slug-ova
2. Refaktorisati `after_switch_theme` → `register_activation_hook`
3. Zameniti sve `Dreampoint_B2B_` prefikse u klasama i hook imenima
4. Učiniti taxonomy slug konfigurabilnim (filter ili plugin settings)
5. Dizajnirati i dokumentovati hook/filter API kao javni ugovor
6. Utvrditi ACF soft dependency strategiju i fallback UI

**Post-ekstrakcija:**
1. Testirati na drugom projektu — ovo je dokaz reusability-a
2. Adresovati projekat-specifične pretpostavke koje su procurile u core
3. Dokumentovati: installation guide, configuration guide, hook/filter reference, upgrade guide
4. Stabilan v1.0

**Dugoročno:**
- Zasebni repozitorijum, nezavisno versioning
- Semantic versioning: `MAJOR.MINOR.PATCH`
- DB schema verzija u `wp_options` (`uncledev_catalog_visibility_db_version`)
- MAJOR = breaking promene API-ja ili DB sheme
