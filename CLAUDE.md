# CLAUDE.md — Sipokat: Progress & Mentoring Document

> Dokumen ini merangkum **kondisi proyek saat ini** dibanding **requirement di draft proposal TA "Rancang Bangun Sistem Inventory Obat Berbasis Web dengan SPK Metode SAW pada Apotek Anugrah Husada"** (Bab I-III).
> Tujuan: bahan review/mentoring untuk dosen pembimbing.
> Last updated: 2026-09-07

---

## 1. Ringkasan Status

| Aspek | Status | Catatan |
|-------|--------|---------|
| Master Data (obat, supplier, unit) | ✅ Selesai | CRUD lengkap di Filament. Rak Obat dihapus; Kategori dikunci 2 golongan (Obat Bebas/Obat Keras), tanpa CRUD — lihat Section 12 |
| Procurement (PO + RO partial + ED tracking) | ✅ Selesai | Auto-numbering, status tracking, export Excel; field ED/batch/manufacture_date sudah di-input per item RO |
| Inventory (Kartu Stok via `MedicineStock` D/C) | ✅ Selesai | `StockCardService` solid; status auto-update; `Medicine::currentStock()` konsisten dengan filter soft-delete |
| Stock Opname | ✅ Selesai | Bisa adjustment D/C |
| Orders (penjualan) | ✅ Selesai | Auto-deduct via MedicineStock C entry, validasi availability, reverse-recreate saat edit, transactional |
| Authentication & User CRUD | ✅ Selesai | Filament Shield + Spatie Permission terpasang |
| Role-based access (Admin / Petugas / Pemilik) | ⏸️ Skip | User handle sendiri pakai Filament Shield |
| **SPK SAW (core skripsi)** | ✅ **Selesai** | Modul lengkap: 3 model + service + Filament resource bobot + Page hitung + scheduled recalc + 3-layer validasi bobot |
| Notifikasi stok minimum & kedaluwarsa | ✅ Selesai | Daily scheduled command kirim Filament DB notification ke semua user |
| Dashboard & Widget | ✅ Selesai | 5 widget aktif: SawTop10, LowStock, PendingPO, Expiring, SalesSummary |
| Data demo SPK (150 obat) | ✅ Selesai | Seeder `SpkTestDataSeeder` — 150 obat + 250 orders + 5 RO dengan ED, distribusi merata semua bracket scale_rules |
| Reporting lanjutan (rekap penjualan/pembelian, fast/slow moving) | ✅ Selesai | Page `LaporanRekap` + `LaporanMoving` dengan export Excel |
| Audit trail SAW (riwayat snapshot) | ✅ Selesai | Resource `SawCalculations` — list + view detail snapshot historis |
| Detail breakdown per obat (verifikasi V_i) | ✅ Selesai | ViewAction modal di SawCalculation page & history — kalkulasi V_i step-by-step match Bab 3.4.4 |

---

## 2. Functional Requirements (Tabel 3.3 Proposal) vs Implementation

| Kode | Requirement | Status | Implementasi |
|------|-------------|--------|--------------|
| F-01 | Login & autentikasi | ✅ | Laravel auth + Filament login panel |
| F-02 | Kelola data obat (CRUD) | ✅ | Filament `MedicineResource` |
| F-03 | Catat transaksi obat masuk & keluar | ✅ | Masuk via RO → `MedicineStock` type D; keluar via Orders → type C dengan validasi qty ≤ availableStock |
| F-04 | Notifikasi stok minimum & obat mendekati kedaluwarsa | ✅ | Command `sipokat:check-stock-and-expiry` dijadwalkan 08:00 daily, kirim Filament DB notification |
| F-05 | Laporan inventory otomatis | ✅ | Kartu stok per obat + 5 dashboard widgets + Page `LaporanRekap` (rekap penjualan/pembelian, export Excel) + Page `LaporanMoving` (fast/slow moving + dead stock, export Excel) |
| F-06 | SAW untuk rekomendasi prioritas restock | ✅ | `SawCalculationService` + Page "Hitung Prioritas Restock" + scheduled recalc 06:00 daily |
| F-07 | Tampilan hasil perangkingan obat | ✅ | Page SawCalculation menampilkan ranking semua obat (paginated table), plus widget Top-10 di dashboard |

---

## 3. SPK SAW — Detail Implementasi (Core Skripsi)

### 3.1 Skema Database (3 tabel baru)

```
saw_criteria
├── code (C1..C4, unique)
├── name, type (cost|benefit), weight (decimal 4,3)
├── scale_rules (JSON) — aturan konversi nilai mentah → skala 1-5
├── sort_order, is_active, description

saw_calculations (snapshot per perhitungan)
├── calculated_at, calculated_by (FK user)
├── period_start, period_end (date)
├── trigger_type (manual|scheduled)
├── criteria_snapshot (JSON — freeze config saat hitung untuk audit)
├── total_alternatives

saw_calculation_results (per obat per kalkulasi)
├── saw_calculation_id (FK), medicine_id (FK)
├── c1_raw..c4_raw (nilai mentah: stok, demand/bln, sisa ED hari, harga beli)
├── c1_score..c4_score (skala 1-5 hasil konversi via scale_rules)
├── c1_norm..c4_norm (hasil normalisasi X/max)
├── preference_value (V_i), rank
```

