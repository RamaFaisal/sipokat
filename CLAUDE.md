# CLAUDE.md — Sipokat: Progress & Mentoring Document

> Rangkuman **kondisi proyek saat ini** dibanding requirement draft TA "Rancang Bangun Sistem Inventory
> Obat Berbasis Web dengan SPK Metode SAW pada Apotek Anugrah Husada" (Bab I–III), untuk bahan
> review/mentoring dosen pembimbing.
> Last updated: 2026-09-14 — setelah **revisi besar September 2026** (rencana lengkap dan alasan tiap
> keputusan ada di [docs/rencana-revisi-2026-09.md](docs/rencana-revisi-2026-09.md)).

---

## 1. Ringkasan Status

| Aspek | Status | Catatan |
|-------|--------|---------|
| Master Data (obat, PBF, satuan) | ✅ | Obat: 5 isian (nama, kategori, satuan jual, kemasan beli + isi, batas minimum). Kode `OBT-####` otomatis. Tanpa harga, dosis, foto, deskripsi (Bagian 1 rencana) |
| Kartu stok per batch + HPP | ✅ | `medicine_stocks` = ledger lapisan: baris D membawa batch/ED, baris C menunjuk lapisan asalnya. HPP rata-rata bergerak per obat (Bagian 4) |
| Procurement (PO → RO per faktur) | ✅ | PO = catatan internal per PBF setelah konfirmasi WA; satu RO = satu faktur; input dalam kemasan, tersimpan dalam satuan jual (Bagian 2–3) |
| Penjualan FEFO | ✅ | Alokasi otomatis dari batch ED terdekat, bisa memecah ke beberapa lapisan; harga ≥ HPP; qty ≤ stok tersedia; tanpa edit (hapus → buat ulang) (Bagian 5) |
| Stok Opname per batch | ✅ | Hitung fisik per lapisan; selisih → penyesuaian pada lapisan itu; jalur retur/pemusnahan obat kedaluwarsa (Bagian 6) |
| **SPK SAW (inti skripsi)** | ✅ | C1 rasio stok÷min, C2 permintaan/bulan, C3 ED batch terjauh yang bersisa, C4 HPP; skala hasil wawancara; peringkat padat; "Buat PO" dari ranking (Bagian 7) |
| Notifikasi stok & ED | ✅ | `sipokat:check-stock-and-expiry` harian 08:00 (Filament DB notification) |
| Dashboard & widget | ✅ | Top-10 SAW, stok kritis, PO terbuka, batch mendekati ED, grafik penjualan |
| Laporan | ✅ | Kartu stok per obat (per batch + HPP), Rekap penjualan/pembelian, Fast/slow moving — Excel & PDF |
| Data demo & data riil | ✅ | `SpkTestDataSeeder` (150 obat lewat jalur service) + template Excel 4 sheet & importer data riil (`sipokat:data-riil:*`) |
| Pengujian | ✅ | 84 tes Pest / 826 asersi, termasuk alur ujung-ke-ujung lewat halaman Filament |
| Roles & Permissions | ⏸️ | Ditangani peneliti via Filament Shield (D7). Permission di DB sudah bersih dari halaman yang dihapus |
| Deploy VPS (MySQL) + cron | 🔜 | E9 — lihat §8 |

---

## 2. Functional Requirements (Tabel 3.3 Proposal) vs Implementasi

| Kode | Requirement | Status | Implementasi |
|------|-------------|--------|--------------|
| F-01 | Login & autentikasi | ✅ | Filament panel `/admin`. `User` implements `FilamentUser` (wajib di luar `APP_ENV=local`) |
| F-02 | Kelola data obat | ✅ | `MedicineResource` + import Excel |
| F-03 | Catat obat masuk & keluar | ✅ | Masuk: RO → baris D per batch. Keluar: penjualan → baris C FEFO per lapisan. Semua lewat `StockMovementService` |
| F-04 | Notifikasi stok minimum & ED | ✅ | Stok tersedia < `min_stock`; batch bersisa dengan ED ≤ 90 hari |
| F-05 | Laporan inventory | ✅ | Kartu stok, Rekap, Moving, dashboard |
| F-06 | SAW prioritas restock | ✅ | `SawCalculationService` + halaman "Hitung Prioritas Restock" + terjadwal 06:00 |
| F-07 | Tampilan perangkingan | ✅ | Tabel ranking (Tingkat, Stok/Min, C1–C4, V), detail hitungan per obat, riwayat snapshot |

