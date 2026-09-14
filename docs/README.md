# Sipokat

Sistem Inventory Obat Berbasis Web dengan Sistem Pendukung Keputusan metode **SAW** (Simple Additive
Weighting) untuk prioritas restock obat — studi kasus Apotek Anugrah Husada, Demak.

## Tentang Aplikasi

Sipokat mencatat stok obat dari hulu ke hilir — pemesanan ke PBF, penerimaan per faktur, kartu stok
per batch, penjualan FEFO, sampai stok opname — lalu memberi rekomendasi obat mana yang paling perlu
di-restock berdasarkan SAW atas empat kriteria: rasio stok terhadap batas minimum, permintaan per
bulan, sisa kedaluwarsa, dan harga pokok persediaan.

### Fitur Utama

- **Master Data**: obat (satuan jual, kemasan beli + isi, batas minimum; kode `OBT-####` otomatis), PBF, satuan. Kategori dikunci 4 golongan (Obat Bebas, Obat Bebas Terbatas, Obat Keras, Alat Kesehatan) sesuai data apotek.
- **Pengadaan**: Purchase Order per PBF (bisa dibuat dari ranking SAW) dan Receive Order **satu per faktur** dengan input dalam kemasan yang otomatis dikonversi ke satuan jual; status PO (pending/sebagian/lengkap/ditutup) turun dari penerimaan.
- **Kartu stok per batch**: setiap penerimaan menjadi lapisan dengan nomor batch, ED, dan harga beli; **HPP rata-rata bergerak** dihitung per obat.
- **Penjualan FEFO**: stok keluar otomatis dari batch dengan ED terdekat (bisa memecah ke beberapa batch); harga jual ≥ HPP; jumlah ≤ stok tersedia. Salah input → hapus dan buat ulang.
- **Stok Opname per batch**: hitung fisik tiap batch, selisih menjadi penyesuaian pada batch itu — sekaligus jalur retur/pemusnahan obat kedaluwarsa.
- **SPK SAW**: bobot dan skala konversi 1–5 dapat diubah admin (Σ bobot harus 1,000), perhitungan manual & terjadwal, peringkat padat ("Tingkat"), tanda "sudah dipesan", aksi massal **Buat PO** dari ranking, riwayat snapshot, dan rincian perhitungan V per obat.
- **Notifikasi** harian stok di bawah batas minimum dan batch yang mendekati kedaluwarsa.
- **Dashboard**: Top-10 prioritas restock, stok kritis, PO terbuka, batch mendekati ED, grafik penjualan.
- **Laporan**: kartu stok (per batch + HPP), rekap penjualan/pembelian, fast/slow/dead moving — Excel & PDF.
- **Data riil**: template Excel 4 sheet dan importer (`sipokat:data-riil:*`).

### Kriteria SAW

| Kode | Kriteria | Tipe | Bobot | Nilai mentah |
|------|----------|------|-------|--------------|
| C1 | Rasio stok | cost | 0,300 | Stok tersedia (belum kedaluwarsa) ÷ batas minimum obat |
| C2 | Permintaan per bulan | benefit | 0,300 | Σ terjual dalam periode, diproyeksikan ke 30 hari |
| C3 | Sisa kedaluwarsa (hari) | cost | 0,200 | ED batch **terjauh** yang masih bersisa; stok 0 → 0 hari |
| C4 | Harga pokok (HPP) | cost | 0,200 | HPP rata-rata bergerak per satuan jual |

Skor 1–5 (skala hasil wawancara, inklusif) dinormalisasi `min/X` untuk cost dan `X/max` untuk
benefit, lalu V = Σ (bobot × R). Rincian: `docs/rencana-revisi-2026-09.md` Bagian 7.

## Teknologi

- PHP 8.2 ke atas (target deploy PHP 8.4), Laravel 12
- Filament 4 (panel admin) + Filament Shield + Spatie Laravel Settings
- **MySQL 8** (produksi); SQLite in-memory untuk pengujian
- Tailwind CSS 4 dan Vite 7
- PhpSpreadsheet (Excel) dan Laravel DomPDF (PDF)

## Instalasi (Development)

Prasyarat: PHP 8.2+, Composer, Node.js, MySQL 8.

```bash
# 1. Dependency
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
# .env: DB_CONNECTION=mysql, DB_DATABASE=sipokat, DB_USERNAME, DB_PASSWORD

# 3. Migrasi + master data + kriteria SAW
php artisan migrate --seed

# 4. (Opsional) data demo 150 obat, lalu hitung SAW
php artisan db:seed --class=SpkTestDataSeeder
php artisan sipokat:recalculate-saw

# 5. Asset frontend
npm run build      # atau: npm run dev

php artisan serve  # panel: http://localhost:8000/admin
```

Akun awal dari `UserSeeder`; setelah itu buat role lewat menu **Roles** (Filament Shield):
`php artisan shield:generate --all` bila permission belum ada.

## Data Riil Apotek

```bash
php artisan sipokat:data-riil:template            # storage/app/import/template-data-riil.xlsx
# isi sheet Obat → SaldoAwal → Faktur → Penjualan (baris 2 = petunjuk, baris contoh dihapus)
php artisan sipokat:data-riil:import berkas.xlsx --period-start=2026-08-01 --dry-run
php artisan sipokat:data-riil:import berkas.xlsx --period-start=2026-08-01
php artisan sipokat:recalculate-saw
```

Impor transaksional: satu baris salah → tidak ada yang tersimpan, semua masalah dicetak.

## Command Terjadwal

| Jadwal | Perintah | Fungsi |
|--------|----------|--------|
| 06:00 | `sipokat:recalculate-saw` | Snapshot SAW harian (dipakai widget dashboard) |
| 08:00 | `sipokat:check-stock-and-expiry` | Notifikasi stok minimum & batch mendekati ED |

Cron di server:

```
* * * * * cd /path/to/sipokat && php artisan schedule:run >> /dev/null 2>&1
```

Cek dengan `php artisan schedule:list`.

## Pengujian

```bash
composer test
# atau
php artisan test
```

Suite Pest memakai SQLite in-memory (`phpunit.xml`). Kalau `pdo_sqlite` tidak aktif di php.ini:
`php -d extension=pdo_sqlite -d extension=sqlite3 vendor/pestphp/pest/bin/pest`.

## Deployment (VPS + MySQL)

Repo menyertakan `nixpacks.toml` (PHP 8.4 + Composer). Di produksi pastikan:

- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, koneksi MySQL
- `php artisan migrate --force` dan `php artisan db:seed --class=SawCriteriaSeeder --force` saat rilis pertama
- `npm run build`, `php artisan optimize`
- Cron `schedule:run` aktif
- Zona waktu aplikasi sudah `Asia/Jakarta` (`config/app.php`); samakan zona waktu MySQL/server

## Lisensi

Dibangun di atas framework Laravel yang berlisensi [MIT](https://opensource.org/licenses/MIT).
