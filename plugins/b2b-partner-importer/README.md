# B2B Partner Importer - WordPress Plugin

## 📋 Opis

WordPress plugin za import B2B partnera iz Excel ili CSV datoteke u WooCommerce, s automatskim matchanjem zaduženih komercijalista.

## ✨ Značajke

- ✅ Import iz Excel (.xlsx, .xls) ili CSV datoteka
- ✅ Automatsko kreiranje WooCommerce korisnika
- ✅ Mapiranje svih billing i shipping adresa
- ✅ Automatsko matchanje komercijalista po email adresi
- ✅ Slanje password reset emailova
- ✅ Test mod (dry run) za provjeru prije stvarnog importa
- ✅ Detaljan log svih akcija
- ✅ Progress bar tijekom importa
- ✅ Provjera duplikata
- ✅ Automatska generacija korisničkih imena

## 📦 Instalacija

### Korak 1: Instaliraj plugin

1. Uploadaj cijeli `b2b-partner-importer` folder u `/wp-content/plugins/`
2. Ili zapakuj sve datoteke u .zip i uploadaj preko WordPress admin panela

### Korak 2: Instaliraj dependencies (PhpSpreadsheet)

**Potreban je Composer i PHP na serveru.**

SSH na server i pokreni:

```bash
cd /wp-content/plugins/b2b-partner-importer
composer install --no-dev
```

Alternativno, ako nemaš pristup SSH-u, možeš:
1. Lokalno na svom računalu pokreni `composer install --no-dev`
2. Uploadaj cijeli `includes/vendor` folder na server

### Korak 3: Aktiviraj plugin

1. Idi u WordPress Admin → Plugins
2. Nađi "B2B Partner Importer"
3. Klikni "Activate"

## 🚀 Korištenje

### 1. Priprema Excel/CSV datoteke

Datoteka mora imati sljedeće kolone **točnim redoslijedom**:

| #  | Naziv kolone              | Opis                                           | Obavezno |
|----|---------------------------|------------------------------------------------|----------|
| 1  | OIB                       | OIB tvrtke                                     | Da       |
| 2  | Šifra partnera            | Task ID iz ERP-a                               | Ne       |
| 3  | Naziv tvrtke              | Puni naziv tvrtke                              | Da       |
| 4  | Korisničko ime            | Username (ako prazno, koristi se dio emaila)   | Ne       |
| 5  | Ime                       | Ime osobe                                      | Da       |
| 6  | Prezime                   | Prezime osobe                                  | Da       |
| 7  | Email                     | Email adresa                                   | Da       |
| 8  | Mobitel                   | Broj mobitela                                  | Ne       |
| 9  | Ulica                     | Naziv ulice                                    | Ne       |
| 10 | Kućni broj                | Kućni broj                                     | Ne       |
| 11 | Mjesto                    | Grad/Mjesto                                    | Ne       |
| 12 | Poštanski broj            | Poštanski broj                                 | Ne       |
| 13 | Email komercijalista      | Email zaduženog komercijalista                 | Ne       |

**Napomena:** Prvi red mora biti zaglavlje (header row).

### 2. Preuzmi template

U pluginu postoji gumb **"📥 Preuzmi Excel Template"** koji daje primjer datoteke s pravilnom strukturom.

Lokacija: `/wp-content/plugins/b2b-partner-importer/template/b2b-import-template.csv`

### 3. Pokreni import

1. Idi u **WordPress Admin → Tools → Import B2B Partnera**
2. Odaberi datoteku (Excel ili CSV)
3. Odaberi opcije:
   - ✅ **Pošalji email za postavljanje lozinke** - Šalje reset link svim novim korisnicima
   - ✅ **Test mod** - Ne sprema podatke, samo prikazuje što bi se desilo
4. Klikni **"🚀 Započni Import"**

### 4. Provjeri rezultate

Plugin će prikazati:
- 📊 Statistiku (Uspješno / Preskočeno / Greške)
- 📋 Detaljan log svake akcije
- ⚠️ Upozorenja ako nešto nije u redu

