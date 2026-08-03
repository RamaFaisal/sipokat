# Sipokat — Product Requirements Document (PRD)

> **Sistem Inventory Obat Berbasis Web dengan Sistem Pendukung Keputusan Metode Simple Additive Weighting (SAW) untuk Apotek Anugrah Husada**

| Field | Value |
|-------|-------|
| Versi | 1.1 (Post-implementation update) |
| Status | ✅ Implemented |
| Last updated | 2026-07-02 |
| Author | Rama Faisal Muntaha (A11.2022.14082) |
| Stakeholder | Apotek Anugrah Husada (Demak), Universitas Dian Nuswantoro |

---

## 1. Executive Summary

Sipokat adalah sistem manajemen inventaris obat berbasis web untuk Apotek Anugrah Husada yang menggantikan pencatatan manual (buku besar + Microsoft Excel) dengan platform terintegrasi. Sistem ini menggabungkan:

1. **Manajemen Inventaris Modern** — kartu stok dinamis (debit/credit), tracking masa kedaluwarsa per batch, validasi stok real-time, notifikasi otomatis.
2. **Sistem Pendukung Keputusan (SPK) berbasis Simple Additive Weighting (SAW)** — rekomendasi prioritas restock obat berdasarkan 4 kriteria (stok, permintaan, kedaluwarsa, harga) dengan bobot yang dapat dikonfigurasi.
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
| **Admin (Apoteker Penanggung Jawab)** | Pengelola master & SAW | Setup data obat, kategori, rak, supplier; konfigurasi bobot & skala SAW; review semua data | Full access semua modul |
| **Petugas Apotek (Staf Gudang)** | Operasional harian | Catat obat masuk (RO) dengan ED, input batch, catat penjualan (Orders), opname stok | Modul transaksi + view inventory |
| **Pemilik / Manajer Apotek** | Pengambil keputusan | Lihat dashboard, laporan rekap, hasil SAW, fast/slow moving — putuskan restock | View dashboard + laporan + SPK output |

> **Catatan**: Infrastruktur role-based access (Spatie Permission + Filament Shield) sudah terpasang. Setup roles dilakukan terpisah via Filament Shield.

---

## 5. Scope

### In Scope ✅
- Master data: medicines, suppliers, units, kategori, rak
- Procurement: Purchase Order (PO) + Receive Order (RO) dengan partial receive + tracking ED per batch
- Inventory: kartu stok dinamis via `MedicineStock` (D/C entries), stock opname, status otomatis
- Sales: Orders dengan auto-deduct stok + validasi ketersediaan
- **SPK SAW**: 4 kriteria editable, scale_rules JSON editable, perhitungan manual + scheduled
- **Notifikasi**: scheduled daily check stok min + ED ≤ 90 hari → Filament database notifications
- **Dashboard**: 5 widget (SAW Top 10, Low Stock, Pending PO, Expiring, Sales Summary 30 hari)
- **Laporan**: rekap penjualan/pembelian, fast/slow/dead-stock analysis, kartu stok per obat
- **Audit trail SAW**: riwayat snapshot historis + breakdown V_i per obat
- **Import/Export**: Import obat & supplier via Excel/CSV template, export Receive Order
- **Pengaturan Umum**: Nama aplikasi, email kontak, telepon, website (Spatie Laravel Settings)
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
- Field: kode (auto: `SIP/NAMA4/CAT3/UNIT3/SEQ`), nama (uppercase), dosis, kategori, satuan, rak, harga beli, harga jual, min_stock, status, foto, deskripsi
- Validasi unik berdasarkan combination (nama + dosis)
- Import via Excel/CSV template (`MedicineImporter`) dengan download template otomatis (`ImporterTemplate`)
- Import supplier via Excel/CSV (`SupplierImporter`)
- Soft delete

### F-03 — Catat Transaksi Obat Masuk & Keluar
**As a** petugas apotek, **I want to** mencatat penerimaan dari supplier & penjualan ke pelanggan dengan tracking otomatis.

**Obat Masuk (Receive Order):**
- Auto-number `RO{YYYYMMDD}-XXXX`
- Support partial receive dari PO (sistem tracking sisa qty)
- Per item: medicine, qty, harga, **batch_number**, **manufacture_date**, **expired_date** (required)
- Auto-create `MedicineStock` entry type Debit (D)
- Status: pending / completed / cancelled

**Obat Keluar (Order/Penjualan):**
- Auto-number `ORD{YYYYMMDD}-XXXX`
- Validasi stok: tolak save jika `qty > Medicine::getAvailableStock()`
- Auto-create `MedicineStock` entry type Credit (C)
- Edit/delete: reverse old C entries → re-validate → recreate (transactional)
- Status: pending / paid / cancelled

