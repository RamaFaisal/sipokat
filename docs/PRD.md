# Sipokat — Product Requirements Document (PRD)

> **Sistem Inventory Obat Berbasis Web dengan Sistem Pendukung Keputusan Metode Simple Additive Weighting (SAW) untuk Apotek Anugrah Husada**

| Field | Value |
|-------|-------|
| Versi | 2.0 (Revisi September 2026: batch/FEFO, HPP, PO–RO per faktur, SAW rasio & HPP) |
| Status | ✅ Implemented — rincian keputusan di `docs/rencana-revisi-2026-09.md` |
| Last updated | 2026-09-14 |
| Author | Rama Faisal Muntaha (A11.2022.14082) |
| Stakeholder | Apotek Anugrah Husada (Demak), Universitas Dian Nuswantoro |

---

## 1. Executive Summary

Sipokat adalah sistem manajemen inventaris obat berbasis web untuk Apotek Anugrah Husada yang menggantikan pencatatan manual (buku besar + Microsoft Excel) dengan platform terintegrasi. Sistem ini menggabungkan:

1. **Manajemen Inventaris Modern** — kartu stok per batch (lapisan D/C), konversi kemasan beli → satuan jual, HPP rata-rata bergerak, penjualan FEFO, opname per batch, notifikasi otomatis.
2. **Sistem Pendukung Keputusan (SPK) berbasis Simple Additive Weighting (SAW)** — rekomendasi prioritas restock obat berdasarkan 4 kriteria (rasio stok terhadap batas minimum, permintaan, sisa kedaluwarsa, HPP) dengan bobot dan skala yang dapat dikonfigurasi, dan pembuatan PO langsung dari ranking.
3. **Laporan & Audit Trail** — laporan rekap penjualan/pembelian, analisis fast/slow moving, riwayat snapshot SAW, breakdown perhitungan V_i per obat.

Sistem dibangun dengan Laravel 12, Filament 4, MySQL, dan Tailwind CSS — sesuai stack yang ditentukan di proposal Tugas Akhir.

---

## 2. Problem Statement

Apotek Anugrah Husada di Demak menghadapi 3 masalah operasional utama dalam pengelolaan obat:

| Masalah | Dampak |
|---------|--------|
| Pencatatan stok manual via buku + Excel | Ketidaksesuaian data fisik dengan catatan, human error, sulit di-audit |
| Tidak ada pemantauan otomatis stok minimum & kedaluwarsa | Keterlambatan restock (stock out), risiko obat kedaluwarsa tidak terpantau, kerugian finansial |
| Prioritas restock berdasarkan perkiraan subjektif | Kekurangan obat yang sering dipakai, penumpukan obat yang jarang terpakai, modal nyangkut |

---

## 3. Goals & Success Metrics

### Tujuan Utama
1. Membangun sistem inventory web yang **terstruktur dan akurat** untuk mengelola data obat, stok, masuk, dan keluar.
2. Mengembangkan **pemantauan otomatis** stok minimum + masa kedaluwarsa untuk mengurangi stock out dan obat kedaluwarsa.
3. Menerapkan **metode SAW** sebagai dasar rekomendasi prioritas restock yang objektif dan terukur.

### Metrik Keberhasilan
| Metrik | Target |
|--------|--------|
| Akurasi data stok | Stok di sistem ≡ stok fisik (selisih 0 setelah opname) |
| Kecepatan deteksi stok menipis | < 1 hari (scheduled scan harian 08:00) |
| Kecepatan perhitungan SAW | < 5 detik untuk 150+ obat |
| Transparansi keputusan restock | 100% rekomendasi dapat ditelusuri via snapshot historis + breakdown V_i per obat |
| Waktu generate laporan rekap | < 3 detik untuk periode 30 hari (153 obat, 250+ transaksi) |

---

## 4. User Personas

| Persona | Peran | Tugas Utama | Akses |
|---------|-------|-------------|-------|
| **Admin (Apoteker Penanggung Jawab)** | Pengelola master & SAW | Setup data obat, satuan, PBF; konfigurasi bobot & skala SAW; review semua data | Full access semua modul |
| **Petugas Apotek (Staf Gudang)** | Operasional harian | Catat obat masuk (RO) dengan ED, input batch, catat penjualan (Orders), opname stok | Modul transaksi + view inventory |
| **Pemilik / Manajer Apotek** | Pengambil keputusan | Lihat dashboard, laporan rekap, hasil SAW, fast/slow moving — putuskan restock | View dashboard + laporan + SPK output |

> **Catatan**: Infrastruktur role-based access (Spatie Permission + Filament Shield) sudah terpasang. Setup roles dilakukan terpisah via Filament Shield.

---

## 5. Scope