## 🔧 Tehnički detalji

### Što plugin radi:

1. **Parsira Excel/CSV** datoteku
2. **Validira podatke** (email format, obavezna polja)
3. **Provjerava duplikate** (po email adresi)
4. **Kreira WooCommerce korisnika** s rolom "customer"
5. **Sprema custom meta podatke:**
   - `oib` - OIB tvrtke
   - `task_id` - Šifra partnera iz Task-a
   - `assigned_komercijalist` - Post ID povezanog komercijalista
6. **Postavlja billing adresu** (za WooCommerce narudžbe)
7. **Postavlja shipping adresu** (za dostavu)
8. **Matchanje komercijalista:**
   - Traži CPT "komercijalist" po ACF polju `email_commercialist`
   - Sprema Post ID u user meta `assigned_komercijalist`
9. **Šalje password reset email** (ako odabrano)

### Generirano korisničko ime:

Ako polje "Korisničko ime" je prazno, plugin automatski generira username:
- Uzima dio emaila prije @ znaka
- Sanitizira (uklanja specijalne znakove)
- Ako već postoji, dodaje brojeve (npr. `korisnik1`, `korisnik2`)

**Primjer:** 
- Email: `ivan.horvat@firma.hr` → Username: `ivan.horvat`

### Država (Country):

- Default je postavljena na **HR** (Hrvatska) za billing i shipping
- Ovo možeš lako promijeniti u kodu ako treba

## 🛠️ Prilagodbe

### Promjena customer role

Ako želiš drugu ulogu umjesto "customer", promijeni u `b2b-partner-importer.php` liniju:

```php
'role' => 'customer', // Promijeni u 'b2b_partner' ili neku drugu custom role
```

### Dodavanje dodatnih polja

Ako trebaš importirati dodatna custom polja, dodaj ih u funkciju `create_b2b_user()`:

```php
update_user_meta($user_id, 'custom_field_name', $data['custom_value']);
```

### Promjena default države

Promijeni u funkciji `create_b2b_user()`:

```php
update_user_meta($user_id, 'billing_country', 'HR'); // Promijeni 'HR' u 'BA', 'SI', itd.
```

## 📧 Password Reset Email

Email koji korisnik dobiva sadrži:
- Korisničko ime
- Email
- Reset link (važeći 24 sata)

Email možeš customizirati u funkciji `send_password_reset_email()`.

## ⚠️ Troubleshooting

### "PhpSpreadsheet not found" greška

**Rješenje:** Nisi instalirao dependencies. Pokreni:
```bash
composer install --no-dev
```

### "WooCommerce nije aktiviran"

**Rješenje:** Aktiviraj WooCommerce plugin.

### Import se ne pokreće

**Rješenje:** 
1. Provjeri da datoteka ima točan format (.xlsx, .xls, .csv)
2. Provjeri da prvi red ima zaglavlja
3. Provjeri da email adrese su validne

### Komercijalisti se ne matchaju

**Rješenje:**
1. Provjeri da CPT "komercijalist" postoji
2. Provjeri da imaju ACF polje `email_commercialist` popunjeno
3. Emailovi u Excel-u moraju biti identični

### Test mod ne radi

**Rješenje:** Test mod radi - samo ne sprema podatke. Pogledaj log za detalje.

## 🔒 Sigurnost

- ✅ Samo administratori mogu koristiti plugin
- ✅ AJAX zahtjevi su zaštićeni nonce-om
- ✅ Email validacija
- ✅ Username sanitizacija
- ✅ Provjera duplikata prije kreiranja

## 📝 Changelog

### Version 1.0.0
- Inicijalna verzija
- Excel i CSV import
- Automatsko matchanje komercijalista
- Password reset funkcionalnost
- Test mod
- Detaljan logging

## 👨‍💻 Support

Za pitanja ili probleme, kontaktiraj: **team@uncledev.info**

## 📄 Licenca

GPL v2 or later

---

**Napravio:** UncleDev Team 🚀
**Verzija:** 1.0.0
