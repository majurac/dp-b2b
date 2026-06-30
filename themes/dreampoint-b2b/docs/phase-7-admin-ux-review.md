# Phase 7 — Admin UX Review

**Plugin:** `uncledev-product-bundles`
**Datum:** 2026-06-10
**Scope:** Pre-implementaciona evaluacija dva UX i maintainability pitanja
**Status:** Preporuke usvojena — videti Phase 7 Impact sekciju

---

## Kontekst

Validovani poslovni use case: display stand za naočale.
Display stand je jedini potvrđeni bundle use case u ovom trenutku.
Plugin treba ostati generički i ekstenzibilan.

Dva pitanja su identifikovana pre početka implementacije:

1. Koji default string koristiti za summary label?
2. Da li bundle konfiguraciju treba ograničiti na određene proizvode (feature gating)?

---

## Topic 1 — Summary Label Strategy

### Problem

Trenutna specifikacija prikazuje `"Sadrži 50 modela naočala"` kao primjer summary labela.
Taj string je čvrsto spregnut s display stand use caseom i pretpostavlja jezik (HR/SR).

Plugin koristi `__()` + text domain `uncledev-product-bundles` na svim stringovima —
konsekventno, default plugin string mora biti engleski i kontekstualno neutralan.

### Evaluacija opcija

**Opcija A — `"Sadrži %d artikala"`**
Jezički spregnut (HR/SR). Pogrešan za plugin koji može biti upotrebljen na bilo kom projektu.

**Opcija B — `"Paket sadrži %d artikala"`**
Dodaje pretpostavku da je svaki bundle "paket" — semantički pogrešno za display stand kontekst.
Display stand nije paket; to je stend koji dolazi s naočalama.

**Opcija C — engleski default, projekat overriduje filter**
Plugin default ostaje generički i jezički neutralan. Projekat primenjuje specifičnu kopiju
kroz `uncledev_bundles_summary_label` filter koji je već definisan u specifikaciji.

### Preporuka: Opcija C

**Default plugin string:**

```php
$label = sprintf(
    /* translators: %d: number of bundle items */
    __( 'Includes %d items', 'uncledev-product-bundles' ),
    $count
);
$label = apply_filters( 'uncledev_bundles_summary_label', $label, $count, $product_id );
```

**Dreampoint B2B override (tema ili projekt-level plugin):**

```php
add_filter( 'uncledev_bundles_summary_label', function( $label, $count, $product_id ) {
    return sprintf( 'Sadrži %d modela naočala', $count );
}, 10, 3 );
```

**Zašto ovo:**
- Plugin default ne pretpostavlja ni jezik ni bundle tip
- Display stand kopija ne zahteva izmenu plugina
- Filter prima `$product_id` — projekat može primeniti različitu kopiju per-product ili per-category
- Budući bundle tipovi ne zahtevaju plugin izmene

### Phase 7 Impact

- Sekcija 6.1 `phase-7-implementation-spec.md`: prikazati `"Includes %d items"` kao plugin default
- Sekcija 6.1: primjer `"Sadrži 50 modela naočala"` ostaje, ali označen kao project-level filter override
- Task 5 (Frontend rendering): `sprintf( __( 'Includes %d items', ... ), $count )` kao polazna vrednost pre filtera

---

## Topic 2 — Bundle Feature Gating

### Problem

Bundle konfiguracija polja (Mode + Items repeater) trenutno se prikazuje na **svim** WooCommerce
proizvodima. U praksi, samo display stendovi koriste bundle sistem. Ostali proizvodi ne treba
da vide bundle UI.

### Evaluirani pristupi

#### Pristup A: Plugin settings page — Category Gating

Admin bira dozvoljene kategorije u WooCommerce → Product Bundles.
Bundle UI se prikazuje samo na proizvodima koji pripadaju tim kategorijama.

| Dimenzija | Ocena |
|-----------|-------|
| Admin UX | Čist za svakodnevno uređivanje — 99% proizvoda bez bundle UI |
| Tehniča kompleksnost | Visoka — settings page, nova wp_options, migracija, nova klasa grešaka |
| Buduća fleksibilnost | Ograničena — bundle ne mora biti per-kategorija decision |
| Rizik od misconfiguracije | Srednji-visoki — kategorija je mutable; reorganizacija kataloga može tiho ukloniti bundle UI bez greške |
| Konzistentnost | Asimetrična — category check za UI ne garantuje isti check u runtime-u |
| Zaključak | **Ne preporučujem** |

**Ključni problem:** Kategorija nije stabilna mapa bundle sposobnosti. Display stand može biti
u više kategorija. Ako admin premjesti stand u neku drugu kategoriju, bundle konfiguracija
ostaje u bazi ali UI nestaje — tiha greška bez ikakve povratne informacije.

#### Pristup B: Dedicated checkbox per product (Preporučeno)

Jedno `true_false` ACF polje `_udp_bundle_enabled` kao prvo polje u `group_udp_product_bundle`.
Default: `false`. ACF conditional logic skriva Mode i Items repeater dok je checkbox `false`.