### In Scope ✅
- Master data: obat (satuan jual, kemasan beli + isi, batas minimum), PBF, satuan; kategori dikunci 4 golongan (Obat Bebas, Obat Bebas Terbatas, Obat Keras, Alat Kesehatan)
- Procurement: PO per PBF (manual atau dari ranking SAW) + RO **satu per faktur** dengan input kemasan → konversi otomatis, penerimaan bertahap, batch + ED wajib
- Inventory: kartu stok per batch via `MedicineStock` (baris D = lapisan, baris C menunjuk lapisan), HPP rata-rata bergerak, stock opname per batch, status otomatis
- Sales: penjualan dengan alokasi **FEFO** otomatis, harga ≥ HPP, qty ≤ stok tersedia; tanpa edit (hapus → buat ulang)
- **SPK SAW**: 4 kriteria (bobot & scale_rules editable, Σ bobot = 1,000), perhitungan manual + scheduled, peringkat padat, "Buat PO" massal dari ranking
- **Data riil**: template Excel 4 sheet + importer transaksional (`sipokat:data-riil:*`)
- **Notifikasi**: scheduled daily check stok min + ED ≤ 90 hari → Filament database notifications
- **Dashboard**: 5 widget (SAW Top 10, Low Stock, Pending PO, Expiring, Sales Summary 30 hari)
- **Laporan**: rekap penjualan/pembelian, fast/slow/dead-stock analysis, kartu stok per obat
- **Audit trail SAW**: riwayat snapshot historis + breakdown V_i per obat
- **Import/Export**: Import obat & supplier via Excel/CSV template, export Receive Order
- **Pengaturan Umum**: Nama aplikasi, email kontak, telepon, website, tarif PPN (Spatie Laravel Settings)
- Authentication: Laravel auth + Filament login panel
- **Authorization Policies**: 9 policy classes untuk resource-level access control

### Out of Scope ❌
- Resep elektronik (e-prescription)
- Manajemen keuangan / akuntansi lengkap (cash flow, P&L, BEP)
- Penjualan online / e-commerce
- Integrasi dengan BPJS Kesehatan / sistem rujukan
- Sensor fisik / IoT (RFID rak, scanner barcode otomatis)
- Machine Learning / prediksi demand kompleks
- POS hardware integration (cash drawer, struk printer)
- Multi-cabang / multi-apotek

---

## 6. Functional Requirements

### F-01 — Login & Autentikasi
**As a** user, **I want to** login dengan email + password, **so that** saya bisa mengakses data sesuai role.
- Form login Filament panel dengan validasi
- Session-based auth (Laravel default)
- Logout dari header panel

### F-02 — Kelola Data Obat (CRUD)
**As an** admin, **I want to** mengelola master obat lengkap dengan kode auto-generate.
- Field: kode (auto `OBT-####`, tidak diubah), nama (uppercase, memuat kekuatan & merek seperti di faktur), kategori, **satuan jual** (= satuan kartu stok), **kemasan pembelian + isi** (1 Box = N satuan jual), min_stock (bawaan Strip 20, lainnya = isi kemasan), status
- Tidak ada harga di master: harga beli dari RO, HPP dihitung; tidak ada dosis/foto/deskripsi
- Validasi unik berdasarkan nama (dinormalkan) — di aplikasi, karena soft-delete
- Import via Excel/CSV template (`MedicineImporter`) dengan download template otomatis (`ImporterTemplate`)
- Import supplier via Excel/CSV (`SupplierImporter`)
- Soft delete

### F-03 — Catat Transaksi Obat Masuk & Keluar
**As a** petugas apotek, **I want to** mencatat penerimaan dari supplier & penjualan ke pelanggan dengan tracking otomatis.

**Pemesanan (Purchase Order):**
- Auto-number `PO{YYYYMMDD}-XXXX`; per PBF; dibuat manual atau dari ranking SAW (jumlah bawaan ⌈min_stock ÷ isi⌉ kemasan, harga perkiraan = harga beli terakhir)
- Baris: obat, kemasan, isi, jumlah kemasan, harga/kemasan → tersimpan juga dalam satuan jual
- Status turunan dari penerimaan: pending / partial / received; **closed** manual (aksi massal "Tutup PO"); edit/hapus hanya saat pending

**Obat Masuk (Receive Order):**
- Auto-number `RO{YYYYMMDD}-XXXX`; **satu RO = satu faktur PBF**; `invoice_number` unik per PBF
- Boleh tanpa PO. Dari PO: centang item PO → baris terisi otomatis dengan sisa; qty ≤ sisa PO (kelebihan = baris di luar PO)
- Per baris: obat, kemasan + isi + jumlah + harga/kemasan (dikonversi ke satuan jual), **batch_number**, **ED bulan-tahun** (disimpan tanggal 1; harus > tanggal terima)
- `StockMovementService::recordReceipt` → satu baris D (lapisan) per baris RO dengan batch/ED/harga; HPP obat di-replay
- Edit: header bebas; qty/harga hanya bila lapisannya belum terpakai; hapus ditolak bila lapisan sudah terpakai
- Cetak RO (PDF) menampilkan DPP/PPN (PPN 11% dari Pengaturan Umum, tampilan saja)

