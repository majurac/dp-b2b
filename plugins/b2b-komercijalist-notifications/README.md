# B2B Komercijalist Notifications

WordPress plugin koji automatski šalje kopiju email notifikacija o novim narudžbama dodijeljenom komercijalisti.

## 📋 Opis

Addon plugin za **B2B Partner Importer** koji omogućava automatsko slanje email notifikacija komercijalisti kada njegov B2B partner napravi narudžbu.

## ✨ Značajke

- ✅ Automatski dodaje komercijalista u **CC** WooCommerce email notifikacija
- ✅ Radi samo za **"Nova narudžba"** email tip
- ✅ Jednostavno uključivanje/isključivanje u admin sučelju
- ✅ Log u Order Notes kada je email poslan
- ✅ Status provjera u admin panelu
- ✅ Failsafe - ako nema komercijalista, email ide normalno samo adminu

## 📦 Instalacija

### Preduvjeti

- WordPress 5.8+
- WooCommerce (aktivan)
- B2B Partner Importer plugin (za dodjelu komercijalista korisnicima)
- ACF (Advanced Custom Fields) - za CPT "Komercijalist"

### Instalacija

1. Uploadaj plugin folder u `/wp-content/plugins/`
2. Aktiviraj plugin u WordPress Admin
3. Idi na **Tools → B2B Email Notifikacije**
4. Označi checkbox "Omogući slanje emailova komercijalisti"
5. Klikni "Spremi postavke"

## 🚀 Korištenje

### Automatski rad

Plugin automatski radi u pozadini:

1. B2B korisnik napravi narudžbu
2. WooCommerce kreira "Nova narudžba" email za admina
3. Plugin dohvaća `assigned_komercijalist` iz user meta
4. Dohvaća email iz CPT "Komercijalist" (ACF polje: `email_commercialist`)
5. Dodaje komercijalista u CC
6. Email ide adminu + komercijalisti istovremeno

### Postavke

**Tools → B2B Email Notifikacije**

- Checkbox za uključivanje/isključivanje funkcionalnosti
- Status provjera (WooCommerce, CPT, korisnici)
- Upute za troubleshooting

## 🔧 Kako radi?

### Tehnički detalji

Plugin koristi WooCommerce hook:
```php
add_filter('woocommerce_email_headers', 'add_komercijalist_to_cc', 10, 3);
```

**Workflow:**
1. Hook hvata sve WooCommerce emailove
2. Filtrira samo `new_order` tip
3. Dohvaća `$order->get_customer_id()`
4. Čita user meta: `assigned_komercijalist`
5. Dohvaća email iz CPT: `get_field('email_commercialist', $komercijalist_id)`
6. Dodaje u CC header: `Cc: Ime Prezime <email@example.com>`

### User Meta struktura

Plugin očekuje da korisnik ima:
```php
update_user_meta($user_id, 'assigned_komercijalist', $komercijalist_post_id);
```

(Ovo kreira B2B Partner Importer plugin prilikom importa)

### CPT Struktura

Očekuje CPT `komercijalist` s ACF poljem:
```php
'email_commercialist' // email adresa komercijalista
```

## 📊 Što vidiš u admin-u?

### Status provjera

Plugin prikazuje:
- ✅ **WooCommerce** status (aktivan/neaktivan)
- ✅ **CPT "Komercijalist"** (broj pronađenih)
- ✅ **B2B korisnici** (koliko ima dodijeljene komercijaliste)
- ✅ **Email notifikacije** status (uključene/isključene)

### Order Notes

U svakoj narudžbi vidiš note:
```
Email notifikacija poslana komercijalisti: Neven Popović (neven.popovic@firma.hr)
```

## ⚠️ Troubleshooting

### Komercijalist ne prima emailove?

**Provjeri:**

1. **User ima dodijeljenog komercijalista?**
   - Users → Edit User → B2B Partner Informacije
   - Mora biti odabran komercijalist u dropdown-u

2. **Komercijalist ima email?**
   - Komercijalisti → Edit Komercijalist
   - ACF polje "Email" mora biti popunjeno

3. **Order Notes**
   - Otvori narudžbu
   - Provjeri Order Notes
   - Vidiš li "Email notifikacija poslana..."?

4. **Postavke**
   - Tools → B2B Email Notifikacije
   - Je li checkbox označen?

5. **Spam folder**
   - Provjeri spam/junk folder komercijalista

### Email se ne šalje ni adminu?

To je WooCommerce problem, ne ovaj plugin. Provjeri:
- WooCommerce → Settings → Emails
- Je li "New Order" email omogućen?
- Testaj s WooCommerce test emailom

### "Class 'WC_Order' not found"

WooCommerce nije aktivan. Aktiviraj WooCommerce plugin.

### "Call to undefined function get_field()"

ACF plugin nije instaliran ili nije aktivan.

## 🎯 Primjer scenarija

### Setup:

1. Importiraš 50 B2B partnera preko B2B Partner Importer-a
2. Svaki partner ima dodijeljenog komercijalista
3. Komercijalisti: Neven, Ana, Marko (3 osobe)

### Rad:

- **Partner 1** (dodijeljen Neven) → napravi narudžbu
  - Admin dobije email ✅
  - Neven dobije CC ✅
  
- **Partner 2** (dodijeljen Ana) → napravi narudžbu
  - Admin dobije email ✅
  - Ana dobije CC ✅

- **Partner 3** (NEMA komercijalista) → napravi narudžbu
  - Admin dobije email ✅
  - Nitko drugi ne dobije (ok, nema komercijalista)

## 📝 Changelog

### Version 1.0.0
- Inicijalna verzija
- Automatsko slanje CC na "Nova narudžba" emailove
- Admin settings stranica
- Status provjera
- Order notes logging

## 👨‍💻 Support

Za pitanja ili probleme:
- Email: team@uncledev.info
- Tools → B2B Email Notifikacije → Status provjera

## 📄 Licenca

GPL v2 or later

---

**Autor:** UncleDev Team  
**Verzija:** 1.0.0  
**Requires:** WordPress 5.8+, WooCommerce, ACF
