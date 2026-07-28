# 📦 Instalacija B2B Partner Importer Plugina

## Pregled

Ovaj dokument sadrži detaljne upute za instalaciju i postavljanje B2B Partner Importer plugina.

---

## 🔧 Preduvjeti

Prije instalacije, provjerite da imate:

- ✅ WordPress 5.8 ili noviji
- ✅ WooCommerce plugin aktiviran
- ✅ PHP 7.4 ili noviji
- ✅ Composer (za instalaciju dependencies)
- ✅ ACF (Advanced Custom Fields) plugin (za Komercijalist CPT)

---

## 📥 Metoda 1: Instalacija putem Upload-a (Preporučeno)

### Korak 1: Priprema

1. Preuzmi ili kloniraj cijeli `b2b-partner-importer` folder
2. Zapakuj folder u `.zip` datoteku (ako nije već)

### Korak 2: Upload

1. Idi u **WordPress Admin Panel**
2. Klikni **Plugins → Add New**
3. Klikni **Upload Plugin**
4. Odaberi `.zip` datoteku
5. Klikni **Install Now**
6. **NE AKTIVIRAJ JOŠ PLUGIN!** (prvo moramo instalirati dependencies)

### Korak 3: Instalacija Dependencies

Trebaš instalirati PhpSpreadsheet biblioteku putem Composera.

**Opcija A: SSH pristup serveru (Preporučeno)**

```bash
# Konektaj se na server
ssh user@your-server.com

# Idi u plugin folder
cd /var/www/html/wp-content/plugins/b2b-partner-importer

# Instaliraj dependencies
composer install --no-dev --optimize-autoloader

# Generiraj Excel template
php generate-template.php
```

**Opcija B: cPanel File Manager**

Ako nemaš SSH pristup:

1. Lokalno na svom računalu:
   ```bash
   cd b2b-partner-importer
   composer install --no-dev
   ```

2. Zapakuj cijeli `includes/vendor` folder u .zip
3. Idi u cPanel → File Manager
4. Navigiraj do `/wp-content/plugins/b2b-partner-importer/includes/`
5. Uploadaj `vendor.zip`
6. Ekstraktiraj `vendor.zip`

**Opcija C: Automatska skripta**

Plugin dolazi s `install-dependencies.sh` skriptom:

```bash
cd /wp-content/plugins/b2b-partner-importer
chmod +x install-dependencies.sh
./install-dependencies.sh
```

### Korak 4: Aktivacija

1. Idi u **WordPress Admin → Plugins**
2. Nađi **B2B Partner Importer**
3. Klikni **Activate**

### Korak 5: Provjera

1. Idi na **Tools → Import B2B Partnera**
2. Ako vidiš admin stranicu, instalacija je uspješna! ✅

---

## 🔌 Metoda 2: Instalacija putem FTP/SFTP

### Korak 1: Upload

1. Konektaj se putem FTP clienta (FileZilla, Cyberduck, itd.)
2. Navigiraj do `/wp-content/plugins/`
3. Uploadaj cijeli `b2b-partner-importer` folder

### Korak 2: Instalacija Dependencies

Slijedi **Korak 3** iz Metode 1 gore.

### Korak 3: Aktivacija

Slijedi **Korak 4** i **5** iz Metode 1 gore.

---

## 🛠️ Post-Instalacija Setup

### 1. Provjera Komercijalista CPT

Plugin pretpostavlja da već imate CPT "komercijalist" s ACF poljem `email_commercialist`.

Provjeri:

```php
// Idi u WordPress Admin
// Tools → Site Health → Info → WordPress Constants
// Provjeri da li postoji CPT "komercijalist"
```

Ako ne postoji, kreira ga (ili dodaj kod iz README.md).

### 2. Testni Import