### 3.2 Pipeline Perhitungan (SawCalculationService)

```
Input: period_start, period_end, trigger_type, user_id
  │
  ├─ getRawValue() per obat per kriteria:
  │     C1 = Medicine::currentStock()
  │     C2 = aggregate OrderItem.qty dalam periode, diproyeksikan ke ekuivalen bulanan
  │          ((total_qty / period_days) × 30) — supaya scale_rules tetap konsisten
  │          saat user pilih period bukan 30 hari
  │     C3 = Medicine::nearestExpiryDays() — FEFO batch terdekat dari receive_order_items
  │     C4 = Medicine.purchase_price
  │
  ├─ buildDecisionMatrix() — convert raw ke skor 1-5 via SawCriteria::convertToScore()
  │
  ├─ normalize() — R = X/max untuk SEMUA kriteria (lihat Section 5 untuk alasan)
  │     Score 0 (data missing) → norm 0 (tidak menyumbang preferensi)
  │
  ├─ calculatePreference() — V_i = Σ (W_j × R_ij)
  │
  └─ persist DB transaction: saw_calculations + saw_calculation_results (rank ter-assign)

Output: SawCalculation snapshot dengan results
```

### 3.3 Konversi Skala (scale_rules JSON di saw_criteria)

Format konsisten untuk semua kriteria — editable admin via Filament:

```json
[
  {"min": null, "max": 10,   "score": 5},
  {"min": 11,   "max": 30,   "score": 4},
  {"min": 31,   "max": 60,   "score": 3},
  {"min": 61,   "max": 100,  "score": 2},
  {"min": 101,  "max": null, "score": 1}
]
```

`null` = open-ended. Seeder default isi sesuai Tabel 3.5-3.8 proposal.

### 3.4 Kriteria SAW (Tabel 3.4 Proposal)

| Kriteria | Bobot | Sumber Data | Status |
|----------|-------|-------------|--------|
| C1 — Stok (cost) | 0.300 | `Medicine::currentStock()` | ✅ |
| C2 — Permintaan/bulan (benefit) | 0.300 | Agregasi `OrderItem.qty` per `medicine_id` per periode → diproyeksi ke 30 hari | ✅ |
| C3 — Sisa kedaluwarsa hari (cost) | 0.200 | `Medicine::nearestExpiryDays()` via `receive_order_items.expired_date` (FEFO) | ✅ |
| C4 — Harga beli (cost) | 0.200 | `Medicine.purchase_price` | ✅ |

**Total bobot = 1.000** (di-validate di UI: kalau ≠ 1.000 muncul warning, dan tombol "Hitung Sekarang" reject).

---

## 4. Action Items per Priority

### 🔴 Priority 1 — Blocker untuk SPK SAW

#### P1.1 Tambah tracking kedaluwarsa (C3) — ✅ Selesai

**Keputusan**: Opsi B — kolom di `receive_order_items` (per batch, akurat dengan kenyataan apotek).

Yang dibangun:
- Migration `add_expiry_to_receive_order_items_table` — kolom `batch_number`, `manufacture_date`, `expired_date` (nullable + index)
- `ReceiveOrderForm.php` — 3 field baru di repeater items, `expired_date` required dengan validasi `after(manufacture_date)`
- `ReceiveOrderItem.php` — cast date
- `Medicine.php` — method `nearestExpiryDate()` & `nearestExpiryDays()` dengan logika FEFO

**Catatan FEFO**: Karena stok dihitung dinamis dari `MedicineStock` (bukan per batch), konvensi: anggap stok keluar mengurangi batch dengan ED terdekat. Untuk skripsi cukup pakai ED terdekat dari RO yang masih ada stok globalnya.

#### P1.2 Bangun modul SAW — ✅ Selesai

Yang dibangun:
- 3 migration: `saw_criteria`, `saw_calculations`, `saw_calculation_results`
- 3 model: `SawCriteria` (dengan `convertToScore()`), `SawCalculation` (dengan scope `latestCalculation`), `SawCalculationResult`
- Service `SawCalculationService` dengan pipeline `execute() → getRawValue → buildDecisionMatrix → normalize → calculatePreference`
- Seeder `SawCriteriaSeeder` — 4 kriteria default sesuai Tabel 3.4-3.8 proposal
- Filament Resource `SawCriterias/` — CRUD bobot + scale_rules editor (Repeater dengan item label dinamis)
- Filament Page `SawCalculation` — form periode + tombol "Hitung Sekarang" + table ranking paginated
- **Polish iterasi 2026-05-30** (lihat Section 11 untuk detail):
    - Toggle column `is_active` di table Kriteria SAW (aktif/non-aktif inline)
    - 3-layer validasi bobot: per-range `min ≤ max`, save bobot ≤ 1.000, aktivasi via toggle ≤ 1.000
    - Layout form Grid 3-kolom (rapi, helper text per field)
    - Rename label `V_i` → "Nilai Prioritas" + tooltip notasi matematis