**Obat Keluar (Order/Penjualan):**
- Auto-number `ORD-{YYYYMMDD}XXXX`
- Validasi: `qty ≤ StockCardService::availableStock()`; `price ≥ HPP saat itu`
- `recordSale` mengalokasikan **FEFO** ke lapisan belum kedaluwarsa (ED terdekat dulu; satu baris jual bisa jadi beberapa baris C dengan `layer_stock_id`)
- Tanpa status/pembayaran/diskon; **tanpa edit** — hapus (baris C dihapus sungguhan, stok kembali ke lapisan asal) lalu buat ulang

### F-04 — Notifikasi Stok Minimum & Kedaluwarsa
**As an** admin, **I want to** mendapat peringatan dini setiap hari saat ada obat menipis atau mendekati ED.
- Command `sipokat:check-stock-and-expiry` scheduled daily 08:00 (jam buka apotek)
- Segarkan `stock_status` semua obat (dari stok tersedia vs `min_stock`), lalu scan `empty` / `almost_empty`
- Scan **lapisan yang masih bersisa** (`MedicineStock::layers()->withRemainingStock()`) dengan `expired_date` ≤ 90 hari (configurable via `--expiry-days`)
- Kirim Filament Database Notification ke semua user dengan action "Lihat di Dashboard"
- Muncul di bell icon header panel + halaman notifikasi

### F-05 — Laporan Inventory Otomatis
**As a** pemilik/manajer, **I want to** generate laporan operasional dalam berbagai sudut pandang.

**Laporan tersedia:**
1. **Kartu Stok per Obat** (`MedicineStockDetail`) — riwayat D/C per obat dengan kolom batch/ED, HPP baris, dan HPP rata-rata berjalan; filter year/month/supplier + export Excel.
2. **Rekap Penjualan & Pembelian** (`LaporanRekap`) — filter periode + tipe (Penjualan / Pembelian / Keduanya), summary cards (total + jumlah transaksi + margin kotor), tabel agregasi per obat (kode, nama, kategori, qty/nilai beli & jual, margin), export Excel berformat dengan total row.
3. **Fast/Slow Moving / Dead Stock** (`LaporanMoving`) — analisis demand bulanan dengan 3 kategori: Fast Moving (top demand), Slow Moving (< 20/bln per Tabel 3.6 proposal), Dead Stock (tanpa transaksi). Multi-sheet Excel export.
4. **Dashboard Widgets** — 5 widget real-time: Top 10 SAW, Low Stock, Pending PO, Expiring Batches, Sales Summary 30 hari.
5. **Export Receive Order** — Filament Exporter (`ReceiveOrderExporter`) untuk data penerimaan barang.

### F-06 — SAW untuk Rekomendasi Prioritas Restock
**As an** admin, **I want to** menjalankan perhitungan SAW kapan saja dan melihat ranking semua obat.
- Page **"Hitung Prioritas Restock"** dengan periode permintaan: "Sampai" selalu hari ini (terkunci), "Dari" bawaan 30 hari ke belakang
- Tombol "Hitung Sekarang" dengan modal konfirmasi → `SawCalculationService::execute()`
- Alternatif = obat aktif dengan ≥ 1 baris kartu stok; yang belum punya riwayat dikecualikan dan dihitung di `excluded_count`
- Snapshot tersimpan ke `saw_calculations` dengan `trigger_type` + `criteria_snapshot` (freeze config)
- Pre-flight check di service (berlaku juga untuk CLI): tolak jika `Σ weight kriteria aktif ≠ 1.000` atau ada kriteria C1–C4 yang tidak aktif
- Scheduled command `sipokat:recalculate-saw` daily 06:00 (hasil dipakai dashboard widget)
- Aksi massal **"Buat PO dari yang dicentang"** → PO per PBF berisi obat terpilih; obat yang sudah ada di PO terbuka ditandai "sudah dipesan"