1. Preuzmi **CSV template** iz plugina
2. Popuni s 2-3 test redaka
3. Idi u **Tools → Import B2B Partnera**
4. Uploadaj datoteku
5. ✅ Označi **"Test mod"**
6. Klikni **Započni Import**
7. Provjeri log da vidiš da li sve radi

### 3. Stvarni Import

Nakon što test prođe uspješno:

1. Pripremi produkcijsku datoteku
2. Uploadaj
3. ❌ Odznači "Test mod"
4. ✅ Označi "Pošalji password reset"
5. Klikni **Započni Import**

---

## 🔍 Troubleshooting

### "Class 'PhpOffice\PhpSpreadsheet\IOFactory' not found"

**Problem:** Dependencies nisu instalirani.

**Rješenje:**
```bash
cd /wp-content/plugins/b2b-partner-importer
composer install --no-dev
```

### "WooCommerce nije aktiviran"

**Problem:** WooCommerce plugin nije aktivan.

**Rješenje:** Idi u Plugins → Aktiviraj WooCommerce.

### "Permission denied" pri composer install

**Problem:** Nedostaju permisije.

**Rješenje:**
```bash
sudo chown -R www-data:www-data /wp-content/plugins/b2b-partner-importer
chmod -R 755 /wp-content/plugins/b2b-partner-importer
```

### Plugin se ne pojavljuje u listi

**Problem:** Plugin nije pravilno uploadan ili ima sintaksnu grešku.

**Rješenje:**
1. Provjeri da `b2b-partner-importer.php` postoji u root folderu
2. Provjeri PHP error log:
   ```bash
   tail -f /var/log/php-error.log
   ```

### "Fatal error: Maximum execution time exceeded"

**Problem:** Import traje predugo.

**Rješenje:** Povećaj `max_execution_time` u `php.ini`:
```ini
max_execution_time = 300
```

Ili dodaj u `.htaccess`:
```apache
php_value max_execution_time 300
```

---

## 📊 Provjera Instalacije

Nakon instalacije, provjeri sljedeće:

### 1. PHP Extensions

```bash
php -m | grep -E 'zip|xml|mbstring|gd'
```

Trebaju biti instalirani: `zip`, `xml`, `mbstring`, `gd`

### 2. Folder Permissions

```bash
ls -la /wp-content/plugins/b2b-partner-importer/
```

Owner bi trebao biti `www-data` (ili tvoj web server user).

### 3. Vendor Folder

```bash
ls /wp-content/plugins/b2b-partner-importer/includes/vendor/
```

Trebao bi sadržavati `phpoffice` folder.

---

## 🔄 Update Procedure

Kada novi update bude dostupan:

### Opcija 1: Manual Update

1. Deaktiviraj plugin
2. Obriši stari folder
3. Uploadaj novi
4. Pokreni `composer install --no-dev`
5. Aktiviraj plugin

### Opcija 2: Git Pull (za developere)

```bash
cd /wp-content/plugins/b2b-partner-importer
git pull origin main
composer install --no-dev
```

---

## 🆘 Dodatna Pomoć

Ako i dalje imaš problema:

1. **Provjeri PHP error log:** `/var/log/php-error.log`
2. **Provjeri WordPress debug:** Aktiviraj `WP_DEBUG` u `wp-config.php`
3. **Kontaktiraj support:** team@uncledev.info

---

## ✅ Checklist

Prije nego što kažeš "Instalacija je gotova", provjeri:

- [ ] WooCommerce je aktiviran
- [ ] Dependencies su instalirani (`includes/vendor` postoji)
- [ ] Plugin je aktiviran u WordPress Admin
- [ ] Admin stranica je dostupna (Tools → Import B2B Partnera)
- [ ] Test import radi
- [ ] Excel template se može preuzeti
- [ ] Komercijalisti CPT postoji i ima email polje

Ako je sve ✅, čestitamo! Plugin je spreman za korištenje! 🎉

---

**Verzija dokumenta:** 1.0.0  
**Zadnje ažurirano:** 30. Listopad 2025