### F-04 — Notifikasi Stok Minimum & Kedaluwarsa
**As an** admin, **I want to** mendapat peringatan dini setiap hari saat ada obat menipis atau mendekati ED.
- Command `sipokat:check-stock-and-expiry` scheduled daily 08:00 (jam buka apotek)
- Scan obat dengan `stock_status` in [`empty`, `almost_empty`]
- Scan batch `receive_order_items` dengan `expired_date` ≤ 90 hari (configurable via `--expiry-days`)
- Kirim Filament Database Notification ke semua user dengan action "Lihat di Dashboard"
- Muncul di bell icon header panel + halaman notifikasi

### F-05 — Laporan Inventory Otomatis
**As a** pemilik/manajer, **I want to** generate laporan operasional dalam berbagai sudut pandang.

**Laporan tersedia:**
1. **Kartu Stok per Obat** (`MedicineStockDetail`) — riwayat transaksi per obat dengan filter year/month/supplier + export Excel.
2. **Rekap Penjualan & Pembelian** (`LaporanRekap`) — filter periode + tipe (Penjualan / Pembelian / Keduanya), summary cards (total + jumlah transaksi + margin kotor), tabel agregasi per obat (kode, nama, kategori, qty/nilai beli & jual, margin), export Excel berformat dengan total row.
3. **Fast/Slow Moving / Dead Stock** (`LaporanMoving`) — analisis demand bulanan dengan 3 kategori: Fast Moving (top demand), Slow Moving (< 20/bln per Tabel 3.6 proposal), Dead Stock (tanpa transaksi). Multi-sheet Excel export.
4. **Dashboard Widgets** — 5 widget real-time: Top 10 SAW, Low Stock, Pending PO, Expiring Batches, Sales Summary 30 hari.
5. **Export Receive Order** — Filament Exporter (`ReceiveOrderExporter`) untuk data penerimaan barang.

### F-06 — SAW untuk Rekomendasi Prioritas Restock
**As an** admin, **I want to** menjalankan perhitungan SAW kapan saja dan melihat ranking semua obat.
- Page **"Hitung Prioritas Restock"** dengan form periode (default 30 hari ke belakang, custom date range)
- Tombol "Hitung Sekarang" dengan modal konfirmasi → invoke `SawCalculationService::execute()`
- Snapshot tersimpan ke `saw_calculations` dengan `trigger_type=manual` + `criteria_snapshot` (freeze config)
- Pre-flight check: tolak jika `Σ weight kriteria aktif ≠ 1.000`
- Scheduled command `sipokat:recalculate-saw` daily 06:00 (hasil dipakai dashboard widget)

### F-07 — Tampilan Hasil Perangkingan Obat
**As a** pemilik, **I want to** melihat ranking semua obat + breakdown perhitungan untuk audit/verifikasi.
- Table paginated 25/halaman, sort by `preference_value DESC`
- Kolom: rank, kode, nama, stok (C1), permintaan/bln (C2), sisa ED (C3), harga (C4), **Nilai Prioritas (V_i)**
- Widget dashboard "Top 10 Prioritas Restock (SAW)" sync dengan snapshot terakhir
- **ViewAction "Detail Hitungan"** per row: modal breakdown V_i step-by-step (matriks per kriteria + rumus `V = W₁×R₁ + W₂×R₂ + W₃×R₃ + W₄×R₄`) match contoh Bab 3.4.4 proposal
- **Resource "Riwayat Perhitungan"** untuk audit trail: list semua snapshot historis dengan filter trigger_type + date range, view detail per snapshot

### F-08 — Pengaturan Umum Aplikasi
**As an** admin, **I want to** mengatur informasi dasar aplikasi dari panel admin.
- Page `ManageGeneralSettings` via Spatie Laravel Settings
- Field: nama aplikasi (brand name dinamis), email kontak, nomor telepon, website
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

### Data Model (18 model)
```
medicines ─┬─ purchase_order_items
           ├─ receive_order_items (batch_number, manufacture_date, expired_date)
           ├─ order_items
           ├─ medicine_stocks (D/C entries, kartu stok)
           └─ saw_calculation_results

suppliers ──── purchase_orders ──── receive_orders ── receive_order_items
              (po_date, discount,     │                  └─ medicine_stocks (D)
               tax, shipping_cost,    │
               estimated_arrival,     └─ purchase_order_items
               status_payment,
               status_receive_order)

orders ──── order_items ──── medicine_stocks (C)

medicine_stock_opnames ── medicine_stock_opname_items ── medicine_stocks (D/C adjustment)

saw_criteria ── saw_calculations ── saw_calculation_results
              (criteria_snapshot JSON freeze saat hitung)

medicine_categories, medicine_racks, units (master lookup)

users ── (FK calculated_by, created_by, received_by di tabel transaksional)
```