### F-07 — Tampilan Hasil Perangkingan Obat
**As a** pemilik, **I want to** melihat ranking semua obat + breakdown perhitungan untuk audit/verifikasi.
- Table paginated 25/halaman, urut `sort_order` (V desc, tie-breaker rasio → permintaan → ED)
- Kolom: **Tingkat** (peringkat padat: V sama → tingkat sama), kode, nama, Stok / Min (C1 rasio), permintaan/bln (C2), sisa ED batch terjauh (C3), HPP (C4), **Nilai Prioritas (V_i)**, penanda "sudah dipesan"
- Widget dashboard "Top 10 Prioritas Restock (SAW)" sync dengan snapshot terakhir
- **ViewAction "Detail Hitungan"** per row: modal breakdown V_i step-by-step (matriks per kriteria + rumus `V = W₁×R₁ + W₂×R₂ + W₃×R₃ + W₄×R₄`) match contoh Bab 3.4.4 proposal
- **Resource "Riwayat Perhitungan"** untuk audit trail: list semua snapshot historis dengan filter trigger_type + date range, view detail per snapshot

### F-08 — Pengaturan Umum Aplikasi
**As an** admin, **I want to** mengatur informasi dasar aplikasi dari panel admin.
- Page `ManageGeneralSettings` via Spatie Laravel Settings
- Field: nama aplikasi (brand name dinamis), email kontak, nomor telepon, website, tarif PPN (bawaan 11%, dipakai untuk tampilan DPP/PPN pada cetak RO)
- Brand name di panel header otomatis sinkron dengan setting `app_name`

---

## 7. Non-Functional Requirements

| Aspek | Requirement | Implementasi |
|-------|-------------|--------------|
| **Security** | Autentikasi, hak akses by role, perlindungan data | Laravel auth + Spatie Permission + Filament Shield + 9 Policy classes |
| **Performance** | SAW < 5 detik untuk 150 obat; laporan rekap < 3 detik | Eager loading, indexed queries, transaction batching, SPA mode |
| **Usability** | Antarmuka mudah dipakai tanpa pelatihan khusus | Filament panel + custom theme Awin (Emerald) + font Poppins + bahasa Indonesia konsisten |
| **Reliability** | Data konsisten, tidak ada race condition | DB transactions, soft deletes, validasi server-side |
| **Maintainability** | Code terstruktur, testable | Service layer (SawCalculationService, StockCardService), schema classes terpisah (Schemas/ + Tables/ per resource), Pest test framework |
| **Auditability** | Setiap perubahan ter-track | `created_by`, `received_by`, `calculated_by` di tabel transaksional + `criteria_snapshot` di SAW |

---

## 8. Technical Architecture

### Stack
- **Backend**: Laravel 12 (PHP 8.2+)
- **Admin Panel**: Filament 4.0
- **Database**: MySQL 8 (InnoDB, utf8mb4)
- **Frontend**: Tailwind CSS 4 + Vite 7, Livewire 3 (via Filament)
- **Theme**: `resma/filament-awin-theme` v1.1 (primary color: Emerald, font: Poppins)
- **Settings**: Spatie Laravel Settings (`filament/spatie-laravel-settings-plugin` v4)
- **Auth**: Filament Shield v4.1 + Spatie Permission
- **Excel/PDF**: PhpOffice/PhpSpreadsheet v5.7, Barryvdh/laravel-dompdf
- **Testing**: Pest v4 + pest-plugin-laravel v4
- **Dev Tools**: Laravel Pail, Laravel Pint, Concurrently (dev server)

### Folder Structure (key directories)
```
app/
├── Console/Commands/      sipokat:check-stock-and-expiry, sipokat:recalculate-saw
├── Filament/
│   ├── Exports/           ReceiveOrderExporter
│   ├── Imports/           MedicineImporter, SupplierImporter
│   ├── Pages/             SawCalculation, LaporanRekap, LaporanMoving, MedicineStockDetail, ManageGeneralSettings
│   ├── Resources/         13 resources (masing-masing punya subdirectory Pages/, Schemas/, Tables/)
│   │   ├── Medicines/           MedicineResource + Pages + Schemas + Tables + RelationManagers
│   │   ├── Suppliers/           SupplierResource + Pages + Schemas + Tables
│   │   ├── Units/               UnitResource + Pages + Schemas + Tables
│   │   ├── MedicineCategories/  MedicineCategoriesResource + Pages + Schemas + Tables
│   │   ├── MedicineRacks/       MedicineRackResource + Pages + Schemas + Tables
│   │   ├── MedicineStocks/      MedicineStockResource + Pages + Tables
│   │   ├── MedicineStockOpnames/ MedicineStockOpnameResource + Pages + Schemas + Tables
│   │   ├── PurchaseOrders/      PurchaseOrderResource + Pages + Schemas + Tables
│   │   ├── ReceiveOrders/       ReceiveOrderResource + Pages + Schemas + Tables
│   │   ├── Orders/              OrderResource + Pages + Schemas + Tables
│   │   ├── SawCriterias/        SawCriteriaResource + Pages + Schemas + Tables
│   │   ├── SawCalculations/     SawCalculationResource + Pages + Tables
│   │   └── Users/               UserResource + Pages + Schemas + Tables
│   └── Widgets/           SawTop10, LowStock, PendingPO, Expiring, SalesSummary (5 widget)
├── Http/Controllers/      (minimal, logic di Filament)
├── Models/                18 model (lihat Data Model di bawah)
├── Policies/              9 policy classes (Medicine, Supplier, Unit, MedicineCategories, MedicineRack, PurchaseOrder, ReceiveOrder, User, Role)
├── Settings/              GeneralSettings (app_name, contact_email, contact_phone, website)
├── Support/               ImporterTemplate (helper generate XLSX/CSV template download)
└── Services/              StockCardService, SawCalculationService
database/
├── migrations/            34 migration (master, transaksi, SAW, settings, permissions, imports/exports)
├── seeders/               UserSeeder, MasterDataSeeder, SawCriteriaSeeder, SpkTestDataSeeder, DatabaseSeeder
└── settings/              GeneralSettings migration
```