---

## 3. Aturan Domain yang Berlaku (ringkas)

Rincian dan alasannya: `docs/rencana-revisi-2026-09.md`. Ini yang harus dipatuhi kode baru.

**Master obat (M1–M12)**
- `unit_id` = **satuan jual = satuan kartu stok**. `pack_unit_id` + `pack_size` = kemasan beli dari PBF (1 Box = N satuan jual). Isi kemasan milik obat, bukan milik satuan.
- Nama unik (dinormalkan huruf besar; aplikasi, bukan indeks DB karena soft-delete). Kode `OBT-####` berurutan, tidak diubah.
- `min_stock` bawaan: Strip → 20, lainnya = `pack_size`; harus > 0.

**Kartu stok & HPP (S3, F1–F7, B5)**
- Semua angka di ledger dalam satuan jual. Konversi kemasan hanya di titik input (`PackLine`).
- Baris D (dari RO/opname) = **lapisan** dengan `batch_number`, `expired_date` (disimpan tanggal 1 bulan ED), `hpp` (harga beli per satuan jual), `hpp_avg` (HPP setelah baris itu). Baris C menunjuk `layer_stock_id`.
- Kedaluwarsa = `expired_date <= hari ini`. Stok **tersedia** = Σ sisa lapisan belum kedaluwarsa; stok **fisik** = Σ semua lapisan.
- HPP rata-rata bergerak, bilangan bulat dibulatkan ke atas: `(saldo × HPP + qty × harga) ÷ (saldo + qty)`. Dihitung ulang (`replayHpp`) tiap kali ledger obat berubah.
- Hapus dokumen (RO/penjualan/opname) → baris ledger **dihapus sungguhan** lalu HPP di-replay. RO yang lapisannya sudah terpakai tidak bisa dihapus/diubah qty-nya (R8).

**Pengadaan (R1–R15, P1–P12, Q0–Q9)**
- PO: per PBF, dibuat manual atau dari ranking SAW (jumlah bawaan ⌈min_stock ÷ isi⌉ kemasan, harga = harga beli terakhir). Status diturunkan dari penerimaan: pending / partial / received / closed (tutup manual, aksi massal).
- RO: satu faktur PBF = satu RO; `invoice_number` unik per PBF (aplikasi). Boleh tanpa PO. Dari PO: centang item → baris terisi otomatis; qty ≤ sisa PO (kelebihan = baris di luar PO). Setiap baris wajib batch + ED (bulan-tahun) > tanggal terima.
- PPN 11% (pengaturan umum) hanya untuk tampilan cetak; harga faktur sudah termasuk PPN.

**Penjualan (S1–S7)**
- Tanpa status/pembayaran/diskon. Harga jual ≥ HPP saat itu; qty ≤ stok tersedia. Alokasi FEFO otomatis, satu baris penjualan bisa menjadi beberapa baris C. Tidak ada edit.

**Opname (D1–D3)**
- Per obat: daftar lapisan bersisa dengan sisa sistem → isi fisik; selisih menjadi C/D pada lapisan itu. Batch belum tercatat → lapisan D baru (butuh HPP obat sudah ada).

**SAW (K0–K15)**
- Alternatif: obat aktif yang punya ≥ 1 baris kartu stok. Σ bobot aktif harus = 1,000 (divalidasi di service; CLI ikut).
- C1 = stok tersedia ÷ `min_stock` (2 desimal); C2 = Σ qty terjual dalam periode × 30 ÷ jumlah hari (inklusif); C3 = sisa hari ke ED batch **terjauh** yang masih bersisa (stok tersedia 0 → 0 hari); C4 = HPP.
- Skala 1–5 inklusif (tabel di §4). Normalisasi `min/X` untuk cost (C1, C3, C4), `X/max` untuk benefit (C2); skor 0 → R = 0. Vi dihitung presisi penuh, dibulatkan saat disimpan.
- Peringkat **padat** ("Tingkat"): Vi sama → tingkat sama; urutan tampil pakai tie-breaker rasio → permintaan → ED. Obat yang ada di PO terbuka diberi tanda "sudah dipesan".
- Periode: "Sampai" selalu hari ini; "Dari" bawaan 30 hari.

