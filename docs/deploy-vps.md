# Deploy Sipokat ke VPS (MySQL 8) — catatan E9

Dicek 2026-09-14: rantai migrasi dari nol + `--seed` + `SpkTestDataSeeder` + `sipokat:recalculate-saw`
+ `sipokat:check-stock-and-expiry` + `sipokat:data-riil:import` berjalan bersih pada database MySQL kosong.

## 1. Server

- Ubuntu 22.04/24.04, PHP 8.4 (`php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml php8.4-zip php8.4-gd php8.4-intl php8.4-curl php8.4-bcmath`), Composer, Node 20, Nginx, MySQL 8.
- Zona waktu server: `timedatectl set-timezone Asia/Jakarta`. MySQL: `SET GLOBAL time_zone = '+07:00'` (atau `default-time-zone` di my.cnf). Aplikasi sudah `Asia/Jakarta` di `config/app.php`.

## 2. Database

```sql
CREATE DATABASE sipokat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sipokat'@'localhost' IDENTIFIED BY '<password kuat>';
GRANT ALL PRIVILEGES ON sipokat.* TO 'sipokat'@'localhost';
```

## 3. Aplikasi

```bash
cd /var/www && git clone <repo> sipokat && cd sipokat
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
# .env: APP_ENV=production APP_DEBUG=false APP_URL=https://... DB_* sesuai §2
php artisan migrate --force --seed        # akun awal + master + kriteria SAW (bukan data demo)
php artisan shield:generate --all          # permission untuk semua resource/halaman/widget
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache
```

Akun awal ada di `database/seeders/UserSeeder.php` — **ganti password** lewat menu Users setelah login pertama.
Susun role Admin / Petugas / Pemilik di menu **Roles** (Tabel 3.2).

Data demo hanya bila diperlukan untuk peragaan: `php artisan db:seed --class=SpkTestDataSeeder --force`.
Faktur asli PBF (14 faktur NPM, digeser ke bulan berjalan) setelah master riil dimuat: `php artisan db:seed --class=FakturNpmSeeder --force`.
Data riil: `sipokat:data-riil:template` → isi → `sipokat:data-riil:import berkas.xlsx --period-start=YYYY-MM-DD --dry-run` → tanpa `--dry-run` → `sipokat:recalculate-saw`.

## 4. Nginx (ringkas)

```
server {
    server_name sipokat.example.com;
    root /var/www/sipokat/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php8.4-fpm.sock; }
    location ~ /\.(?!well-known).* { deny all; }
    client_max_body_size 20m;
}
```

HTTPS: `certbot --nginx`.

## 5. Cron (wajib — tanpa ini SAW terjadwal & notifikasi tidak jalan)

```
* * * * * cd /var/www/sipokat && php artisan schedule:run >> /dev/null 2>&1
```

Verifikasi: `php artisan schedule:list` → `0 6 * * * sipokat:recalculate-saw`, `0 8 * * * sipokat:check-stock-and-expiry`.

## 6. Rilis berikutnya

```bash
git pull && composer install --no-dev --optimize-autoloader && npm ci && npm run build
php artisan migrate --force && php artisan optimize
```

Backup sebelum migrasi: `mysqldump -u sipokat -p sipokat > backup-$(date +%F).sql`.