### Data Model (17 model)
```
medicines (code OBT-####, unit_id = satuan jual, pack_unit_id + pack_size = kemasan beli, min_stock)
   ├─ purchase_order_items (pack_unit_id, pack_size, pack_qty, price/satuan jual, qty)
   ├─ receive_order_items  (jejak kemasan + batch_number, expired_date)
   ├─ order_items (qty, price)
   ├─ medicine_stocks — ledger lapisan:
   │     D: batch_number, expired_date, hpp (harga beli/satuan jual), hpp_avg, receive_order_item_id
   │     C: layer_stock_id → lapisan yang dikonsumsi (FEFO), hpp = HPP saat itu
   └─ saw_calculation_results (c1_stock, c1_min_stock, c1..c4 raw/score/norm, preference_value, rank padat, sort_order)

suppliers ── purchase_orders (po_number, po_date, status_receive_order: pending|partial|received|closed)
           └─ receive_orders (receive_order_number, invoice_number unik per PBF, receive_date, purchase_order_id nullable)

orders (order_code, order_date, grand_total, note) ── order_items ── medicine_stocks (C, bisa >1 per item)

medicine_stock_opnames ── medicine_stock_opname_items (layer_stock_id | batch baru) ── medicine_stocks (C/D pada lapisan)

saw_criteria ── saw_calculations (period, trigger_type, criteria_snapshot, total_alternatives, excluded_count) ── saw_calculation_results

medicine_categories (4 golongan), units (master lookup)   ·   general_settings (app_name, kontak, ppn_rate)

users ── (FK calculated_by, created_by, received_by di tabel transaksional)
```

**Daftar 17 Model:**
`Medicine`, `MedicineCategories`, `MedicineStock`, `MedicineStockOpname`, `MedicineStockOpnameItem`, `Order`, `OrderItem`, `PurchaseOrder`, `PurchaseOrderItem`, `ReceiveOrder`, `ReceiveOrderItem`, `SawCalculation`, `SawCalculationResult`, `SawCriteria`, `Supplier`, `Unit`, `User`

**Services:** `StockMovementService` (satu-satunya penulis ledger: receipt/sale/opname + reverse, `replayHpp`), `StockCardService` (pembaca: fisik, tersedia, lapisan, HPP), `SawCalculationService`, `RealDataImporter`.

### Panel Configuration
- **Panel ID**: `admin` (path: `/admin`)
- **Theme**: FilamentAwinTheme (primary: Emerald)
- **Font**: Poppins (Google Fonts)
- **Dark Mode**: Disabled
- **SPA Mode**: Enabled
- **Database Notifications**: Enabled (bell icon header)
- **Brand Name**: Dinamis dari `GeneralSettings::app_name` (default: "SIPOKAT")
- **Brand Logo**: `assets/medicineLogo(1).png`
- **Navigation Groups**: Inventory → Penjualan → Pembelian → Master Data → SPK Restock → Laporan → Manajemen Pengguna

---

## 9. SPK SAW — Detail Spesifikasi

### Kriteria (sesuai Tabel 3.4 proposal)
| Kode | Nama | Tipe | Bobot | Sumber Data |
|------|------|------|-------|-------------|
| C1 | Rasio Stok | cost | 0.300 | `availableStock ÷ min_stock` (2 desimal); stok tersedia = Σ sisa lapisan belum kedaluwarsa |
| C2 | Permintaan/bulan | benefit | 0.300 | Σ `OrderItem.qty` dalam periode × 30 ÷ jumlah hari (inklusif) |
| C3 | Sisa Kedaluwarsa (hari) | cost | 0.200 | Hari ke ED lapisan **terjauh** yang masih bersisa; stok tersedia 0 → 0 |
| C4 | Harga Pokok (HPP) | cost | 0.200 | `StockCardService::currentHpp()` — rata-rata bergerak, bulat ke atas |
| **Total** | | | **1.000** | |