---

## 4. Skala Konversi (hasil wawancara, `SawCriteriaSeeder`)

| Skor | C1 Rasio stok÷min | C2 Permintaan (satuan jual/bln) | C3 Sisa ED (hari) | C4 HPP (Rp) |
|:---:|---|---|---|---|
| 1 | ≤ 1,00 | ≤ 10 | ≤ 90 | ≤ 2.000 |
| 2 | 1,01 – 2,00 | 11 – 30 | 91 – 180 | 2.001 – 10.000 |
| 3 | 2,01 – 3,50 | 31 – 65 | 181 – 365 | 10.001 – 50.000 |
| 4 | 3,51 – 5,00 | 66 – 100 | 366 – 730 | 50.001 – 100.000 |
| 5 | ≥ 5,01 | ≥ 101 | ≥ 731 | ≥ 100.001 |

Bobot tetap 0,30 / 0,30 / 0,20 / 0,20. C1 memakai rasio (ambang wawancara ÷ 20 = batas waspada
narasumber) supaya obat bersatuan berbeda dinilai adil terhadap batas minimumnya sendiri (K6).
Batasan yang tetap ditulis di Bab 1.4: skala C2 satu set untuk semua satuan.

---

## 5. Peta Kode

| Lapisan | Path | Peran |
|---|---|---|
| Service | `app/Services/StockMovementService.php` | Satu-satunya penulis ledger: `recordReceipt/reverseReceipt/syncReceipt`, `recordSale/reverseSale` (FEFO), `recordOpname/reverseOpname`, `replayHpp`, `refreshStockStatus` |
| Service | `app/Services/StockCardService.php` | Pembaca: `physicalStock`, `availableStock`, `layers`, `sellableLayers`, `currentHpp`, kartu stok berjalan |
| Service | `app/Services/SawCalculationService.php` | Pipeline SAW: alternatif → nilai mentah → skor → normalisasi → Vi → peringkat padat → snapshot |
| Service | `app/Services/RealDataImporter.php`, `app/Support/RealDataTemplate.php` | Template & importer data riil (Obat → SaldoAwal → Faktur → Penjualan) |
| Form | `app/Filament/Forms/PackLine.php` | Baris kemasan bersama PO & RO: satuan/isi/jumlah/harga → konversi ke satuan jual |
| Model | `MedicineStock` | Scope `layers()`, `withRemainingStock()`; accessor `remaining`; `isExpired()` |
| Model | `Order`, `ReceiveOrder`, `MedicineStockOpname` | Hook `deleting` → balikkan ledger lewat service |
| Halaman | `app/Filament/Pages/SawCalculation.php` | Hitung, tabel ranking, detail hitungan, aksi massal "Buat PO" |
| Perintah | `sipokat:recalculate-saw`, `sipokat:check-stock-and-expiry`, `sipokat:data-riil:template`, `sipokat:data-riil:import` | Jadwal: 06:00 & 08:00 (`routes/console.php`) |
| Migrasi revisi | `database/migrations/2026_09_13_00000{1..5}_*.php` | Master → lapisan & HPP → pengadaan → penjualan/opname FEFO (backfill lapisan) → hasil SAW |

Tes (`tests/Feature`): `MedicineMasterTest`, `HppMovingAverageTest` (tabel §4.3 rencana),
`FefoLayersTest`, `ProcurementTest`, `StockLedgerTest`, `StockMovementServiceTest`,
`SawCalculationTest` (contoh 5 alternatif), `SpkTestDataSeederTest`, `RealDataImportTest`,
`PanelPagesRenderTest`, `EndToEndFlowTest` (alur penuh lewat form Filament).