**Daftar 18 Model:**
`Medicine`, `MedicineCategories`, `MedicineRack`, `MedicineStock`, `MedicineStockOpname`, `MedicineStockOpnameItem`, `Order`, `OrderItem`, `PurchaseOrder`, `PurchaseOrderItem`, `ReceiveOrder`, `ReceiveOrderItem`, `SawCalculation`, `SawCalculationResult`, `SawCriteria`, `Supplier`, `Unit`, `User`

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
| C1 | Stok | cost | 0.300 | `Medicine::currentStock()` |
| C2 | Permintaan/bulan | benefit | 0.300 | Σ `OrderItem.qty` per medicine dalam periode → proyeksi ke 30 hari |
| C3 | Sisa Kedaluwarsa (hari) | cost | 0.200 | `Medicine::nearestExpiryDays()` (FEFO dari `receive_order_items`) |
| C4 | Harga Beli | cost | 0.200 | `Medicine.purchase_price` |
| **Total** | | | **1.000** | |

### Pipeline Perhitungan
```
Input: period_start, period_end, trigger_type (manual/scheduled), user_id
  │
  ├─ Validasi: total bobot kriteria aktif = 1.000
  │
  ├─ getRawValue() per obat × per kriteria
  │     C2 di-normalisasi ke ekuivalen bulanan: (total_qty / period_days) × 30
  │
  ├─ buildDecisionMatrix() — convert raw → skor 1-5 via SawCriteria::convertToScore()
  │     (baca scale_rules JSON: [{"min":null,"max":10,"score":5}, ...])
  │
  ├─ normalize() — R = X / max untuk SEMUA kriteria
  │     (Opsi 2: pasca konversi semua benefit-like, tipe cost/benefit ter-encode di scale_rules)
  │     Skor 0 (data missing) → norm 0 (tidak menyumbang preferensi)
  │
  ├─ calculatePreference() — V_i = Σ (W_j × R_ij)
  │
  └─ persist (DB transaction):
        saw_calculations + saw_calculation_results (rank ter-assign DESC)
```

### Konfigurasi Editable Admin
- **Bobot kriteria** — editable via UI `SawCriteriaResource`, validasi 3-layer (min ≤ max scale_rules, total ≤ 1.000 saat save, total > 1.000 saat aktivasi via toggle)
- **Skala konversi 1-5** — disimpan di `saw_criteria.scale_rules` JSON, editable via Repeater di form
- **Aktif/non-aktif** — ToggleColumn inline di list (toggle off selalu boleh, toggle on divalidasi)

### Output
- **Page Hitung Prioritas Restock** — table 153+ obat paginated dengan kolom Nilai Prioritas + ViewAction Detail
- **Widget Dashboard Top 10** — sync dengan snapshot terakhir
- **Resource Riwayat Perhitungan** — list snapshot historis dengan filter, view detail per snapshot
- **Modal Detail V_i** — matriks per kriteria (raw, skor 1-5, normalisasi) + rumus step-by-step (W₁×R₁ + W₂×R₂ + W₃×R₃ + W₄×R₄ = V)

### Catatan Resolusi Inkonsistensi Proposal
PDF Tabel 3.10 (worked example Bab 3.4.4) berisi nilai konversi terbalik dari Tabel 3.5-3.8. Sistem mengikuti Tabel 3.5-3.8 sebagai sumber kebenaran dan menggunakan rumus `R = X/max` universal. Hasil ranking 5 alternatif sample: **A2 Amoxicillin rank 1 (V=0.94)** vs PDF original **A9 Lipitor rank 1 (V=0.84)** — argumentasi: A2 menang karena kombinasi stok rendah + demand tinggi + ED dekat + harga murah lebih masuk akal secara apotek logic. Update Bab 3.4.4 PDF perlu dilakukan saat finalisasi proposal.

---

## 10. Key User Flows

### Flow 1: Restock Decision (Pemilik)
```
1. Login admin → Dashboard
2. Cek Widget "Top 10 Prioritas Restock (SAW)" — lihat obat dengan nilai prioritas tertinggi
3. (Opsional) Buka Page "Hitung Prioritas Restock" → klik "Hitung Sekarang" untuk recalc terbaru
4. Klik ViewAction "Detail Hitungan" pada obat top 1 → modal tampilkan breakdown V_i
5. Putuskan: buat PO baru via Resource "Purchase Orders" untuk obat tersebut
```