| Dimenzija | Ocena |
|-----------|-------|
| Admin UX | Na 99% proizvoda: samo checkbox (isključen, nevidljiv overhead) |
| Tehniča kompleksnost | Minimalna — jedno ACF polje, conditional logic kaskada |
| Buduća fleksibilnost | Visoka — per-product, nema kategorijskog coupling-a |
| Rizik od misconfiguracije | Nizak — explicit per-product decision, vidljiv stanje |
| Konzistentnost | Simetričan — isti flag kontroliše i UI i runtime |
| Zaključak | **Preporučujem** |

**Prednost konzistentnosti:** `Data::is_bundle_product( $product_id )` čita isti flag koji
skriva UI — nema razlike između admin prikaza i runtime ponašanja.

#### Pristup C: Custom product attribute

WooCommerce atributi su primarno za varijante i filtriranje. Vidljivi su kupcima na frontendu —
semantički neprikladan za admin-only konfiguraciju. Odbačeno.

#### Pristup D: Custom product tag

Tagovi su vidljivi kupcima. Odbačeno iz istog razloga kao atribut.

#### Pristup E: Custom product type

Registrovati `bundle` kao WooCommerce product type. Najsnažnija separacija, ali zahteva
override WC klasa, visoku kompleksnost, i semantički menja prirodu produkta.
Preterano za Phase 7. Kandidat za daleku budućnost ako se bundle use case drastično proširi.

### Preporuka: Pristup B — Dedicated checkbox

```php
// Novo prvo polje u Fields::get_fields()
[
    'key'           => 'field_udp_bundle_enabled',
    'label'         => __( 'Enable Bundle', 'uncledev-product-bundles' ),
    'name'          => '_udp_bundle_enabled',
    'type'          => 'true_false',
    'message'       => __( 'This product includes a bundle of additional items', 'uncledev-product-bundles' ),
    'default_value' => 0,
    'wrapper'       => [ 'width' => '50' ],
]
```

**ACF conditional logic kaskada:**

```
_udp_bundle_enabled = 1
    → prikazati _udp_bundle_mode (select)
        → _udp_bundle_mode != 'disabled'
            → prikazati _udp_bundle_items (repeater)
```

**Runtime konzistentnost:**

```php
// Data.php — novi helper
public static function is_bundle_product( int $product_id ): bool {
    return (bool) get_field( '_udp_bundle_enabled', $product_id );
}
```

Cart logika proverava ovaj flag pre pokretanja bundle validate/handle pipeline-a.
Ovo eliminiše nepotrebne ACF read pozive na non-bundle proizvodima.

### Da li je category gating bolja opcija?

Ne. Display stand use case zahteva per-product opt-in, ne per-category feature toggle.
Razlozi zašto kategorija nije pravo rešenje:

1. Kategorijska struktura se menja tokom životnog veka prodavnice
2. Jedan display stand može biti u više kategorija (cross-listing)
3. `Display Stands` kategorija može u budućnosti sadržati stendove koji ne koriste bundle sistem
4. Tihe greške pri reorganizaciji su visokog rizika i teško uočljive

### Phase 7 Impact

- `class-bundle-fields.php`, `Fields::get_fields()`:
  dodati `field_udp_bundle_enabled` kao prvo polje
  ažurirati conditional logic na `field_udp_bundle_mode` da zavisi od `field_udp_bundle_enabled`
- `class-bundle-data.php`, `Data`:
  dodati `is_bundle_product( int $product_id ): bool` helper
- `class-bundle-cart.php`, `Cart::validate_bundle_before_add()` i `Cart::handle_bundle_after_add()`:
  rani izlaz ako `! Data::is_bundle_product( $product_id )`
- `uninstall.php`: dodati `_udp_bundle_enabled` u `$meta_keys`
- Definition of Done sekcija: dodati checkbox za `_udp_bundle_enabled` field

---

## Da li se Phase 7 scope menja?

Da, minimalno. Ove izmene su aditivan scope koji ne menja arhitekturu.

| Promena | Tip | Effort |
|---------|-----|--------|
| Promeniti default summary label string u `"Includes %d items"` | Minorna korekcija | 5 min |
| Dodati `field_udp_bundle_enabled` checkbox u Fields | Novi ACF field | +30 min u Task 1 |
| Ažurirati conditional logic na `field_udp_bundle_mode` | Modifikacija Task 1 | uključeno u gore |
| Dodati `Data::is_bundle_product()` helper | Nova metoda | +30 min u Task 2 |
| Dodati rani izlaz u Cart po `is_bundle_product()` | Minimalni kartguard | +20 min u Task 3 |
| Dodati `_udp_bundle_enabled` u uninstall | Jednolinjska izmena | uključeno u Task 4 |

**Ukupni overhead:** ~80 minuta. Unutar granice normalnog Task 1–4 efforta.

---

## Konačni odgovori

**1. Koji je default summary label?**

`"Includes %d items"` (engleski, plugin-nivo). Projekat overriduje `uncledev_bundles_summary_label` filterom za display stand kopiju.

**2. Da li bundle konfiguracija treba biti dostupna na svim proizvodima ili ograničena?**

Ograničena — kroz dedicated `_udp_bundle_enabled` checkbox per product.

**3. Da li je category gating najbolji pristup za validovani display stand use case?**

Ne. Checkbox per product je bolji — konzistentnost između UI i runtimea, nema coupling-a na kategorijsku strukturu, nema settings page kompleksnosti.