Skala 1–5 (inklusif, hasil wawancara): lihat CLAUDE.md §4 / `SawCriteriaSeeder`.

### Pipeline Perhitungan
```
Input: period_start (bawaan 30 hari lalu), period_end = hari ini, trigger_type, user_id
  │
  ├─ activeCriteria(): C1–C4 wajib aktif, Σ bobot = 1.000 (RuntimeException bila tidak)
  │
  ├─ alternatives(): obat aktif dengan ≥ 1 baris kartu stok (sisanya → excluded_count)
  │
  ├─ getRawValue() per obat × kriteria → ['raw', 'meta'] (C1 menyimpan stok & min_stock)
  │
  ├─ buildDecisionMatrix() — raw → skor 1-5 via SawCriteria::convertToScore()
  │     (rentang inklusif, pembulatan 2 desimal; di luar rentang → 0)
  │
  ├─ normalize() — cost: R = min/X (min dari skor > 0), benefit: R = X/max; skor 0 → R = 0
  │     presisi penuh; pembulatan hanya saat disimpan
  │
  ├─ calculatePreference() — V_i = Σ (W_j × R_ij)
  │
  ├─ rank() — peringkat PADAT (V sama → tingkat sama); sort_order = V desc, rasio asc, permintaan desc, ED asc
  │
  └─ persist (DB transaction): saw_calculations + saw_calculation_results
```

### Konfigurasi Editable Admin
- **Bobot kriteria** — `SawCriteriaResource`; validasi Σ bobot aktif = 1.000 tepat saat simpan; `min ≤ max` per rentang; step 0,01
- **Skala konversi 1-5** — `saw_criteria.scale_rules` JSON, editable via Repeater
- Keempat kriteria wajib aktif (toggle aktif/non-aktif dihapus)

### Output
- **Page Hitung Prioritas Restock** — tabel ranking (Tingkat, Stok/Min, C2–C4, V), ViewAction Detail, penanda "sudah dipesan", aksi massal Buat PO
- **Widget Dashboard Top 10** — 10 baris teratas snapshot terakhir
- **Resource Riwayat Perhitungan** — list snapshot historis dengan filter, view detail per snapshot
- **Modal Detail V_i** — matriks per kriteria (raw dengan rincian C1 = stok ÷ min, skor, Min/Max kolom, R dengan label min/X atau X/max) + rumus step-by-step

### Catatan Resolusi Inkonsistensi Proposal
Tabel 3.10 draf proposal memuat konversi terbalik dari Tabel 3.5–3.8. Sistem memakai **normalisasi baku**: skor searah nilai mentah, prioritas dari `min/X` (cost) dan `X/max` (benefit). Contoh 5 alternatif (`SawCalculationTest`): A1 0,90 · A2 0,74 · A4 0,5467 · A5 0,54 · A3 0,42. Bab 3.4.4 naskah ditulis ulang mengikuti ini; pembahasan T1–T11 di `IMPROVEMENT.md` (ditutup).

---

## 10. Key User Flows

### Flow 1: Restock Decision (Pemilik)
```
1. Login admin → Dashboard
2. Cek Widget "Top 10 Prioritas Restock (SAW)" — lihat obat dengan nilai prioritas tertinggi
3. (Opsional) Buka Page "Hitung Prioritas Restock" → klik "Hitung Sekarang" untuk recalc terbaru
4. Klik ViewAction "Detail Hitungan" pada obat top 1 → modal tampilkan breakdown V_i
5. Konfirmasi ketersediaan ke sales PBF (WA/telepon), lalu centang obat → "Buat PO dari yang dicentang" → pilih PBF & tanggal
6. Sesuaikan jumlah di halaman PO bila perlu; obat itu kini bertanda "sudah dipesan" di ranking
```

### Flow 2: Penerimaan Barang per Faktur (Petugas)
```
1. Login petugas → Penerimaan → Create; isi PBF, nomor faktur, tanggal terima
2. (Opsional) pilih PO → centang item yang tercetak di faktur ini → baris terisi sisa PO dalam kemasan
3. Per baris: kemasan, isi, jumlah kemasan, harga/kemasan (pratinjau konversi ke satuan jual), batch, ED bulan-tahun
4. Cocokkan "Total RO" dengan total faktur → Save
5. StockMovementService: satu lapisan D per baris, HPP di-replay; status PO → sebagian/lengkap
6. Faktur berikutnya untuk PO yang sama → RO baru (satu RO = satu faktur)
```

