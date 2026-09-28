# Deploy Sipokat ke VPS (MySQL 8) catatan E9

Dicek 2026-09-14: rantai migrasi dari nol + `--seed` + `SpkTestDataSeeder` 
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
# .env: APP_ENV=production APP_DEBUG=false APP_URL=https://... DB_* sesuai §2 QUEUE_CONNECTION=sync
php artisan migrate --force --seed        # peran, akun awal, master, 127 obat riil, faktur RO, contoh PO, simulasi penjualan
# shield:generate sudah dipanggil RoleSeeder; jalankan manual hanya bila menambah resource/halaman baru
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache
```

Akun awal ada di `database/seeders/UserSeeder.php` satu per peran (Admin / Petugas Apotek / Pemilik
Apotek) **ganti password** lewat menu Users setelah login pertama.

Peran `admin` / `petugas` / `pemilik` beserta matriks izinnya (Tabel 3.2) dibuat `RoleSeeder`, jadi tidak
perlu disusun manual di menu **Roles**. Basis data yang masih memakai nama lama
(`super_admin` / `staff` / `manajer`) diganti namanya oleh migrasi
`2026_09_24_000001_rename_roles_to_thesis_terms` penting untuk `mysqldump` dari mesin lokal (R2).

`DatabaseSeeder` sekarang memuat semua menu sekaligus lewat `--seed` di atas tidak perlu lagi
memanggil seeder data riil satu-satu: `MedicineDataSeeder` (127 obat, dari
`database/seeders/data/master-data-obat.csv` yang ikut git, **bukan** dari
`storage/app/import/*` yang digitignore), `FakturNpmSeeder` (14 faktur NPM asli, digeser ke
bulan **saat seeder dijalankan**), `PurchaseOrderSeeder` (contoh PO), `SimulasiPenjualanSeeder`
(penjualan simulasi bertanda `[SIMULASI]` supaya C2 SAW tidak nol **bukan** data riil, harus
dinyatakan begitu di naskah). Semua idempoten: mengulang `db:seed` tidak melipatgandakan data.

**Penting untuk konsistensi Bab IV**: karena `FakturNpmSeeder` menggeser tanggal faktur ke bulan
saat *dijalankan*, hasil seed di server pada tanggal lain akan berbeda dari yang dibekukan di
lokal. Kalau angka Bab IV sudah dibekukan (lihat `docs/rencana-sidang-2026-10.md` T2), **pindahkan
data lewat `mysqldump` dari lokal**, bukan `--seed` ulang di server `--seed` di atas untuk deploy
awal/percobaan sebelum angka dibekukan, atau untuk instalasi baru di luar keperluan sidang.

Data demo sintetis (150 obat contoh, terpisah dari data riil) hanya bila diperlukan:
`php artisan db:seed --class=SpkTestDataSeeder --force`.
Data riil dari berkas Excel lain (bukan CSV bawaan di atas):
`sipokat:data-riil:template` → isi → `sipokat:data-riil:import berkas.xlsx --period-start=YYYY-MM-DD --dry-run` → tanpa `--dry-run`. Peringkat SAW ikut terbarui sendiri saat dashboard dibuka.

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

## 5. Cron (wajib tanpa ini notifikasi harian tidak jalan)

```
* * * * * cd /var/www/sipokat && php artisan schedule:run >> /dev/null 2>&1
```

Verifikasi: `php artisan schedule:list` → satu baris, `0 8 * * * sipokat:check-stock-and-expiry`. (SAW tidak dijadwalkan: peringkat dihitung saat halaman dibuka.)

**Antrean: tidak perlu worker.** `.env.example` memakai `QUEUE_CONNECTION=sync`, jadi impor dan ekspor
Excel Filament berjalan langsung di dalam request. Volumenya kecil (127 obat) sehingga selesai dalam
hitungan detik. Kalau suatu saat diubah ke `database`, wajib memasang worker
(`php artisan queue:work` lewat supervisor atau systemd) tanpa itu impor akan menggantung di status
antre tanpa pesan galat.

## 6. Rilis berikutnya

```bash
git pull && composer install --no-dev --optimize-autoloader && npm ci && npm run build
php artisan migrate --force && php artisan optimize
```

Backup sebelum migrasi: `mysqldump -u sipokat -p sipokat > backup-$(date +%F).sql`.