---

### 🟠 Priority 2 — Core functionality

#### P2.1 Integrasi Orders ↔ MedicineStock — ✅ Selesai

Audit: 95% sudah ada saat dev plan ditulis. Yang ditambahkan:
- ✅ `CreateOrder.handleRecordCreation()` — auto-create MedicineStock C entries + validate `qty <= getAvailableStock()` (sudah ada sebelumnya)
- ✅ `EditOrder.handleRecordUpdate()` — delete old C entries, re-validate stock, recreate, transactional (sudah ada)
- ✅ Soft delete cascade — `whereHas` di `StockCardService::getAvailableStock()` exclude entries dari Order soft-deleted (sudah ada)
- ✅ **Fix konsistensi**: `Medicine::currentStock()` di-update agar pakai filter `whereHas` yang sama dengan `getAvailableStock()`. Penting karena `SawCalculationService` pakai `currentStock()` untuk C1 — sebelum fix, soft-deleted Order tidak ter-exclude → SAW over-estimate stok.

#### P2.2 Dashboard Widgets — ✅ Selesai

5 widget di `app/Filament/Widgets/`:
- `SawTop10RestockWidget` — read snapshot SAW terakhir, top 10 dengan badge rank (danger 1-3, warning 4-7, gray 8-10)
- `LowStockMedicinesWidget` — obat dengan `stock_status` empty/almost_empty
- `PendingPurchaseOrdersWidget` — PO approved tapi belum fully received
- `ExpiringMedicinesWidget` — batch ED ≤ 90 hari, badge "Sisa Hari" by urgency
- `SalesSummaryWidget` — line chart 30 hari grand_total (Order status ≠ cancelled)

Registered di `AdminPanelProvider.php` widgets array.

#### P2.3 Roles & Permissions — ⏸️ DI-SKIP (user handle sendiri)

User akan setup roles via Filament Shield mandiri. Konsekuensi untuk P2.4: notifikasi sementara dikirim ke **semua user**. Bisa di-refactor ke `User::role('admin')` setelah role di-setup.

#### P2.4 Notifikasi (F-04) — ✅ Selesai

Yang dibangun:
- Command `app/Console/Commands/CheckStockAndExpiryCommand.php` — scan + kirim Filament DB notification
- Command `app/Console/Commands/RecalculateSawCommand.php` — daily SAW recalc dengan `trigger_type=scheduled`
- `routes/console.php` — schedule:
  - `sipokat:recalculate-saw` daily 06:00 (hasil dipakai dashboard widget)
  - `sipokat:check-stock-and-expiry` daily 08:00 (notif jam buka apotek)

