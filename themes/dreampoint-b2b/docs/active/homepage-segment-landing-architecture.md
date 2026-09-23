# Homepage & Segment Landing — Final Structure (Design Record)

**Status (2026-09-23):** IMPLEMENTIRANO I DEPLOYOVANO — Homepage rebuild + 3 Segment Landing stranice (Lifestyle/Toys/Outdoor) + ADR-009 Faza B vidljivost wiring, plus focused frontend fix (Brands slider enqueue, Company Features de-slider). Deployovan na staging (`dreampoint.b2b.uncledev.cloud`), commit `c4dc61d4f5674a3eb6da59490210f243019fee1e` (gradi na `feef07b61b59bbaec50a39b6ec306126bad51457`). Puna evidencija: `docs/decisions.md` ADR-009 (svi §Update unosi, 2026-09-23).

**Verifikacija:** potpuno lokalno (Playwright, browser, real HTTP). Na stagingu: hash potvrđen + server-side/statička provera; stvarna browser/JS inicijalizacija NIJE direktno posmatrana tamo (nema staging test-korisničkih kredencijala u ovoj sesiji, sajt globalno redirektuje neautentifikovane posjetioce).

**Figma vernost:** strukturna/sadržajna, iz Figma metadata (ne pixel-perfect — `get_screenshot` je bio rate-limited cijelu sesiju, nikad izvršen).

**Otvoreno (content-population, NE implementacioni defekt):**
- Realne `brand_segment` vrijednosti na ERP-sync brendovima (posebno Outdoor) — Segment Landing product sekcije ostaju prazne dok se ne popune preko wp-admin.
- "Istaknuti proizvodi" ručna kuracija po Segment Landing stranici.
- `features_items` ACF Options (Company Features sadržaj) je `NULL` na stagingu — pre-postojeći, nezavisan gap.

**Otvoreno (vizuelni polish follow-up):** "Badge" hero polje namjerno odloženo (blokirano DB-only ACF field grupom, ne slučajno izostavljeno — vidi ADR-009). Hero slike generičke/reused, ne potvrđeni Figma asset-i. Pixel-level Figma poređenje.

**Otvoreno (tehnički follow-up, van scope-a implementacije):** `docs/active/block-css-cache-busting-followup.md` — block-level CSS dijeli globalni `_S_VERSION` cache-bust sa `style.css`/`theme.min.js`.

~~**Status:** FINAL sadržajna specifikacija potvrđena od klijenta (2026-09-21, `B2B odgovori na pitanja.docx`, §5.1 EDIT). **Implementacija NIJE započeta.** Arhitekturalni gap u vidljivost engine-u je dokumentovan u `docs/decisions.md` ADR-009 i mora biti riješen prije bilo kakvog koda koji dira frozen vidljivost sistem.~~ (superseded — gap riješen, implementacija završena, vidi gore)

Ne miješati sa trenutnim, danas živim homepage sadržajem — ovaj dokument opisuje BUDUĆU strukturu. Trenutni homepage layout se koristi kao konceptualna/layout osnova za Segment Landing stranice (vidi §2), ne kao konačan cilj sam po sebi.

---

## 1. Homepage — FINALNA struktura

Sekcije (redoslijed nije nužno finalan, samo sadržajna lista):

1. **Segments** — 3 segmenta: Lifestyle, Toys, Outdoor (linkuju na Segment Landing stranice, §2)
2. **Brands** blok
3. **Featured section** blok
4. **Company Features** blok
5. **Sales Representative** sekcija

**Vidljivost:** Svaki autentifikovani korisnik vidi KOMPLETAN homepage sadržaj. Customer-specifična bucket/Special Offers/product visibility pravila NE SMIJU ukloniti sadržaj sa homepage-a.

**Header/footer:** TENTATIVE — trenutna pretpostavka je da homepage možda neće imati standardni site header/footer, ali ovo NIJE finalizovano. Ne implementirati na osnovu ove pretpostavke bez eksplicitne potvrde.

---

## 2. Segment Landing stranice (Lifestyle / Toys / Outdoor)

Trenutni homepage layout (postojeći ACF blokovi) služi kao konceptualna osnova za sadržaj ovih stranica, uz DVIJE izmjene:

- **`Featured Brand` blok (`blocks/templates/featured-brand.php`) se UKLANJA** iz Segment Landing kompozicije.
- Product-sekcije (Latest Products, Bestsellers, itd.) OSTAJU, ali moraju prikazivati sadržaj koji pripada TRENUTNOM SEGMENTU, ne globalni katalog.

### Segment filtering ≠ Customer/bucket filtering

Ovo je ključna distinkcija koja se ne smije pomiješati:

- **Segment filtering** — Segment Landing sadržaj MORA biti filtriran po `Lifestyle`/`Toys`/`Outdoor` segmentu (postojeći `brand_segment` ACF koncept iz `brands.php`, ili ekvivalentna nova taksonomija/meta — implementacijski detalj, nije odlučeno).
- **Customer/bucket filtering** — customer-specifična vidljivost (bucket liste, Special Offers, ostala pravila iz `inc/visibility/`) se **NE SMIJE primijeniti** na Segment Landing nivou. Lifestyle Segment Landing smije prikazati SVE Lifestyle proizvode/sadržaj — ne smije dalje uklanjati proizvode zato što bi ih trenutno prijavljeni korisnik inače video ograničeno dublje u katalogu.

Customer-specifična vidljivost počinje TEK kad korisnik ode dublje sa Segment Landing stranice u normalan katalog/kategorija/proizvod kontekst.

### Konceptualni tok

```
Autentifikovan korisnik
  → Homepage                    (shared/unrestricted po customer bucket-u)
  → Segment Landing             (restriktovano po SEGMENTU; i dalje unrestricted po customer bucket-u)
  → dublji katalog/sadržaj      (segment/kategorija/itd. kontekst; normalna customer/bucket/Special Offers pravila važe)
```

---

## 3. Poznat arhitekturalni gap — vidljivost engine

Puna analiza: `docs/decisions.md` ADR-009.

Ukratko: `inc/visibility/class-query-filter.php` danas bezuslovno filtrira SVAKI `WP_Query` sa `post_type => product` i sve `get_terms('product_brand')` pozive, bez obzira na stranicu. Empirijski potvrđeno (lokalno, `vis_none` test korisnik) da trenutni homepage blokovi Latest Products, Bestseller, Discounted Products, Featured Products i Brands carousel svi NESTAJU ili se prazne za restriktovanog korisnika. Segment Landing NE SMIJE nasljediti ovo ponašanje za customer-bucket dimenziju, ali MORA zadržati (novu) segment-filtering dimenziju.

**Preduslov za implementaciju:** eksplicitno odobren plan za query-level vidljivost-izuzetak (vidi ADR-009 preporučenu arhitekturu) — frozen sistem, ne smije se tiho zaobići per-blok patch-ovima.

---

## 4. Povezano

- `docs/decisions.md` ADR-009 — puna investigacija, preporučena arhitektura, blast-radius analiza
- `inc/visibility/class-query-filter.php` — frozen implementacija koja zahtijeva izmjenu
- `docs/active/current-phase.md` — Frozen Systems tabela
- `brands.php`, `blocks/templates/brands.php` — postojeći `brand_segment`/segment-navigation koncept koji se može ponovo iskoristiti za segment filtering
- `inc/myaccount-komercijalist.php` — postojeća Sales Representative implementacija (već funkcionalna na homepage-u i My Account tabu; Contact stranica nije potvrđena)