### Flow 3: Penjualan FEFO (Petugas)
```
1. Login petugas → Penjualan → Create
2. Tambah item: pilih obat, jumlah (satuan jual), harga jual (petunjuk HPP ditampilkan)
3. Pratinjau alokasi: "5 dari batch B1 (ED 10-2026), 2 dari batch B2 (ED 03-2027)"
4. Validasi: qty ≤ stok tersedia; harga ≥ HPP
5. Save → baris C per lapisan (FEFO) → stok berkurang; stock_status diperbarui
6. Salah input → hapus penjualan (stok kembali ke batch asal) → buat ulang
```

### Flow 4: Konfigurasi Bobot SAW (Admin)
```
1. Login admin → SPK Restock → Kriteria SAW
2. Edit salah satu kriteria (mis. C1)
3. Modifikasi bobot atau scale_rules (Repeater; min ≤ max)
4. Save → ditolak bila Σ bobot ≠ 1.000
5. Hitung Prioritas Restock → snapshot baru memakai konfigurasi itu (criteria_snapshot)
```

### Flow 6: Stok Opname per Batch (Petugas)
```
1. Stok Opname → Create → pilih obat → daftar batch bersisa muncul dengan sisa sistem
2. Isi jumlah fisik per batch (batch kedaluwarsa yang dimusnahkan/diretur: 0)
3. Batch yang belum tercatat → "Batch baru" (batch, ED, jumlah)
4. Save → selisih menjadi C/D pada batch itu; tanpa selisih → dibatalkan
```

### Flow 5: Generate Laporan (Pemilik)
```
1. Login → Laporan → Rekap Penjualan & Pembelian
2. Set periode + tipe → klik "Tampilkan Laporan"
3. Lihat summary cards + tabel agregasi per obat (sorted by nilai jual)
4. Klik "Export Excel" → download file dengan format profesional
```

---

## 11. Reporting & Analytics

| Laporan | Path | Output |
|---------|------|--------|
| Kartu Stok per Obat | `app/Filament/Pages/MedicineStockDetail.php` | Detail D/C per batch (batch/ED, HPP baris, HPP rata-rata berjalan) + saldo berjalan, export Excel |
| Rekap Penjualan & Pembelian | `app/Filament/Pages/LaporanRekap.php` | Summary cards + tabel agregasi 50 top obat + total, export Excel |
| Fast / Slow / Dead Stock | `app/Filament/Pages/LaporanMoving.php` | 3 kategori dengan filter top N, multi-sheet Excel |
| Export Receive Order | `app/Filament/Exports/ReceiveOrderExporter.php` | Export data RO (nomor RO, faktur, PO, PBF, tanggal, total) |
| Riwayat Snapshot SAW | `app/Filament/Resources/SawCalculations/` | Audit trail snapshot + ranking lengkap per snapshot |
| Dashboard Widgets | `app/Filament/Widgets/` | 5 widget real-time (Top 10 SAW, LowStock, PendingPO, Expiring, SalesSummary) |

---

## 12. Notifications & Scheduling

| Job | Schedule | Behavior |
|-----|----------|----------|
| `sipokat:recalculate-saw` | Daily 06:00 | Run SAW dengan period 30 hari, trigger_type=scheduled, hasil dipakai dashboard widget |
| `sipokat:check-stock-and-expiry` | Daily 08:00 | Scan low-stock + expiring batches ≤ 90 hari, kirim Filament DB notification ke semua user |

**Deployment**: cron host harus setup `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`.

---

## 13. Demo Data

Seeder `SpkTestDataSeeder` (idempotent, lewat `StockMovementService` — jalur yang sama dengan UI) generate:
- 150 obat dengan profil satuan/kemasan/min_stock bervariasi, kode `OBT-####`
- 1 PBF "PT. Distributor SPK Test"; faktur ≤ 14 baris (seperti faktur asli)
- Rencana per obat untuk semua bracket C1–C4: ~35% punya dua batch, ~8% sisa kedaluwarsa, ~10% stok tersedia 0
- Penjualan 30 hari terakhir (FEFO), HPP terbentuk dari harga faktur
- Idempoten via berkas penanda `storage/app/spk-test-data.json`

`DatabaseSeeder` (`migrate --seed`) hanya memuat akun, master data, dan kriteria SAW — data demo
sengaja terpisah. Data riil apotek dimuat lewat `sipokat:data-riil:template` → `sipokat:data-riil:import`.

Run:
```bash
php artisan db:seed --class=SpkTestDataSeeder
php artisan sipokat:recalculate-saw
```

---

## 14. Future Enhancements (Out of Current Scope)

Untuk versi berikutnya (post-sidang TA):
- Multi-cabang apotek
- Resep elektronik (e-prescription) terintegrasi
- Prediksi demand dengan time series (ARIMA / Prophet)
- POS hardware integration (scanner barcode, struk printer)
- Mobile app (Petugas) untuk opname & scan barcode
- Integrasi BPJS Kesehatan
- Machine Learning untuk auto-tuning bobot SAW berdasarkan historis
- Dashboard analytics lanjutan (cohort analysis, ABC analysis, EOQ)
- Notifikasi push (FCM / WhatsApp) untuk pemilik saat critical stock
- Dark mode toggle (infrastruktur sudah disabled, bisa diaktifkan)
- Global search (sudah disabled, bisa diaktifkan via panel config)
- Profile page untuk user (sudah disabled via `->profile(false)`)