**Catatan deploy**: Server hosting perlu setup cron: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`. Tanpa ini scheduled tasks tidak eksekusi.

---

### 🟡 Priority 3 — Enhancement — ✅ Selesai

- ✅ Laporan rekap penjualan & pembelian (export Excel) — Page `LaporanRekap`
- ✅ Laporan fast-moving / slow-moving / dead-stock (export Excel) — Page `LaporanMoving`
- ✅ History/audit log perubahan SAW — Resource `SawCalculations` (list + view detail snapshot historis)
- ✅ Detail breakdown per obat dalam ranking — ViewAction modal dengan kalkulasi V_i step-by-step match Bab 3.4.4 proposal

---

## 5. Catatan Teknis Penting (untuk Sidang)

### 5.1 Inkonsistensi Proposal Bab 3.4.4 vs Tabel 3.5-3.8

**Temuan**: PDF Tabel 3.10 (worked example) berisi nilai konversi terbalik dari Tabel 3.5-3.8.
- Tabel 3.5 jelas bilang: stok ≤10 → score 5 (prioritas tinggi).
- Tapi Tabel 3.10 menulis A1 (stok=8) → C1=1, A9 (stok=4) → C1=1. Itu inverted.
- Lalu PDF pakai rumus cost `min/X` di atas nilai terbalik → V_i jadi tidak konsisten matematis.

**Keputusan sistem (Opsi 2)**: Sistem mengikuti **Tabel 3.5-3.8 sebagai sumber kebenaran** (score 5 = high priority) dan menggunakan rumus normalisasi **`R = X/max` untuk SEMUA kriteria** (karena pasca konversi semua sudah benefit-like — tipe cost/benefit di RAW input sudah ter-encode di scale_rules).

**Hasil ranking 5 alternatif sample PDF**:

| Rank | Sistem (Opsi 2) | PDF Bab 3.4.4 (original) |
|------|-----------------|---------------------------|
| 1 | A2 Amoxicillin V=0.94 | A9 Lipitor V=0.84 |
| 2 | A1 Paracetamol V=0.90 | A1 Paracetamol V=0.72 |
| 3 | A7 Sangobion V=0.68 | A2 Amoxicillin V=0.61 |
| 4 | A6 Lansoprazole V=0.66 | A6 Lansoprazole V=0.56 |
| 5 | A9 Lipitor V=0.60 | A7 Sangobion V=0.40 |

**Argumentasi sidang**: A2 menang karena stok rendah (12) + demand tinggi (90/bln) + ED dekat (450 hari) + harga murah (6.500) = kandidat restock yang paling urgent secara apotek logic. A9 (Lipitor) ranked terakhir karena meski stok 4, demand cuma 15/bulan dan harga 165rb — tidak urgent. Ranking sistem lebih defensible dibanding PDF.

**Action**: Update Bab 3.4.4 proposal dengan hasil sistem + flag inkonsistensi sebagai "temuan dalam implementasi".

### 5.2 Filtering Soft-Delete Konsisten

`Medicine::currentStock()` di-update agar punya filter `whereHas` yang sama dengan `StockCardService::getAvailableStock()`. Tanpa fix ini, kalau Order di-soft-delete, `MedicineStock` C entries-nya tidak ter-exclude → SAW C1 over-estimate stok. Sekarang dua method ini equivalent dan keduanya respect soft-delete cascade.

### 5.3 Proyeksi Demand ke Ekuivalen Bulanan

Kalau user pilih period custom (mis. 60 hari), raw demand di-proyeksi ke 30 hari:
```
monthly_equivalent = (total_qty_dalam_periode / period_days) × 30
```
Tujuan: scale_rules tetap konsisten (Tabel 3.6: ">80 = score 5" tetap valid sebagai "demand bulanan").

### 5.4 FEFO untuk Kriteria C3

Karena `MedicineStock` tidak track batch-level (cuma D/C qty global), `nearestExpiryDays()` pakai konvensi FEFO:
- Cari `receive_order_items.expired_date` terdekat yang `>= today`
- Hanya valid kalau `Medicine::currentStock() > 0` (kalau stok habis, tidak ada batch tersisa)

Untuk akurasi penuh per-batch perlu refactor `MedicineStock` jadi multi-batch (out of scope skripsi).

---

## 6. Keputusan Final (dijawab user 2026-05-21)

| # | Pertanyaan | Keputusan |
|---|-----------|-----------|
| 1 | Tracking ED | **Opsi B** — kolom di `receive_order_items` (per batch) |
| 2 | Bobot SAW | **Editable admin via UI** (`saw_criteria.weight`) |
| 3 | Skala konversi 1-5 | **Editable via DB** (`saw_criteria.scale_rules` JSON) |
| 4 | Periode permintaan (C2) | **Custom date range** (form input, default 30 hari, normalisasi ke monthly equivalent) |
| 5 | Frekuensi recalculate | **Keduanya** — button manual + scheduled harian 06:00 (untuk dashboard widget) |
| 6 | Output ranking | **Tampil semua obat** (paginated table, sort by `preference_value DESC`) |
| 7 | Role split | **User handle sendiri** — tidak setup roles/permissions di scope ini |
| 8 | Resolusi inkonsistensi Tabel 3.10 | **Opsi 2** — sistem ikuti Tabel 3.5-3.8 + X/max universal, update Bab 3.4.4 proposal |

---

## 7. Roadmap Eksekusi Final

```
Step 1 (P1.1)  → ✅ Migration expired_date di receive_order_items + UI form + nearestExpiryDays()
Step 2 (P1.2a) → ✅ Migration & Model: SawCriteria, SawCalculation, SawCalculationResult + seeder
Step 3 (P1.2b) → ✅ SawCalculationService (convert, normalize, preference, rank, execute)
Step 4 (P1.2c) → ✅ Filament Resource SawCriteria (CRUD bobot + scale_rules) + Page SawCalculation
Step 5 (P2.1)  → ✅ Tutup Phase 2: Order ↔ MedicineStock integration + fix Medicine::currentStock() konsistensi
Step 6 (P2.2)  → ✅ Dashboard widgets (LowStock, Expiring, PendingPO, SalesSummary, SawTopRestock)
Step 7 (P2.4)  → ✅ Scheduled commands: sipokat:recalculate-saw 06:00 + sipokat:check-stock-and-expiry 08:00
Step 8 (P3)    → ✅ Laporan rekap + fast/slow moving + history snapshot SAW + detail breakdown V_i
Step 9         → ✅ Perampingan master data: hapus Rak Obat, kunci Kategori jadi Obat Bebas/Obat Keras
                    + resync segmen kategori pada kode obat (Section 12)