---

## 6. Catatan untuk Sidang

1. **Normalisasi baku**: skor searah nilai mentah, prioritas dibentuk `min/X` (cost) dan `X/max`
   (benefit). Contoh 5 alternatif di `SawCalculationTest`: A1 0,90 · A2 0,74 · A4 0,5467 · A5 0,54 · A3 0,42.
2. **C3 = batch terjauh yang bersisa**, bukan batch terdekat: dengan FEFO batch terdekat memang habis
   duluan; yang menentukan "kapan harus pesan lagi" adalah sisa umur stok terakhir. Obat berstok 0
   → 0 hari → skor 1 (paling mendesak), sehingga tidak ada obat kosong yang kehilangan kriteria.
3. **C4 = HPP rata-rata bergerak**, bukan harga master: harga beli memang berbeda antar PBF dan antar
   faktur (wawancara §4.1), dan HPP-lah yang benar-benar tertanam di stok.
4. **Peringkat padat** dipilih karena Vi kembar wajar terjadi dengan skala 1–5; angka urut 1,2,3 akan
   memberi kesan perbedaan yang tidak ada.
5. Konversi kemasan, HPP, FEFO, dan opname per batch adalah **prasyarat data** agar C1–C4 benar —
   bukan fitur tambahan penjualan. Topik tetap prioritas restock.
6. Apotek Winong hanya referensi internal peneliti — **tidak dicantumkan** di naskah.

---

## 7. Cara Uji Manual (demo)

```bash
php artisan migrate --seed                       # master + kriteria SAW
php artisan db:seed --class=SpkTestDataSeeder    # 150 obat demo (idempoten)
php artisan sipokat:recalculate-saw
php artisan sipokat:check-stock-and-expiry
```

Alur demo: Obat → (SPK) Hitung Prioritas → centang → Buat PO → Penerimaan (pilih PO, centang item,
isi batch/ED) → Penjualan (lihat pratinjau alokasi batch) → Kartu Stok (per batch + HPP) → Stok
Opname (fisik per batch) → Hitung Prioritas lagi → Laporan.

Data riil: `php artisan sipokat:data-riil:template` → isi 4 sheet → `php artisan
sipokat:data-riil:import berkas.xlsx --period-start=YYYY-MM-DD --dry-run` → tanpa `--dry-run`.

---

## 8. Deploy (E9) & yang ditangani peneliti

- VPS + MySQL 8: `.env` `DB_CONNECTION=mysql`, `APP_ENV=production`, `APP_TIMEZONE` sudah
  `Asia/Jakarta` di `config/app.php`. `php artisan migrate --force`, `php artisan db:seed --class=SawCriteriaSeeder`,
  `php artisan storage:link`, `php artisan optimize`.
- Cron: `* * * * * cd /path/sipokat && php artisan schedule:run >> /dev/null 2>&1` (cek `php artisan schedule:list`).
- Roles: `php artisan shield:generate --all` lalu susun Admin/Petugas/Pemilik di menu Roles (Tabel 3.2).
- Naskah: sinkronkan Bab 1.4, Bab III (Tabel 3.4–3.8, definisi C1–C4, algoritma FEFO/HPP), Bab IV
  dengan `docs/update-dari-wawancara.md` dan `docs/rencana-revisi-2026-09.md` Bagian 7.

---

> Catatan lokal (Windows/Laragon): `php` tidak ada di PATH; pakai
> `C:\laragon\bin\php\php-8.4.25-nts-Win32-vs17-x64\php.exe`. `pdo_sqlite` tidak aktif di php.ini, jadi tes
> dijalankan dengan `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/pestphp/pest/bin/pest`
> (bukan `artisan test`). `artisan tinker` tersangkut prompt interaktif — jangan dipakai.
> Riwayat rencana sebelum revisi (Section 12–14 lama: rak obat dihapus, kategori 2 golongan, satuan
> kemasan) tetap berlaku dan sudah tercermin di kode; dokumentasinya ada di git history.