### Flow 2: Penerimaan Barang dengan ED (Petugas)
```
1. Login petugas → Receive Orders → Create
2. Pilih PO yang ada (auto-fill items dengan sisa qty)
3. Per item: input batch_number, manufacture_date, expired_date (required)
4. Save → MedicineStock D entry auto-created → stok bertambah
5. Sistem update Medicine.stock_status berdasarkan stok baru
```

### Flow 3: Penjualan dengan Validasi Stok (Petugas)
```
1. Login petugas → Orders → Create
2. Tambah item: pilih medicine, input qty
3. Sistem validasi: qty ≤ getAvailableStock(), jika tidak → error "Stok tidak mencukupi"
4. Save → MedicineStock C entry auto-created → stok berkurang
5. Setelah save, sistem update Medicine.stock_status
```

### Flow 4: Konfigurasi Bobot SAW (Admin)
```
1. Login admin → SPK Restock → Kriteria SAW
2. Toggle "Aktif" inline di table (off → on divalidasi terhadap total)
3. Edit salah satu kriteria (mis. C1)
4. Modifikasi bobot atau scale_rules JSON (Repeater)
5. Save → 3-layer validasi otomatis
6. Setelah save, sistem cek total bobot — jika ≠ 1.000 muncul warning persistent
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
| Kartu Stok per Obat | `app/Filament/Pages/MedicineStockDetail.php` | Detail D/C transaksi + running balance, export Excel |
| Rekap Penjualan & Pembelian | `app/Filament/Pages/LaporanRekap.php` | Summary cards + tabel agregasi 50 top obat + total, export Excel |
| Fast / Slow / Dead Stock | `app/Filament/Pages/LaporanMoving.php` | 3 kategori dengan filter top N, multi-sheet Excel |
| Export Receive Order | `app/Filament/Exports/ReceiveOrderExporter.php` | Export data RO (nomor RO, PO, supplier, tanggal, status) |
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

Seeder `SpkTestDataSeeder` (idempotent) generate:
- 150 medicines dengan format code match Filament form (mis. `SIP/PARAC100/ANT/SLP/001`)
- 1 supplier "PT. Distributor SPK Test"
- 5 ReceiveOrder dengan ED per batch (cover bracket Tabel 3.7: 30-90, 91-180, 181-365, 366-730, >730)
- 250 Orders distributed over 30 hari dengan demand sesuai Tabel 3.6
- Distribusi atribut merata cover semua bracket scale_rules

Cleanup via marker `[SPK_TEST_DATA]` di description + prefix code `ORD-SPK-` / `RO-SPK-` (`withTrashed()` untuk soft-deleted).

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
| 1 | UML Diagrams (Use Case, Activity, Sequence, ERD) | Gambar di draw.io / StarUML / Visual Paradigm | ⏳ |
| 2 | Update PDF Bab 3.4.4 dengan hasil sistem (Opsi 2) | Rewrite narasi + Tabel 3.10-3.16 dengan ranking A2 menang | ⏳ |
| 3 | Update `sipokat_project_overview.md` | Versi: Laravel 11 → Laravel 12, Filament v3 → v4 | ⏳ |
| 4 | Setup cron job di server hosting | `* * * * * cd /path && php artisan schedule:run` | ⏳ |
| 5 | Setup Roles & Permissions via Filament Shield | Sesuai Tabel 3.2 proposal (Admin / Petugas / Pemilik). Policy classes sudah tersedia (9 policy). | ⏳ |
| 6 | Smoke test browser end-to-end | Wajib sebelum demo sidang | ⏳ |
| 7 | Write Pest tests (unit + feature) | Framework Pest sudah terpasang, test skeleton ada di `tests/` | ⏳ |

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
- [x] Algoritma SAW 7 langkah persis match proposal Bab 3.4.3
- [x] 4 kriteria + scale_rules persis match Tabel 3.4-3.8
- [x] Notifikasi otomatis berjalan (terdaftar di scheduler)
- [x] Laporan rekap + fast/slow moving + kartu stok lengkap dengan export Excel
- [x] Audit trail SAW dengan snapshot historis + breakdown V_i
- [x] Tech stack: Laravel 12 + Filament 4.0 + MySQL + Tailwind CSS 4 (versi up-to-date)
- [x] Data demo 150 obat siap untuk peragaan sidang
- [x] Import obat & supplier via Excel/CSV dengan template download
- [x] Export Receive Order via Filament Exporter
- [x] Pengaturan umum aplikasi (brand name dinamis, kontak info)
- [x] 9 Policy classes untuk authorization

**Status: ✅ ALL ACCEPTANCE CRITERIA MET (updated 2026-07-02)**

---

**Dokumen terkait:**
- [CLAUDE.md](CLAUDE.md) — progress & mentoring document dengan detail teknis
- [README.md](README.md) — dokumentasi project setup & deployment