— (P2.3 di-skip, user handle sendiri pakai Filament Shield)
```

---

## 8. File yang Dibangun (bukti kerja per Step)

### Step 1 (P1.1 — Tracking ED)
| Type | Path |
|------|------|
| Migration | `database/migrations/2026_05_21_000001_add_expiry_to_receive_order_items_table.php` |
| Modified | `app/Filament/Resources/ReceiveOrders/Schemas/ReceiveOrderForm.php` |
| Modified | `app/Models/ReceiveOrderItem.php` |
| Modified | `app/Models/Medicine.php` (+nearestExpiryDate, nearestExpiryDays) |

### Step 2-4 (P1.2 — Modul SAW)
| Type | Path |
|------|------|
| Migration | `database/migrations/2026_05_21_000002_create_saw_criteria_table.php` |
| Migration | `database/migrations/2026_05_21_000003_create_saw_calculations_table.php` |
| Migration | `database/migrations/2026_05_21_000004_create_saw_calculation_results_table.php` |
| Model | `app/Models/SawCriteria.php` |
| Model | `app/Models/SawCalculation.php` |
| Model | `app/Models/SawCalculationResult.php` |
| Service | `app/Services/SawCalculationService.php` |
| Seeder | `database/seeders/SawCriteriaSeeder.php` |
| Modified | `database/seeders/DatabaseSeeder.php` (register seeder) |
| Filament Resource | `app/Filament/Resources/SawCriterias/` (5 file: Resource, Form, Table, ListPage, EditPage) |
| Filament Page | `app/Filament/Pages/SawCalculation.php` |
| Blade View | `resources/views/filament/pages/saw-calculation.blade.php` |

### Step 5 (P2.1 — Orders integration fix)
| Type | Path |
|------|------|
| Modified | `app/Models/Medicine.php` (`currentStock()` filter soft-delete) |

### Step 6 (P2.2 — Dashboard Widgets)
| Type | Path |
|------|------|
| Widget | `app/Filament/Widgets/SawTop10RestockWidget.php` |
| Widget | `app/Filament/Widgets/LowStockMedicinesWidget.php` |
| Widget | `app/Filament/Widgets/PendingPurchaseOrdersWidget.php` |
| Widget | `app/Filament/Widgets/ExpiringMedicinesWidget.php` |
| Widget | `app/Filament/Widgets/SalesSummaryWidget.php` |
| Modified | `app/Providers/Filament/AdminPanelProvider.php` (register widgets) |

### Step 7 (P2.4 — Scheduled + Notifikasi)
| Type | Path |
|------|------|
| Command | `app/Console/Commands/CheckStockAndExpiryCommand.php` |
| Command | `app/Console/Commands/RecalculateSawCommand.php` |
| Modified | `routes/console.php` (schedule commands) |

### Polish iterasi 2026-05-30 (UX + Demo Data)
| Type | Path |
|------|------|
| Seeder | `database/seeders/SpkTestDataSeeder.php` (150 obat + RO + Orders, distribusi merata, idempotent) |
| Modified | `app/Filament/Resources/SawCriterias/Schemas/SawCriteriaForm.php` (Grid 3-kolom rapi, 3-layer validasi: scale_rules min≤max, bobot ≤ 1.000) |
| Modified | `app/Filament/Resources/SawCriterias/Tables/SawCriteriaTable.php` (ToggleColumn `is_active` + beforeStateUpdated block aktivasi kalau total > 1) |
| Modified | `app/Filament/Pages/SawCalculation.php` (label `V_i` → "Nilai Prioritas" + tooltip) |
| Modified | `app/Filament/Widgets/SawTop10RestockWidget.php` (label `V_i` → "Nilai Prioritas" + tooltip) |

### Step 8 (P3) — Reporting & Audit Trail (2026-06-03)
| Type | Path |
|------|------|
| Page | `app/Filament/Pages/LaporanRekap.php` (filter periode + tipe, summary cards, agregasi per obat, export Excel) |
| Blade | `resources/views/filament/pages/laporan-rekap.blade.php` |
| Page | `app/Filament/Pages/LaporanMoving.php` (fast/slow/dead-stock analysis, multi-sheet Excel) |
| Blade | `resources/views/filament/pages/laporan-moving.blade.php` |
| Blade partial | `resources/views/filament/pages/partials/moving-table.blade.php` |
| Resource | `app/Filament/Resources/SawCalculations/SawCalculationResource.php` (history snapshot SAW, read-only) |
| Tables | `app/Filament/Resources/SawCalculations/Tables/SawCalculationHistoryTable.php` (filter trigger_type + date range) |
| Pages | `app/Filament/Resources/SawCalculations/Pages/ListSawCalculationHistory.php`, `ViewSawCalculationHistory.php` |
| Blade | `resources/views/filament/resources/saw-calculations/pages/view.blade.php` (info snapshot + criteria_snapshot collapsible + ranking table) |
| Blade partial | `resources/views/filament/pages/partials/saw-result-detail.blade.php` (modal breakdown V_i step-by-step) |
| Modified | `app/Filament/Pages/SawCalculation.php` (tambah ViewAction "Detail Hitungan" di table) |

### Step 9 — Perampingan Master Data (2026-09-07)
Daftar file lengkap ada di **Section 12.1** (Rak Obat) dan **Section 12.3** (migration). Ringkasnya: 2 migration baru, 15 file dihapus, 8 file kode dimodifikasi.

---

## 9. Cara Test Manual (untuk Demo Sidang)

### 9.0 Test Laporan & History SAW
1. Login admin → sidebar muncul group **"Laporan"**
    - Klik **"Rekap Penjualan & Pembelian"** → set periode + tipe → klik "Tampilkan Laporan" → summary cards + tabel agregasi muncul → klik "Export Excel" untuk download.
    - Klik **"Fast / Slow Moving"** → analisis demand bulanan → tampilkan 3 tabel (fast/slow/dead stock) + multi-sheet Excel.
2. Menu **"SPK Restock → Riwayat Perhitungan"** → list semua snapshot historis. Klik "Lihat Hasil" → tampilkan ranking lengkap + info snapshot + collapsible criteria_snapshot.
3. Di table ranking (SawCalculation Page atau History), klik **"Detail Hitungan"** → modal breakdown V_i step-by-step (W₁×R₁ + W₂×R₂ + W₃×R₃ + W₄×R₄) match Bab 3.4.4 proposal.

### 9.1 Generate data demo SPK
```bash
php artisan db:seed --class=SpkTestDataSeeder
php artisan sipokat:recalculate-saw
```
- 150 obat ter-generate dengan code format match Filament form (mis. `SIP/PARAC100/OBB/SLP/001`)
- 5 ReceiveOrder + 250 Orders dalam 30 hari, distribusi atribut cover semua bracket Tabel 3.5-3.8
- SAW snapshot ter-create, dashboard widget langsung berisi data

### 9.2 Test SPK SAW end-to-end
1. Login admin → sidebar muncul group **"SPK Restock"**
2. Klik **"Kriteria SAW"** → 4 row (C1, C2, C3, C4).
    - Toggle `is_active` di kolom "Aktif" untuk on/off inline.
    - Edit salah satu → modifikasi bobot atau skala konversi (test validasi `min ≤ max` dan `total bobot ≤ 1.000`).
3. Klik **"Hitung Prioritas Restock"** → set periode → klik **"Hitung Sekarang"** → snapshot baru tersimpan, table refresh.
    - Kolom "Nilai Prioritas" (hover untuk lihat rumus `V_i = Σ Wj × Rij`).
4. Buka **Dashboard** → widget **"Top 10 Prioritas Restock (SAW)"** sync dengan snapshot terakhir.

### 9.3 Test Notifikasi
```bash
php artisan sipokat:check-stock-and-expiry
```
- Console output: jumlah obat & batch yang trigger notif
- DB notification masuk untuk semua user → bel notifikasi Filament di header panel muncul angka unread

### 9.4 Test Scheduled Recalc
```bash
php artisan sipokat:recalculate-saw
```
- Snapshot baru dengan `trigger_type=scheduled` tersimpan di `saw_calculations`
- Dashboard widget langsung pakai snapshot terbaru

### 9.5 Verifikasi Schedule Cron
```bash
php artisan schedule:list
```
Output:
```
0 6 * * *  php artisan sipokat:recalculate-saw
0 8 * * *  php artisan sipokat:check-stock-and-expiry
```

---

## 10. Open Items / Yang User Perlu Handle Sendiri

| # | Item | Catatan |
|---|------|---------|
| 1 | ~~Backfill `expired_date` untuk 6 RO existing~~ | ✅ Auto-handle via `SpkTestDataSeeder` — kalau pakai data demo, ED sudah ter-isi semua |
| 2 | Update Bab 3.4.4 PDF dengan ranking sistem (Opsi 2) | Argumentasi siap di Section 5.1 dokumen ini |
| 3 | Setup cron job di server hosting | `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1` |
| 4 | Setup Roles & Permissions via Filament Shield | User handle sendiri sesuai Bab 3 Tabel 3.2 (Admin/Petugas/Pemilik) |
| 5 | Update `sipokat_project_overview.md` | Saat ini masih nulis "Laravel 11 + Filament v3", aktual "Laravel 12 + Filament 4". Penguji bisa tanya |
| 6 | Smoke test browser end-to-end | Wajib sebelum demo sidang. Checklist + skenario lengkap ada di chat / [NEXT_STEPS.md](NEXT_STEPS.md) opsi C1 |
| 7 | (Opsional P3) Laporan rekap & fast/slow moving | Bukan blocker TA tapi memperkuat manfaat. Lihat [NEXT_STEPS.md](NEXT_STEPS.md) opsi B1/B2 |

---

---

## 11. Polish Iterasi 2026-05-30 (Detail)

### 11.1 Seeder Data Demo SPK
- **File**: `database/seeders/SpkTestDataSeeder.php`
- **Output**: 150 obat + 1 supplier dummy + 5 ReceiveOrder + 250 Orders (30 hari)
- **Algoritma plan-then-execute**: tentukan dulu `target_stock` & `target_demand` per obat → `ro_qty = stok + demand` → stok akhir TIDAK PERNAH negatif
- **Distribusi merata** semua bracket Tabel 3.5-3.8: stok 0-200+, demand 0-130/bln, ED 30-1000 hari, harga 2k-250k
- **Code obat match Filament form** — `generateMedicineCode()` mirror dari `MedicineForm::generateCode()` (uppercase, format `SIP/NAMA4/ALIAS_KAT/UNIT3/SEQ`, fallback 5-char prefix kalau konflik). Sejak 2026-09-07 segmen kategori pakai `alias` (OBB/OBK), bukan 3 huruf pertama nama — lihat Section 12.2
- **Idempotent**: cleanup via marker `[SPK_TEST_DATA]` di description + prefix code `ORD-SPK-` / `RO-SPK-` (pakai `withTrashed()` supaya soft-deleted juga ke-purge)
- **Run**: `php artisan db:seed --class=SpkTestDataSeeder`

### 11.2 Toggle Column + 3-Layer Validasi Bobot
- **ToggleColumn** di `SawCriteriaTable` — aktif/non-aktif kriteria inline tanpa masuk edit page
- **Layer 1** (form, per range scale_rules): rule `min ≤ max` di field `min` dan `max` — error inline kalau dilanggar
- **Layer 2** (form, saat save bobot): rule check `total_bobot_aktif_lain + bobot_baru ≤ 1.000` — block save dengan error inline detail
- **Layer 3** (table, saat aktivasi via toggle): `beforeStateUpdated` cek `total + weight > 1.000` → throw exception + Filament notification danger persistent
- **Non-aktif → aktif** divalidasi; **aktif → non-aktif** selalu boleh (toh tidak melanggar batas)

### 11.3 Form Layout Rapi
- Section "Identitas Kriteria" pakai Grid eksplisit:
    - Baris 1: Kode (1/3) | Nama Kriteria (2/3)
    - Baris 2: Jenis (1/3) | Bobot (1/3) | Urutan Tampil (1/3)
    - Baris 3: Deskripsi full-width
- `is_active` dipindah dari form ke ToggleColumn di tabel
- Helper text per field (rumus bobot, range scale, posisi sort_order)

### 11.4 Rename `V_i` → "Nilai Prioritas"
- Kolom `preference_value` di `SawCalculation` page dan `SawTop10RestockWidget` di-relabel jadi **"Nilai Prioritas"**
- Tooltip menyimpan notasi matematis: `V_i = Σ Wj × Rij. Semakin tinggi = semakin prioritas restock.`
- User awam (apoteker, manajer) langsung paham; konteks akademis tetap accessible via hover

---

## 12. Perampingan Master Data 2026-09-07

Master data disederhanakan agar sesuai praktik apotek: **Rak Obat dihapus**, dan **Kategori tidak lagi dikelola lewat CRUD** melainkan dikunci pada dua golongan resmi — **Obat Bebas** dan **Obat Keras**.

### 12.1 Rak Obat dihapus tuntas

Kolom `medicines.rack_id` dan tabel `medicine_racks` di-drop, bukan sekadar disembunyikan dari sidebar — menyisakan kolom `NOT NULL` yang tak terpakai justru jadi utang teknis yang bisa ditanyakan penguji.

| Aksi | Path |
|------|------|
| Dihapus | `app/Models/MedicineRack.php` |
| Dihapus | `app/Filament/Resources/MedicineRacks/` (6 file) |
| Dihapus | `app/Policies/MedicineRackPolicy.php` |
| Modified | `app/Models/Medicine.php` (buang relasi `rack()` + `rack_id` dari `$fillable`) |
| Modified | `app/Filament/Resources/Medicines/Schemas/MedicineForm.php` (buang Select Rak, grid jadi 2 kolom) |
| Modified | `app/Filament/Resources/Medicines/Tables/MedicinesTable.php` (buang kolom `rack.name`) |
| Modified | `app/Filament/Imports/MedicineImporter.php` (buang kolom & resolusi `rack_name`) |
| Modified | `database/seeders/MasterDataSeeder.php`, `DemoApotekSeeder.php`, `SpkTestDataSeeder.php` |

### 12.2 Kategori dikunci 2 golongan

Tabel `medicine_categories` dan FK `medicines.category_id` **tetap dipertahankan** — laporan, importer, dan generator kode sudah bergantung padanya, jadi mengubahnya jadi kolom enum akan menyentuh jauh lebih banyak file tanpa manfaat setara. Yang dihapus hanya CRUD-nya (`app/Filament/Resources/MedicineCategories/` 6 file + `MedicineCategoriesPolicy.php`), sehingga isinya tidak bisa ditambah lewat UI.

| Kategori | Alias | Keterangan |
|----------|-------|------------|
| Obat Bebas | `OBB` | Dapat dibeli bebas tanpa resep dokter |
| Obat Keras | `OBK` | Penyerahannya harus dengan resep dokter |

Alias ditambahkan ke `MedicineCategories::$fillable` — sebelumnya tidak ada di sana, sehingga seeder yang mengirim `alias` diam-diam mengabaikannya.

**Kenapa alias wajib dipakai di kode obat**: `generateCode()` dulu memakai `substr($category->name, 0, 3)`. Dengan dua kategori baru, "Obat Bebas" dan "Obat Keras" sama-sama menghasilkan `OBA` — segmen kategori jadi tidak membedakan apa pun. Ketiga tempat yang menduplikasi algoritma ini (`MedicineForm`, `DemoApotekSeeder`, `SpkTestDataSeeder`) kini memakai `$category->alias`, sejalan dengan cara `Unit` diperlakukan di baris sebelahnya.

> **Catatan untuk sidang**: skema lama sebenarnya sudah punya cacat serupa — "Antibiotik" dan "Antiseptik" sama-sama terpotong jadi `ANT`, sehingga 139 obat berbagi segmen yang sama padahal kategorinya berbeda. Perpindahan ke alias sekaligus menutup cacat lama itu.

### 12.3 Migrasi data

| Migration | Isi |
|-----------|-----|
| `2026_09_07_000001_remove_rack_and_lock_medicine_categories.php` | Remap obat ke 2 kategori final → hapus kategori lama → drop `rack_id` + tabel `medicine_racks` |
| `2026_09_07_000002_resync_medicine_codes_with_category_alias.php` | Tulis ulang segmen kategori pada `medicines.code` jadi OBB/OBK |

**Jebakan cascade**: `medicines.category_id` memakai `ON DELETE CASCADE`. Menghapus kategori lama tanpa me-remap obatnya lebih dulu akan **ikut menghapus obatnya**. Urutan di `up()` sengaja dibuat remap-dulu-baru-hapus.

Aturan remap yang dipakai (eksplisit sebagai konstanta di migration, mudah diubah):

| Kategori lama | → | Kategori baru | Jumlah obat |
|---------------|---|---------------|-------------|
| Antibiotik | → | Obat Keras | 69 |
| Analisik, Antiseptik, Vitamin, dan lainnya | → | Obat Bebas | 204 |

**Penomoran ulang kode**: penggabungan kategori bisa membuat dua obat bertemu di kode identik. Pada data aktual terjadi satu kasus — dua CIPROFLOXACIN 1000 mg (dulu Analisik dan Antiseptik) sama-sama jadi Obat Bebas — diselesaikan jadi `.../OBB/KAP/001` dan `.../OBB/KAP/002`. Penulisan dilakukan dua fase (parkir di nilai sementara dulu) supaya unique index `code` tidak terlanggar di tengah proses.

`down()` migration kedua sengaja kosong: singkatan kategori lama tidak tersimpan di mana pun setelah kategorinya dihapus, jadi pemulihan hanya bisa lewat backup database.

### 12.4 Hasil verifikasi

| Cek | Hasil |
|-----|-------|
| Total obat sebelum → sesudah | 273 → **273** (nol kehilangan akibat cascade) |
| Kategori | 4 → **2** (OBB 204 obat, OBK 69 obat) |
| Tabel `medicine_racks` & kolom `rack_id` | hilang |
| Obat dengan kategori yatim | 0 |
| Kode obat unik | 273 dari 273 |
| Kode dengan segmen kategori lama (ANA/ANT/VIT) | 0 |
| Kode yang tidak cocok dengan kategori obatnya | 0 |
| Permission Shield basi (2 resource terhapus) | 22 dibersihkan beserta pivotnya |

### 12.5 Yang perlu diperhatikan

1. **Ketepatan golongan obat belum ditinjau satu per satu.** Remap dilakukan per kategori lama, bukan per obat. Contoh nyata: dua CIPROFLOXACIN kini bergolongan Obat Bebas padahal secara farmasi termasuk obat keras — akibat asalnya berkategori Analisik/Antiseptik. Perlu dirapikan lewat UI sebelum demo sidang.
2. **Data duplikat**: dua CIPROFLOXACIN 1000 mg dengan satuan sama adalah duplikat bawaan seeder demo (dulu lolos karena kategorinya berbeda). Pertimbangkan menghapus salah satunya.
3. Kalau `SpkTestDataSeeder` atau `DemoApotekSeeder` dijalankan ulang, kode obat otomatis memakai OBB/OBK — tidak perlu menjalankan migration kedua lagi.

---

**Status dokumen**: ✅ Mencerminkan kondisi aktual per 2026-09-07.
**Tahap berikutnya**: Rapikan golongan obat per item (Section 12.5), smoke test browser end-to-end (lihat [NEXT_STEPS.md](NEXT_STEPS.md) opsi C1), presentasi ke dosen pembimbing, dan eksekusi item Open Items di Section 10.