---

## 15. Open Items (Manual, Non-Code)

Item yang masih perlu dikerjakan **manual oleh peneliti** (tidak bisa di-generate dari sistem):

| # | Item | Action | Status |
|---|------|--------|--------|
| 1 | UML Diagrams (Use Case, Activity, Sequence, ERD) | Gambar ulang sesuai skema revisi (lapisan batch, PO–RO per faktur) | ⏳ |
| 2 | Naskah Bab 1.4, Bab III (Tabel 3.4–3.8, definisi C1–C4, FEFO/HPP), Bab 3.4.4, Bab IV | Ikuti `docs/update-dari-wawancara.md` §3 & `docs/rencana-revisi-2026-09.md` Bagian 7; Winong tidak dicantumkan | ⏳ |
| 3 | Data riil apotek (kartu stok, faktur) | Isi template `sipokat:data-riil:template`, impor dengan `--dry-run` dulu | ⏳ |
| 4 | Deploy VPS MySQL + cron | Lihat README "Deployment" | ⏳ |
| 5 | Setup Roles & Permissions via Filament Shield | `shield:generate --all`, susun Admin / Petugas / Pemilik (Tabel 3.2) | ⏳ |
| 6 | Smoke test browser end-to-end | Alur sudah diuji otomatis (`EndToEndFlowTest`); ulangi manual di browser sebelum sidang | ⏳ |
| 7 | ~~Write Pest tests~~ | ✅ 84 tes / 826 asersi | ✅ |

---

## 16. Glossary

| Term | Definition |
|------|------------|
| **SAW** | Simple Additive Weighting — metode SPK Multi-Attribute Decision Making berbasis penjumlahan nilai terbobot |
| **V_i (Nilai Prioritas)** | Nilai preferensi alternatif ke-i, hasil `Σ (W_j × R_ij)` |
| **W_j** | Bobot kriteria ke-j |
| **R_ij** | Nilai normalisasi alternatif ke-i pada kriteria ke-j |
| **Kartu Stok** | Buku besar stok obat — di Sipokat di-track dinamis via `MedicineStock` D/C entries |
| **FEFO** | First Expired First Out — asumsi keluar stok dari batch ED terdekat dulu |
| **ED** | Expired Date / Tanggal Kedaluwarsa |
| **HPP** | Harga Pokok Penjualan |
| **PO** | Purchase Order — pesanan ke supplier |
| **RO** | Receive Order — penerimaan barang dari supplier |
| **Opname** | Penyesuaian stok fisik vs sistem |

---

## 17. Acceptance Criteria Summary

Sistem dianggap memenuhi requirement TA jika:

- [x] F-01 sampai F-08 semua terimplementasi
- [x] Algoritma SAW persis match Bab 3.4.3 dengan normalisasi baku (min/X cost, X/max benefit), Σ bobot = 1,000 divalidasi di service
- [x] 4 kriteria + skala hasil wawancara (C1 rasio stok÷min, C4 HPP) — Tabel 3.4–3.8 naskah mengikuti sistem
- [x] Kartu stok per batch, HPP rata-rata bergerak, penjualan FEFO, opname per batch — prasyarat agar C1/C3/C4 benar
- [x] PO per PBF (juga dari ranking SAW) dan RO satu per faktur dengan konversi kemasan
- [x] Notifikasi otomatis berjalan (terdaftar di scheduler)
- [x] Laporan rekap + fast/slow moving + kartu stok, export Excel & PDF
- [x] Audit trail SAW dengan snapshot historis + breakdown V_i
- [x] Tech stack: Laravel 12 + Filament 4 + MySQL 8 + Tailwind CSS 4; zona waktu Asia/Jakarta
- [x] Data demo 150 obat (jalur service) + template & importer data riil
- [x] Import obat & supplier via Excel/CSV; export Receive Order
- [x] Pengaturan umum aplikasi (brand name, kontak, PPN)
- [x] Policy classes untuk authorization; `User implements FilamentUser`
- [x] 84 tes Pest (826 asersi) termasuk alur ujung-ke-ujung lewat halaman Filament

**Status: ✅ ALL ACCEPTANCE CRITERIA MET (updated 2026-09-14)**

---

**Dokumen terkait:**
- [CLAUDE.md](CLAUDE.md) — progress & mentoring document dengan detail teknis
- [README.md](README.md) — dokumentasi project setup & deployment
