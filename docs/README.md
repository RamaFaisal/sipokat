# Sipokat

Sistem Inventory Obat berbasis web dengan Sistem Pendukung Keputusan (SPK) metode Simple Additive Weighting (SAW) untuk merekomendasikan prioritas restock obat pada Apotek Anugrah Husada.

Aplikasi ini dibuat sebagai implementasi Tugas Akhir dengan judul "Rancang Bangun Sistem Inventory Obat Berbasis Web dengan SPK Metode SAW pada Apotek Anugrah Husada".

## Tentang Aplikasi

Sipokat membantu apotek mengelola stok obat dari hulu ke hilir, mulai dari pengadaan, pencatatan obat masuk dan keluar, kartu stok, sampai stock opname. Selain itu aplikasi memberi rekomendasi obat mana yang paling perlu di-restock berdasarkan perhitungan SAW atas empat kriteria: sisa stok, permintaan, tanggal kedaluwarsa, dan harga beli.

### Fitur Utama

- **Master Data**: kelola obat, supplier, satuan, kategori, dan rak.
- **Pengadaan**: Purchase Order dan Receive Order dengan penerimaan bertahap, penomoran otomatis, tracking status, serta pencatatan nomor batch, tanggal produksi, dan tanggal kedaluwarsa per item.
- **Inventory**: kartu stok berbasis entri debit dan kredit, status stok yang ter-update otomatis, dan stock opname untuk penyesuaian.
- **Penjualan**: pengurangan stok otomatis dengan validasi ketersediaan, dijalankan transaksional, dan disusun ulang saat transaksi diedit.
- **SPK SAW**: kriteria dan bobot bisa diubah lewat antarmuka, aturan konversi skala 1 sampai 5 tersimpan sebagai JSON, perhitungan bisa manual maupun terjadwal, ada riwayat snapshot untuk audit, dan rincian perhitungan nilai prioritas per obat.
- **Notifikasi**: peringatan harian untuk stok menipis dan obat yang mendekati kedaluwarsa.
- **Dashboard**: lima widget yaitu Top 10 Prioritas Restock, Low Stock, Pending PO, Expiring, dan ringkasan penjualan.
- **Laporan**: rekap penjualan dan pembelian serta analisis fast, slow, dan dead moving, lengkap dengan export Excel.

### Kriteria SAW

| Kode | Kriteria | Tipe | Bobot | Sumber Data |
|------|----------|------|-------|-------------|
| C1 | Stok | cost | 0.300 | `Medicine::currentStock()` |
| C2 | Permintaan per bulan | benefit | 0.300 | Agregasi `OrderItem.qty` per periode, diproyeksi ke 30 hari |
| C3 | Sisa kedaluwarsa (hari) | cost | 0.200 | `Medicine::nearestExpiryDays()` dengan pendekatan FEFO |
| C4 | Harga beli | cost | 0.200 | `Medicine.purchase_price` |

Total bobot harus sama dengan 1.000 dan divalidasi di antarmuka. Bobot maupun aturan konversi skala bisa diubah admin tanpa menyentuh kode.

## Teknologi

- PHP 8.2 ke atas (target deploy PHP 8.4)
- Laravel 12
- Filament 4 untuk panel admin, dengan Filament Shield dan Spatie Laravel Settings
- PostgreSQL
- Tailwind CSS 4 dan Vite 7 untuk frontend
- PhpSpreadsheet untuk export Excel dan Laravel DomPDF untuk PDF
- Pest 4 untuk pengujian

## Instalasi (Development)

Prasyarat: PHP 8.2 atau lebih baru, Composer, Node.js, dan PostgreSQL.

```bash
# 1. Install dependency
composer install
npm install

# 2. Siapkan environment
cp .env.example .env
php artisan key:generate
# sesuaikan koneksi database PostgreSQL di .env

# 3. Migrasi dan seed
php artisan migrate
php artisan db:seed

# 4. Build asset frontend
npm run build
```

Menjalankan aplikasi sekaligus (server, queue, dan vite):

```bash
composer run dev
```

Atau secara manual:

```bash
php artisan serve
npm run dev
```

Panel admin bisa diakses di `/admin`.

## Command Terjadwal

Penjadwalan diatur di `routes/console.php`:

| Command | Jadwal | Fungsi |
|---------|--------|--------|
| `sipokat:recalculate-saw` | Setiap hari 06:00 | Menghitung ulang SAW dan menyediakan data untuk widget dashboard |
| `sipokat:check-stock-and-expiry` | Setiap hari 08:00 | Mengirim notifikasi stok menipis dan obat mendekati kedaluwarsa |

Agar berjalan otomatis di server, tambahkan cron berikut:

```
* * * * * cd /path/to/sipokat && php artisan schedule:run >> /dev/null 2>&1
```

Untuk melihat daftar jadwal, jalankan `php artisan schedule:list`.

## Pengujian

```bash
composer test
# atau
php artisan test
```

## Deployment

Repo sudah menyertakan `nixpacks.toml` (PHP 8.4 dan Composer) untuk deploy berbasis Nixpacks. Beberapa hal yang perlu dipastikan di lingkungan produksi:

- Koneksi PostgreSQL sudah dikonfigurasi di `.env`
- Menjalankan `php artisan migrate --force` saat rilis
- Menjalankan `npm run build` untuk asset produksi
- Cron `schedule:run` aktif (lihat bagian Command Terjadwal)

## Lisensi

Dibangun di atas framework Laravel yang berlisensi [MIT](https://opensource.org/licenses/MIT).
