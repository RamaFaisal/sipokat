# Rencana Revisi September 2026

> Hasil brainstorming pasca-wawancara dan pasca-analisa `contoh-data.zip` (14 faktur PT. Nisa Permata
> Mulia, Mei 2024 + 11 screenshot sistem pembanding "Apotik Winong").
> Disusun: 2026-09-12, diperbarui 2026-09-13. **Status: rencana lengkap (Bagian 1–9), menunggu
> pernyataan "matang" dari peneliti sebelum eksekusi E0.** Dibahas per modul; tiap bagian ditutup
> dengan daftar berkas yang tersentuh dan cara verifikasinya. Bagian 8 merekap keputusan
> konsistensi, Bagian 9 urutan eksekusi.
>
> Prinsip yang dipegang di seluruh dokumen: topik TA adalah **prioritas restock**, bukan penjualan.
> Setiap field dan fitur diuji dengan satu pertanyaan — apakah ini dibutuhkan untuk memutuskan
> restock, atau untuk memberi data ke SAW? Keputusan yang lolos uji itu tetapi bukan kontribusi
> (kebersihan data, konversi kemasan, penamaan) **tidak** ditulis sebagai fitur di naskah — cukup
> muncul sebagai konsekuensi di definisi operasional.

---

## 1. Master Data Obat

### 1.1 Keputusan

| # | Keputusan | Dasar |
|---|-----------|-------|
| M1 | Kategori tetap dua golongan: Obat Bebas, Obat Keras | Hasil wawancara. Label PREKURSOR/OOT di faktur diabaikan |
| M2 | Master Satuan dan Supplier **tidak diubah strukturnya** | Sudah sesuai. Isi tabel `units` kini dipakai untuk dua peran: satuan dasar dan nama kemasan. Ampul & Kaleng ditambah sebagai *data* (faktur: KETOROLAC INJ 100 AMP, GG TRIMAN 20 KLG) |
| M3 | `unit_id` = **satuan dasar** = satuan jual = satuan kartu stok. Kemasan beli disimpan sebagai `pack_unit_id` (FK `units`) + `pack_size` (isi per kemasan) | Faktur dalam BOX/FLS/TUBE dengan harga per kemasan; stok berkurang dalam satuan jual, jadi kartu stok harus dalam satuan itu. Selaras dengan CLAUDE.md §13.2 aturan #1 |
| M4 | **`purchase_price` dihapus dari master.** C4 = **HPP rata-rata bergerak** per satuan jual, dibaca dari kartu stok (`hpp_avg` baris terakhir obat itu) — lihat Bagian 4. `Medicine::latestPurchasePrice()` (harga item RO terakhir) tetap ada, hanya untuk harga perkiraan di PO | Kolom di master hanyalah salinan yang bisa basi. **Diputuskan 2026-09-13 (menggantikan "harga penerimaan terakhir")**: C4 mengukur modal yang tertanam pada stok yang dipegang. Definisi operasional C4 di Bab III dan `update-dari-wawancara.md` §4.1 harus diperbarui |
| M5 | **`sale_price` dihapus dari master.** Harga jual diketik kasir di form penjualan, divalidasi **≥ HPP**. Aturan margin **tidak dibangun** (D2) | Bukan urusan restock; modul penjualan hanya dibutuhkan sebagai sumber C2. Margin adalah kebijakan apotek, bukan sistem |
| M6 | `dosage` **dihapus**. `name` memuat nama + kekuatan + merek/pabrikan; keunikan pada seluruh string `name` | Faktur & Winong menulis dosis dan merek di nama (`ALLOPURINOL 100MG IFI` vs `ALLOPURINOL 100MG NOVA`, harga beda). Kolom dosis terpisah tidak bisa membedakan keduanya |
| M7 | `code` → **`OBT-0001`** sekuensial, dibuat sekali, tidak pernah dihitung ulang | Kode lama menyimpan kategori & satuan sehingga dua kali butuh migration penulisan ulang (CLAUDE.md §12.3, §14). Generator lama diduplikasi di 3 tempat |
| M8 | `photo` **dihapus** | Tidak ada gunanya untuk restock |
| M9 | `min_stock` tetap, **wajib > 0**. Nilai bawaan **per satuan jual**: Strip **20**, satuan lain = `pack_size` (satu kemasan); bisa ditimpa. Data lama bernilai 0 di-backfill ke bawaan ini | Dipakai notifikasi F-04 **dan** sebagai pembagi C1 (K1) — jadi harus diisi nyata dari apotek, bawaan hanya jaring pengaman. Angka 20 untuk strip = batas waspada wawancara (B6, B7) |
| M10 | Satuan dasar tiap obat = **satuan yang tertulis di kartu stok apotek**. Untuk tablet: strip atau tablet — **dikunci setelah foto kartu stok diterima** | Ambang C1 "≤ 20 unit" dan C4 "per strip" dari wawancara hanya bermakna kalau satuannya sama dengan yang dihitung apotek |
| M11 | Kemasan di **baris RO boleh ditimpa** (kemasan & isi), master **tidak ditulis balik**. Satuan dasar obat **tidak pernah berubah**: kemasan baru yang merupakan kelipatan bulat satuan dasar → timpa di baris RO; yang bukan → **obat berbeda** | Lihat §1.6. Penulisan balik ditolak karena satu pembelian eceran 5 strip akan mengubah bawaan form jadi "per strip" |
| M12 | `description` **dihapus** | Tidak ada tujuan yang jelas. Satu-satunya pemakai adalah penanda `[SPK_TEST_DATA]` di `SpkTestDataSeeder` untuk pembersihan idempoten — diganti penanda lain (lihat §1.4) |

### 1.2 Skema `medicines` sesudah revisi

| Kolom | Tipe | Perubahan | Keterangan |
|-------|------|-----------|------------|
| `code` | string, unique | **ubah isi** | `OBT-0001` … `OBT-9999` |
| `name` | string, unique (non-deleted) | **tambah unique** | Uppercase, spasi ganda dirapikan. Konvensi: ditulis persis seperti tercetak di faktur PBF |
| `category_id` | FK | tetap | 2 golongan |
| `unit_id` | FK `units` | tetap | Satuan dasar (jual = kartu stok) |
| `pack_unit_id` | FK `units`, **NOT NULL** | **baru** | Nama kemasan beli, mis. Box. Data lama di-backfill = `unit_id` (B8) |
| `pack_size` | int, **NOT NULL** | **baru** | Isi satu kemasan dalam satuan dasar, mis. 10. Boleh 1 bila kemasan = satuan dasar. Data lama di-backfill = 1 (B8) |
| `min_stock` | int, **> 0** | tetap | Bawaan per satuan (M9); data lama 0 di-backfill (B7) |
| `stock_status` | enum | tetap | Turunan, dihitung `StockCardService` |
| `status` | enum active/inactive | tetap | Menentukan alternatif SAW |
| `purchase_price` | — | **drop** | Dibaca dari RO (M4) |
| `sale_price` | — | **drop** | Diketik di penjualan (M5) |
| `dosage` | — | **drop** | |
| `photo` | — | **drop** | |
| `description` | — | **drop** | |

Tidak ada satu pun angka rupiah di master obat — semua rupiah hidup di transaksi, tempat asalnya.

### 1.3 Form master obat sesudah revisi

| Field | Wajib | Sumber | Contoh |
|---|---|---|---|
| Nama | ✔ | diketik | CALORTUSIN KAPLET |
| Kategori | ✔ | pilih | Obat Keras |
| **Satuan jual** (label form; = satuan dasar = satuan kartu stok) | ✔ | pilih | Strip |
| **Kemasan pembelian** (satuan yang tertulis di faktur PBF) | ✔ | pilih dari `units` | Box |
| **Isi per kemasan** (1 kemasan pembelian = berapa satuan jual) | ✔ | angka | 10 |
| Stok minimum (batas waspada) | ✔ | otomatis: Strip 20, satuan lain = isi kemasan; boleh diubah; harus > 0 | 20 |
| Status | ✔ | bawaan aktif | Aktif |
| Kode | sistem | `OBT-0001` saat simpan | |
| Status stok | sistem | kartu stok | |

Lima field yang benar-benar diketik, tidak ada field opsional. Sebelumnya sembilan wajib plus dua opsional.

Supaya tidak rancu di layar, ketiga field satuan ditata sebagai satu baris kalimat dengan helper text:

> **Satuan jual**: `[Strip ▾]` — satuan yang dipakai saat menjual dan menghitung stok.
> **Kemasan pembelian**: `[Box ▾]` isi `[10]` Strip — satuan yang tertulis di faktur PBF; sistem
> mengonversinya ke satuan jual saat penerimaan.

Di kode, "satuan jual" tetap disimpan sebagai `unit_id` (nama kolom lama, tidak perlu diganti) dan
di dokumen ini disebut *satuan dasar* — ketiganya satu hal yang sama.

### 1.4 Aturan turunan

**Kode obat.** Satu generator di model (`Medicine::booted()` → `creating`): ambil angka terbesar dari
`code` yang berpola `OBT-%` (termasuk yang soft-deleted, agar nomor tidak terpakai ulang), tambah
satu, format 4 digit. Field `code` di form jadi read-only/hidden. Seeder tidak lagi membawa
algoritma kode sendiri.

**Penanda data demo.** `SpkTestDataSeeder` kehilangan `description` sebagai tempat penanda
`[SPK_TEST_DATA]`. Pengganti yang paling sederhana: seeder menyimpan daftar `id` obat yang dibuatnya
di `storage/app/spk-test-data.json` dan membacanya saat bersih-bersih. Alternatif tanpa berkas: obat
demo dikenali lewat relasi ke RO berprefiks `RO-SPK-` (prefiks itu sudah dipakai seeder, dan seeder
selalu membuat RO untuk tiap obat).

**Harga beli (C4) = HPP rata-rata bergerak.** Dibaca dari `medicine_stocks.hpp_avg` pada baris
terakhir obat itu (definisi dan rumus di §4.3). Obat berstok 0 tetap punya HPP (nilai terakhir
dipertahankan), jadi tidak kehilangan C4. Obat yang belum punya satu pun baris kartu stok **tidak ikut
dihitung SAW** (K0) — dan karena stok awal wajib lewat RO (§7.3), setiap obat yang punya baris kartu
stok pasti punya HPP. C4 tidak pernah 0.

**Harga perkiraan PO.** `Medicine::latestPurchasePrice()` = `price` item RO terakhir (urut
`receive_orders.receive_date` lalu `id`, RO tidak soft-deleted), per satuan jual. Hanya untuk mengisi
harga awal baris PO (P6). @Harga faktur dipakai apa adanya — **sudah termasuk PPN** (cek faktur 02029:
474.000 + 530.000 + 392.500 = 1.396.500 = Total; DPP adalah hitungan mundur). Kolom Disc kosong di
semua faktur contoh.

**Keunikan nama.** `PARACETAMOL` dan `PARACETAMOL 500MG` adalah dua string berbeda → keduanya boleh
dan memang dua obat berbeda. Yang tidak tertangkap validasi: satu obat fisik terdaftar dua kali karena
ejaan (`PARACETAMOL 500MG` vs `PARACETAMOL 500 MG`). Akibatnya ke SAW nyata — stok dan permintaan
terbelah ke dua alternatif. Penangkalnya konvensi penamaan + normalisasi + dedup di importer data riil.

### 1.5 Ilustrasi: perjalanan 5 Box

Master obat A sudah diisi sekali: satuan dasar **Strip**, kemasan bawaan **Box isi 10**.
Faktur: `5 BOX @ 41.000 = 205.000`.

**Input di baris RO** (persis seperti membaca faktur):

| Kolom | Diisi petugas |
|---|---|
| Obat | Obat A |
| Kemasan | Box (isi 10 Strip) — terisi dari master, boleh diubah |
| Jumlah kemasan | 5 |
| Harga per kemasan | 41.000 |
| Batch / ED | T10088BC / 10-2026 |

**Output yang dihitung sistem** di baris yang sama:

| Kolom | Nilai |
|---|---|
| Jumlah satuan dasar | 50 Strip |
| Harga per satuan dasar | Rp 4.100 |
| Subtotal | Rp 205.000 — harus sama dengan Jml.Harga faktur (kontrol input) |

**Yang tersimpan:**

| Tempat | Isi |
|---|---|
| `receive_order_items` | qty **50**, price **4.100**, batch, ED, jejak konversi: pack_qty 5, pack_size 10 |
| Kartu stok | D **50 Strip** @ harga 4.100, batch T10088BC, ED 2026-10-01 (lapisan baru, C5); `hpp_avg` diperbarui (§4.3) → C4 |
| Master obat A | tidak berubah |
| `latestPurchasePrice()` | 4.100 (harga perkiraan PO berikutnya) |

Kalau PBF mengirim eceran **5 strip lepas**: petugas mengganti kemasan di baris itu ke Strip (isi 1),
jumlah 5, harga 4.100 → tersimpan qty 5, price 4.100. Master tidak berubah.

Kenapa sistem yang mengonversi, bukan petugas: form yang mengikuti faktur bisa dicek (subtotal harus
cocok); perkalian di kepala adalah sumber "salah tulis" yang disebut wawancara; dan harga per strip
yang salah bagi langsung melempar obat ke ujung skala C4.

### 1.6 Dua situasi kemasan berubah

**Situasi A — kemasan luar berubah, isinya tetap satuan dasar yang sama.**
Calortusin Mei datang 10 Box isi 10 strip; Juli datang 1 Karton isi 100 strip; Agustus 5 strip lepas.

| RO | Kemasan (ditimpa di baris) | Isi | Jumlah | Kartu stok |
|---|---|---|---|---|
| Mei | Box | 10 | 10 | D 100 Strip |
| Juli | Karton | 100 | 1 | D 100 Strip |
| Agustus | Strip | 1 | 5 | D 5 Strip |

Kartu stok tidak tahu dari kemasan apa strip itu datang. Master tidak ditulis balik (M11).

**Situasi B — kemasan baru tidak bisa dinyatakan dalam satuan dasar.**
CTM 4MG pertama datang Box berisi strip, lalu datang KLG (kaleng) berisi 1.000 tablet lepas. Tablet
lepas bukan kelipatan strip. Ini **obat berbeda** — dan PBF sendiri menamainya begitu di faktur
(`CTM 4MG KLG TRIMAN`, `GG TRIMAN KLG`, `IFIDEX 0,5MG KLG`); konvensi M6 otomatis memisahkannya.

| Obat | Satuan dasar | Kemasan bawaan |
|---|---|---|
| CTM 4MG | Strip | Box = 10 |
| CTM 4MG KLG TRIMAN | Pcs (tablet) | Kaleng = 1.000 |

Apotek memang menyimpan dan menjual keduanya sebagai barang berbeda, jadi SAW memperlakukannya
sebagai dua alternatif juga benar.

Krim yang satuan dasarnya Tube: datang 20 tube lepas (isi 1) atau 1 box isi 20 tube (isi 20) →
situasi A. "Tube" menggantikan "Box" untuk obat yang tadinya tablet → bentuk sediaan lain →
situasi B.

Contoh pemilihan satuan dasar dari faktur:

| Obat | Faktur | Satuan dasar | Kemasan | Isi |
|---|---|---|---|---|
| Calortusin kaplet | 10 BOX | Strip | Box | 10 |
| Lostacef dry syrup | 60 FLS | Flask | Flask | 1 |
| Ketorolac inj | 100 AMP | Ampul | Ampul (atau Box bila PBF mengirim per box) | 1 (atau 10) |
| Altamed glove | 5 BOX | Box | Box | 1 |

### 1.7 Berkas yang tersentuh

| Aksi | Berkas |
|------|--------|
| Migration baru | drop `dosage`, `photo`, `purchase_price`, `sale_price`, `description`; add `pack_unit_id` (FK `units`), `pack_size`; unique index `name`; penomoran ulang `code` → `OBT-####` urut `id` |
| Model | `app/Models/Medicine.php` — `$fillable`, relasi `packUnit()`, generator kode di `booted()`, `latestPurchasePrice()`, hapus `isLowStock()` (dead code, CLAUDE.md §13.6 #5) |
| Form | `app/Filament/Resources/Medicines/Schemas/MedicineForm.php` — hapus `dosage`, `photo`, `purchase_price`, `sale_price`, `description`, `generateCode()`; tambah `pack_unit_id`/`pack_size`; `min_stock` bawaan per satuan (M9), tolak 0 |
| Table | `app/Filament/Resources/Medicines/Tables/MedicinesTable.php` — kolom kemasan; hapus kolom harga |
| Importer | `app/Filament/Imports/MedicineImporter.php` — hapus `dosage`, `purchase_price`, `sale_price`, `description`; tambah `pack_unit`/`pack_size` |
| SAW | `app/Services/SawCalculationService.php` — C4 via `hpp_avg` kartu stok (Bagian 4) |
| Pemakai `purchase_price` lain | `PurchaseOrderForm` (harga awal → `latestPurchasePrice()`), `ReceiveOrderForm` (fallback harga), `MedicineStockOpnameForm` & `ViewMedicineStockOpname` (hpp penyesuaian → `hpp_avg`) |
| Pemakai `sale_price` | `OrderForm` (harga diketik, validasi ≥ HPP) |
| Referensi `dosage` yang harus dibersihkan (18 berkas) | `MedicineStockOpnameForm`, `MedicineStocksTable`, `OrderForm`, `PurchaseOrderForm`, `ReceiveOrderForm`, 3 widget (`Expiring`, `LowStock`, `SawTop10`), `StockMovementService` (pesan error), 2 blade print (`print-purchase-order`, `print-receive-order`), `tests/Pest.php` |
| Seeder | `MasterDataSeeder` (tambah Ampul, Kaleng), `DemoApotekSeeder` & `SpkTestDataSeeder` (hapus generator kode, `dosage`, harga, `description`; isi `pack_unit_id`/`pack_size`; ganti penanda data demo) |
| Dokumen | `CLAUDE.md` §12.2 (segmen kategori pada kode tidak berlaku lagi), `docs/PRD.md` F-02, `docs/README.md`, naskah Bab III (definisi operasional C1 & C4 menyebut satuan dasar; pembenaran field dosis → pembenaran aturan penamaan) |

### 1.8 Dampak ke modul lain

| Modul | Dampak |
|-------|--------|
| SAW C4 | Sumber pindah dari kolom master ke `hpp_avg` kartu stok (HPP rata-rata bergerak, Bagian 4). Tidak pernah null (K0) |
| SAW alternatif | `status = active` **dan** punya ≥ 1 baris kartu stok (K0) |
| SAW C1 | `min_stock` kini pembagi rasio (K1) — perubahan `min_stock` mengubah peringkat, disengaja |
| Penjualan | Harga item diketik kasir, validasi ≥ HPP. Tidak ada autofill dari master |
| PO | Harga awal dari `latestPurchasePrice()`; nilainya per satuan dasar |
| Stok opname | `hpp` penyesuaian = `hpp_avg` saat itu |
| Notifikasi F-04 | `min_stock` bawaan per satuan (Strip 20, lainnya satu kemasan); membandingkan dengan **stok tersedia** (B4) |
| Laporan | Kolom dosis hilang dari tampilan; nama sudah memuatnya. Laporan margin memakai `hpp` baris C yang diluruskan di Bagian 4 |

### 1.9 Verifikasi

- [ ] Tidak ada referensi `dosage`/`photo`/`purchase_price`/`sale_price`/`description` ke kolom `medicines` yang tersisa
- [ ] `php artisan db:seed --class=SpkTestDataSeeder` dua kali berturut-turut → jumlah obat tidak berlipat (penanda baru bekerja)
- [ ] Semua obat berkode `OBT-####`, unik, urut `id`, tanpa nomor terpakai dua kali (termasuk soft-deleted)
- [ ] Menyimpan RO → `latestPurchasePrice()` obat = harga item RO itu (dipakai PO); `hpp_avg` kartu stok sesuai rumus §4.3; hasil SAW berikutnya memakai `hpp_avg` sebagai C4
- [ ] Obat tanpa baris kartu stok → tidak ikut SAW, tercatat di `excluded_count` (K0)
- [ ] Menyimpan obat dengan nama yang sudah ada → ditolak; `PARACETAMOL` dan `PARACETAMOL 500MG` → keduanya diterima; obat soft-deleted bernama sama tidak menghalangi (indeks unik menyertakan `deleted_at`)
- [ ] `min_stock` terisi otomatis saat create: Strip → 20, satuan lain → `pack_size`; nilai 0 ditolak
- [ ] Setelah migration: tidak ada obat dengan `pack_size` null, `pack_unit_id` null, atau `min_stock` 0
- [ ] Form obat: 5 field diketik, tidak ada field rupiah
- [ ] Test suite hijau; tambah test untuk generator kode dan `latestPurchasePrice()`

### 1.10 Hal terbuka

- **M10 menunggu foto kartu stok**: strip atau tablet untuk sediaan padat. Menentukan `pack_size` seluruh obat tablet dan interpretasi ambang C1. Sampai data datang, seeder demo memakai **strip** (T2).
- Penomoran ulang kode pada data yang ada: urut `id` (urutan pembuatan) — paling mudah dipertanggungjawabkan.
- Implementasi: indeks unik `name` menyertakan `deleted_at` (C4); generator kode dibungkus retry sekali untuk tabrakan simultan (C6).

---

## 2. Transaksi Masuk (Receive Order)

RO adalah pintu masuk data: dari faktur PBF, RO memberi C3 (batch/ED) dan C4 (harga). Konversi
kemasan (M3, M11) hidup di sini. Alur nyata apotek **tidak lewat dokumen PO ke PBF** — pesan lewat
telepon/WA ke sales, faktur datang bersama barang — jadi RO harus bisa berdiri sendiri, dan PO hanya
pengisi awal.

### 2.1 Keputusan

| # | Keputusan | Dasar |
|---|-----------|-------|
| R1 | Baris RO: Obat, **Satuan input** (kemasan bawaan obat *atau* satuan jual), **Isi per kemasan**, Jumlah, Harga per satuan input, No. Batch, ED. Sistem menampilkan hasil konversi (jumlah satuan jual, harga per satuan jual, subtotal). Tersimpan `qty` dan `price` **dalam satuan jual** + jejak konversi | Petugas mengetik apa yang tercetak di faktur; sistem yang mengalikan. Lihat §1.5 |
| R1a | `medicine_name` di item RO tetap sebagai snapshot | Nama obat bisa diedit di master; faktur lama harus tetap terbaca |
| R1b | Cetakan RO seperti faktur: kemasan, tanpa kolom konversi | Sepadan dengan dokumen PBF |
| R2 | `manufacture_date` **dihapus** | Tidak ada di faktur maupun sistem pembanding |
| R3 | ED diinput **bulan-tahun**, disimpan sebagai **tanggal 1** bulan itu — obat dianggap kedaluwarsa sejak awal bulan | Faktur hanya mencetak MM-YY. Tafsir konservatif: C3 dan notifikasi ±30 hari lebih pendek daripada akhir bulan |
| R4 | ED **wajib**, dan harus **> tanggal terima** (Q4) | Bila faktur kosong (RECO TM, ALLOPURINOL IFI), petugas membaca fisik kemasan. ED sebelum tanggal terima tidak mungkin secara fisik |
| R5 | Header: nomor RO (otomatis), **nomor faktur PBF** (wajib, unik per supplier), PBF, tanggal terima, PO (opsional), `received_by`. **Hapus** `description`, `late_arrival`, `status` | Status selalu *completed* — RO menulis kartu stok saat disimpan; kolom tanpa makna dihapus, bukan dikonstankan |
| R6 | Jejak konversi per baris: `pack_unit_id`, `pack_size`, `pack_qty` | Isi kemasan PBF bisa berubah; faktur harus tetap bisa direkonsiliasi |
| R7 | `medicine_stocks.receive_order_item_id` — **satu baris D per item RO = per batch** | Fondasi C3 batch terjauh dan Section 13 Tahap 3. `recordReceipt()` sudah menulis per item; hanya kurang kolomnya |
| R8 | Edit/hapus RO: **batch/ED/harga selalu boleh** (harga → `hpp` baris D ikut, C4 ikut). **Jumlah atau hapus RO hanya bila belum ada baris C yang menunjuk item RO itu**; selebihnya koreksi lewat Stok Opname | Menghapus lapisan yang sudah terjual membuat stok negatif diam-diam. Opname adalah jalur koreksi yang disepakati wawancara |
| R9 | PO **mengikuti format baris RO**; sisa PO dihitung dalam satuan jual. RO dari PO: petugas **mencentang** item dari daftar sisa PO, bukan menerima semua sisa lalu menghapus. Baris yang berasal dari PO **ditolak bila melebihi sisa** (validasi yang ada dipertahankan); kelebihan kiriman dicatat sebagai baris di luar PO (Q6) | PBF memecah pesanan jadi banyak faktur (maks 14 baris/lembar + Prekursor/OOT wajib terpisah). Sisa PO tetap bermakna |
| R10 | **Satu faktur = satu RO**, berapa pun jumlah faktur sehari (data: 8 faktur pada 11-05-2024). Faktur revisi = edit RO yang ada | Faktur adalah satuan rekonsiliasi (subtotal RO = Total faktur), satuan regulasi (Prekursor/OOT), dan satuan revisi. Bentuk "satu RO banyak faktur" (faktur per baris / nested repeater) ditolak: tidak mengurangi ketikan, menambah struktur, dan mematikan pengisian dari PO |
| R11 | Petugas **boleh menambah baris di luar PO** | Faktur adalah kebenaran, PO hanya pengisi awal |
| R12 | **Simpan & buat lagi** membawa PO/PBF/tanggal dari RO sebelumnya; header berikutnya tinggal nomor faktur | Menghapus biaya "delapan header sehari" tanpa mengubah struktur |
| R13 | PPN **tidak dihitung dan tidak disimpan**. Harga faktur sudah termasuk PPN (bukti §1.4). Cetakan RO menampilkan DPP = Total ÷ (1 + tarif) dan PPN = Total − DPP, **dihitung saat tampil**, tarif dari Pengaturan Umum (`ppn_rate`, 11%) | Sepadan dengan faktur tanpa menyentuh harga beli/C4 |
| R14 | Ongkos kirim: **tidak ada field** | Nol di 14 faktur; PBF mengantar sendiri. Tambah di header RO bila suatu hari muncul |
| R15 | Nomor batch yang sama di dua faktur (LOSTACEF 31232 pada 08-05 dan 11-05) → **dua lapisan terpisah**, tidak digabung | ED sama, urutan FEFO di antara keduanya tidak penting; laporan stok batch mengelompokkan per nomor batch saat tampil |

### 2.2 Bentuk form

**Header**

| Field | Wajib | Catatan |
|---|---|---|
| Nomor RO | sistem | `RO{YYYYMMDD}-XXXX`, banyak RO per hari |
| PO | – | Memilih PO membawa PBF dan daftar sisa item |
| PBF | ✔ | Terisi dari PO bila ada |
| Nomor faktur PBF | ✔ | Unik per PBF |
| Tanggal terima | ✔ | |

**Pilih item dari PO** (hanya bila PO dipilih) — daftar sisa PO dengan kolom dipesan / sudah diterima
/ sisa, petugas mencentang yang ada di faktur. Baris terbentuk dengan kemasan dan harga dari PO.

**Baris item**

| Kolom | Diisi | Bawaan |
|---|---|---|
| Obat | petugas / dari PO | — |
| Satuan input | petugas | kemasan bawaan obat |
| Isi per kemasan | petugas | dari master; nonaktif bila satuan input = satuan jual |
| Jumlah | petugas | dari sisa PO bila dari PO |
| Harga per satuan input | petugas | dari PO bila ada, kalau tidak `latestPurchasePrice()` × isi |
| No. Batch | petugas | — |
| ED (bulan-tahun) | petugas | — |
| *= jumlah satuan jual* | sistem | |
| *= harga per satuan jual* | sistem | |
| *Subtotal* | sistem | |

**Footer**: Total RO (Σ baris, tampil saja — petugas mencocokkan dengan faktur secara manual; tidak
ada field "Total menurut faktur" dan tidak ada validasi). Tombol **+ Tambah item di luar PO**,
**Simpan**, **Simpan & buat lagi**.

### 2.3 Skema

| Tabel | Perubahan |
|---|---|
| `receive_orders` | **tambah** `invoice_number` (unique `[supplier_id, invoice_number]`); **drop** `description`, `late_arrival`, `status` |
| `receive_order_items` | **tambah** `pack_unit_id` (FK `units`), `pack_size`, `pack_qty`; **drop** `manufacture_date`; `expired_date` NOT NULL dan **dinormalkan ke tanggal 1** untuk data lama (Q5); `qty`/`price` tetap (dalam satuan jual) |
| `medicine_stocks` | **tambah** pada baris D: `receive_order_item_id` (FK nullable, jejak asal), `batch_number`, `expired_date` (disalin ke **semua** baris D — dari RO maupun opname, C5); pada baris C: `layer_stock_id` (FK ke baris D, D1). Lihat §5.4 |
| `general_settings` | **tambah** `ppn_rate` (decimal, default 11) |

Backfill: baris D lama mendapat `receive_order_item_id` + batch/ED dengan mencocokkan
`receive_order_id` + `medicine_id` (satu item per obat per RO pada data lama). Baris C lama
diatribusikan lewat replay FEFO (F6).

### 2.4 Berkas yang tersentuh

| Aksi | Berkas |
|------|--------|
| Migration | 4 perubahan skema di atas + backfill `receive_order_item_id` |
| Model | `ReceiveOrder` (fillable, hapus status), `ReceiveOrderItem` (fillable, cast, relasi `packUnit`), `MedicineStock` (relasi `receiveOrderItem`, `layer`; accessor `remaining` pada baris D) |
| Form | `ReceiveOrderForm` — header baru, komponen centang item PO, baris dengan konversi, ED bulan-tahun, aturan R8; **tanpa** validasi total |
| Service | `StockMovementService::recordReceipt()` — tulis lapisan (jejak item, batch, ED); `reverseReceipt()` baru dengan cek R8, **menghapus baris D sungguhan** lalu replay HPP (B5) |
| Cetak | `print-receive-order.blade.php` — kemasan, DPP/PPN hitung tampil |
| Export | `ReceiveOrderExporter` — kolom baru, hapus status |
| Settings | `GeneralSettings` + `ManageGeneralSettings` — `ppn_rate` |
| Widget | `PendingPurchaseOrdersWidget` (definisi "belum lengkap" ikut Bagian 3) |
| Seeder | `SpkTestDataSeeder`, `DemoApotekSeeder` — RO dengan `invoice_number`, jejak kemasan, tanpa `manufacture_date` |
| Test | `StockMovementServiceTest` — atribusi item, aturan R8 |

### 2.5 Dampak ke modul lain

| Modul | Dampak |
|---|---|
| SAW C3 | `farthestExpiryDate()` membaca **sisa per lapisan** (`SUM(D) − SUM(C)` per `layer_stock_id`, Bagian 5) |
| SAW C4 | Baris D dari RO memperbarui `hpp_avg` (Bagian 4) — C4 ikut berubah pada perhitungan berikutnya |
| PO | Sisa dihitung dalam satuan jual; status penerimaan turunan (Bagian 3) |
| Notifikasi ED | Hanya lapisan dengan sisa > 0 (F5) |
| Stok opname | Jalur resmi koreksi jumlah RO yang sudah terjual (R8) |
| Laporan pembelian | Kolom kemasan tersedia dari jejak konversi |

### 2.6 Verifikasi

- [ ] RO 5 Box @ 41.000 (isi 10) → item `qty` 50, `price` 4.100; kartu stok D 50 dengan `receive_order_item_id`; `latestPurchasePrice()` = 4.100
- [ ] RO 5 Strip (satuan jual) @ 4.100 → item `qty` 5, `price` 4.100
- [ ] ED `10-2026` → tersimpan `2026-10-01`
- [ ] Nomor faktur sama untuk PBF sama → ditolak; PBF berbeda → diterima
- [ ] RO dari PO: hanya item yang dicentang yang jadi baris; sisa PO berkurang sesuai satuan jual
- [ ] Hapus RO yang batch-nya sudah terjual → ditolak dengan pesan; ubah ED-nya → boleh
- [ ] Simpan & buat lagi → PO/PBF/tanggal terbawa
- [ ] Cetakan RO menampilkan DPP/PPN yang jumlahnya = Total

### 2.7 Hal terbuka

- Tidak ada. Atribusi batch pada baris C dibahas di Bagian 5.

Catatan: pembandingan Total RO dengan Total faktur **tidak dibuat** — pengecekan awal dan revisi
dilakukan petugas secara manual. Faktur revisi pada data contoh (01955, 07-05-2024) menunjukkan
revisi PBF menyangkut *isi kiriman* (jumlah dipangkas), bukan salah hitung; totalnya selalu
konsisten dengan barisnya.

---

## 3. Pemesanan (Purchase Order)

Dari wawancara: apotek **menelepon/WA sales dulu untuk menanyakan ketersediaan**, baru memesan.
Maka PO **bukan dokumen ke PBF** — PBF tidak pernah melihatnya. PO adalah catatan internal
"setelah dikonfirmasi sales, apotek memesan barang-barang ini ke PBF X", sekaligus pengikat
banyak RO yang memenuhinya.

### 3.1 Keputusan

| # | Keputusan | Dasar |
|---|-----------|-------|
| P1 | PO = catatan internal **per PBF**, dibuat **setelah** konfirmasi WA; isinya hanya obat yang sudah dipastikan tersedia | Obat yang kosong di PBF A ditanyakan ke PBF B → PO terpisah (wawancara §4.1) |
| P2 | **Cetak PO dihapus** (`print-purchase-order.blade.php`) | Tidak ada penerimanya |
| P3 | **Hapus** kolom `sub_total`, `discount`, `tax`, `total_tax`, `shipping_cost`, `other_cost`, `grand_total`, `estimated_arrival`, `status_payment`, `description` (header & item), `discount` & `total` (item) | Angka sebenarnya ada di faktur → RO. Pembayaran selalu lunas; hutang di luar cakupan (PRD). Total PO bila perlu dihitung saat tampil dari baris |
| P4 | Baris PO **mengikuti format baris RO**: Obat, Satuan beli (kemasan bawaan / satuan jual), Isi, Jumlah, Harga perkiraan. Tersimpan `qty` & `price` dalam satuan jual + jejak `pack_unit_id`/`pack_size`/`pack_qty` | Jebakan CLAUDE.md §13.6 #2: sisa PO harus dibandingkan dalam satuan yang sama |
| P5 | Jumlah bawaan baris = **⌈`min_stock` ÷ isi⌉ kemasan**, diketik ulang | "Isi ulang sampai batas minimum". Strip bawaan (20 ÷ 10) → 2 Box; satuan lain bawaan (= isi kemasan) → 1 |
| P6 | Harga di PO = **perkiraan** dari `latestPurchasePrice()` × isi, boleh diubah. Harga beli sesungguhnya (C4) **selalu dari RO** | PO dibuat sebelum faktur; RO menimpa |
| P7 | Status penerimaan **turunan** dari sisa: *belum diterima* → *sebagian* → *lengkap*; plus *ditutup* | Tidak diubah tangan |
| P8 | **Tutup PO** = bulk action di daftar PO untuk sisa yang tidak akan datang; sisa dicatat sebagai tidak terpenuhi | Jarang terjadi karena ketersediaan sudah dikonfirmasi WA, tapi PO tidak boleh menggantung |
| P9 | Di tabel ranking SAW: **centang obat → "Buat PO" → pilih PBF** → PO terbentuk dengan kemasan bawaan dan jumlah P5 | Jembatan *choice* → *implementation*. Apoteker yang mencentang (setelah WA), sistem tidak memutuskan untuknya |
| P10 | Di tabel ranking: penanda **"sudah dipesan"** untuk obat yang ada di PO terbuka (belum lengkap, belum ditutup) | Mencegah pesan dua kali; memperlihatkan lingkaran berjalan |
| P11 | **Tidak** ada `saw_calculation_id` di PO | Keputusan peneliti. Konsekuensi: Bab IV tidak bisa menautkan PO ke snapshot perhitungan tertentu secara langsung |
| P12 | Satu PO → banyak RO (satu per faktur); RO boleh menambah baris di luar PO (R11) | Lihat R9–R11 |

**Batasan yang harus ditulis di Bab 1.4**: SAW menentukan **urutan** restock, bukan **jumlah**. Jumlah
pesanan di luar metode; Bab 5.2 boleh menyarankan EOQ atau permintaan × lead time.

### 3.2 Skema `purchase_orders` sesudah revisi

| Kolom | Status |
|---|---|
| `po_number`, `supplier_id`, `po_date`, `created_by` | tetap |
| `status_receive_order` | tetap, nilai: `pending` / `partial` / `received` / **`closed`** (baru) |
| `status` (draft/approved/cancelled/completed) | **drop** — PO yang dibuat sudah keputusan final (pasca-WA); pembatalan = Tutup PO. Pemakai lain hanya widget PO Tertunda & filter tabel PO → pindah ke `status_receive_order` |
| `sub_total`, `discount`, `tax`, `total_tax`, `shipping_cost`, `other_cost`, `grand_total`, `estimated_arrival`, `status_payment`, `description` | **drop** |

`purchase_order_items`: `medicine_id`, `qty` (satuan jual), `price` (per satuan jual), **tambah**
`pack_unit_id`, `pack_size`, `pack_qty`; **drop** `description`, `discount`, `total`.

### 3.3 Bentuk form

| Field | Wajib | Catatan |
|---|---|---|
| Nomor PO | sistem | |
| PBF | ✔ | |
| Tanggal pesan | ✔ | = tanggal WA. Bersama tanggal RO memberi **lead time terukur per PBF** — bahan Bab 5.2 |
| Baris: Obat · Satuan beli · Isi · Jumlah · Harga perkiraan | ✔ | Sesuai P4–P6 |

Total PO ditampilkan dari Σ baris, tidak disimpan.

### 3.4 Berkas yang tersentuh

| Aksi | Berkas |
|------|--------|
| Migration | drop/tambah kolom §3.2; migrasi nilai status |
| Model | `PurchaseOrder` (fillable, accessor sisa per item & status turunan), `PurchaseOrderItem` |
| Form | `PurchaseOrderForm` — hapus seksi Perhitungan & Keterangan, baris format RO, jumlah bawaan P5 |
| Table | `PurchaseOrdersTable` — bulk action Tutup PO, kolom status turunan |
| Hapus | `resources/views/print/print-purchase-order.blade.php` + action cetaknya |
| SAW page | `SawCalculation.php` — bulk action "Buat PO" (modal pilih PBF), kolom/badge "sudah dipesan" |
| Widget | `PendingPurchaseOrdersWidget` — definisi: `status_receive_order` in (`pending`, `partial`) |
| Seeder | `SpkTestDataSeeder`, `DemoApotekSeeder` |
| Dokumen | PRD F-03 & Flow 1; Bab III alur pemesanan; Bab 1.4 batasan jumlah |

### 3.5 Hal terbuka

- Nomor PO tetap `PO{YYYYMMDD}-XXXX`? Tidak ada alasan mengubah.

---

## 4. Penjualan dan HPP

Modul penjualan dibutuhkan skripsi hanya untuk dua hal: **mengurangi stok** (C1) dan **mencatat
permintaan** (C2). Semua yang lain — nama pembeli, struk, aturan margin — di luar cakupan sebelum
sidang. HPP dibahas di sini karena hidup di kartu stok dan **dipakai SAW sebagai C4** (M4).

### 4.1 Keputusan

| # | Keputusan | Dasar |
|---|-----------|-------|
| S1 | Kolom `status` (pending/paid/cancelled) **dihapus** | Penjualan apotek selalu lunas saat barang diserahkan; tidak ada retur. Pembatalan = hapus (order soft-delete, baris C kartu stok dihapus sungguhan — B5) |
| S2 | `no_payment` **dihapus** | Tidak ada sumbernya; `order_code` sudah unik |
| S3 | Harga **diketik kasir**, divalidasi **≥ HPP** saat itu. `discount` per baris **dihapus** (ada di DB, tidak di form) | M5 |
| S4 | **HPP = rata-rata bergerak (Metode A)**, disimpan sebagai kolom `hpp_avg` (bilangan bulat, dibulatkan ke atas — K14) di `medicine_stocks`, diperbarui di setiap baris. Tidak ada modul terpisah — kartu stok per obat *adalah* riwayat pembelian + HPP | Metode A adalah metode biaya baku (rata-rata bergerak), tidak terikat pada batch, dan memberi angka yang sama dengan rumus peneliti di setiap penerimaan. Metode B (rata-rata lapisan tersisa) ditolak agar biaya tidak bergantung pada alokasi FEFO (F7) |
| S5 | Baris penjualan selalu dalam **satuan jual**, tanpa pilihan kemasan | M3. Pelanggan beli 1 box → kasir mengetik 10 strip (jarang terjadi) |
| S6 | Penjualan **tidak bisa diedit**. Salah input → hapus → buat ulang | Praktik apotek; menyederhanakan pembalikan stok, dan alokasi FEFO nanti tidak perlu "mengembalikan ke lapisan mana" |
| S7 | Tidak dibangun: nama pembeli, cetak struk, aturan margin | Di luar topik |

### 4.2 Bentuk form penjualan

| Field | Wajib | Catatan |
|---|---|---|
| Kode | sistem | `ORD{YYYYMMDD}-XXXX` |
| Tanggal | ✔ | Bawaan hari ini, boleh diubah mundur (menyusulkan penjualan kemarin). Alokasi FEFO selalu berdasarkan lapisan **saat diketik**, bukan saat tanggalnya; replay HPP mengikuti tanggal (Q7) |
| Baris: Obat · Jumlah (satuan jual) · Harga | ✔ | Harga ≥ HPP; pesan penolakan menyebut angka HPP-nya |
| Catatan | – | |
| Total | sistem | Σ baris, tampil |

Aksi: **Simpan**, **Hapus**. Tidak ada Edit.

### 4.3 HPP rata-rata bergerak — rumus dan contoh

Setiap baris **D** dari penerimaan memperbarui HPP:

```
HPP_baru = (Saldo_lama × HPP_lama + Qty_masuk × Harga_masuk) / (Saldo_lama + Qty_masuk)
```

`Saldo` = **stok fisik** (Σ D − Σ C seluruh lapisan, termasuk yang kedaluwarsa dan belum dimusnahkan)
— pembagi akuntansi, bukan stok tersedia (Q2). Hasil dibulatkan **ke atas** ke rupiah bulat (K14).

Baris **C** (penjualan, opname keluar) **tidak mengubah** HPP — hanya menyalinnya ke kolom `hpp`
baris itu (harga pokok barang yang keluar). Baris **D dari opname masuk** memakai HPP saat itu
sebagai harganya (koreksi jumlah tidak mengubah biaya). Saldo 0 lalu ada penerimaan → HPP = harga
penerimaan itu.

Contoh Obat A (satuan jual: strip):

| Tgl | Keterangan | D | C | Harga | Saldo | Hitungan | **HPP** |
|---|---|---|---|---|---|---|---|
| 1/9 | RO batch 1 | 5 | | 22.000 | 5 | saldo 0 → = harga | **22.000** |
| 5/9 | RO batch 2 | 20 | | 16.000 | 25 | (5×22.000 + 20×16.000) / 25 | **17.200** |
| 6/9 | Penjualan | | 5 | hpp = 17.200 | 20 | tidak berubah | 17.200 |
| 8/9 | Penjualan | | 10 | hpp = 17.200 | 10 | tidak berubah | 17.200 |
| 9/9 | RO batch 3 | 10 | | 20.000 | 20 | (10×17.200 + 10×20.000) / 20 | **18.600** |
| 12/9 | Opname keluar (rusak) | | 2 | hpp = 18.600 | 18 | tidak berubah | 18.600 |
| 15/9 | Penjualan | | 18 | hpp = 18.600 | 0 | tidak berubah | 18.600 |
| 20/9 | RO batch 4 | 10 | | 19.000 | 10 | saldo 0 → = harga | **19.000** |

Pada 15/9 stok 0 tapi HPP tetap 18.600 — obat berstok 0 **tidak kehilangan C4**.

**C4 untuk SAW** = `hpp_avg` baris terakhir obat itu pada saat perhitungan. Pada contoh: dihitung
10/9 → 18.600; dihitung 21/9 → 19.000.

**Validasi harga jual** pada 6/9: harga ≥ 17.200.

**Perhitungan ulang (replay).** `hpp_avg` bergantung pada urutan dan saldo, jadi setiap perubahan pada
baris yang bukan baris terakhir — koreksi harga RO (R8), hapus penjualan yang terjadi *sebelum* suatu
penerimaan, hapus RO — memicu hitung ulang `hpp_avg` seluruh baris obat itu dari awal. Ledger per obat
kecil (puluhan–ratusan baris), jadi murah. Aturan tunggal: **setiap mutasi kartu stok → replay obat
itu.** Ini juga membuat backfill data lama sekadar menjalankan replay untuk semua obat.

Kenapa penjualan yang dihapus bisa mengubah HPP: pada 5/9, kalau ternyata penjualan 5 strip (yang
sebelumnya tercatat 3/9) dihapus, saldo saat batch 2 masuk bukan lagi 5 melainkan 10 → pembagi
berubah → HPP berubah. Replay menangani ini tanpa kasus khusus.

### 4.4 Skema

| Tabel | Perubahan |
|---|---|
| `orders` | **drop** `status`, `no_payment`; **pertahankan** `grand_total` sebagai cache (tidak pernah basi karena tidak ada edit, S6; dibaca widget penjualan) |
| `order_items` | **drop** `discount`, `total` (= qty × price, hitung saat tampil) |
| `medicine_stocks` | **tambah** `hpp_avg` (unsigned int rupiah, dibulatkan ke atas pada tiap langkah — K14; nullable hanya untuk baris lama sebelum backfill); `hpp` baris C kini = harga pokok, bukan harga jual |

Backfill: replay `hpp_avg` untuk semua obat dari baris D pertama; `hpp` baris C lama ditimpa dengan
`hpp_avg` pada posisinya.

### 4.5 Berkas yang tersentuh

| Aksi | Berkas |
|------|--------|
| Migration | drop/tambah kolom §4.4 + backfill replay |
| Service | `StockMovementService` — `recordReceipt()` menulis `hpp_avg`; `recordSale()` menulis `hpp = hpp_avg`; method `replayHpp(medicineId)` dipanggil setelah setiap mutasi; `reverseSale()` **menghapus baris C sungguhan** (B5) lalu replay. Setelah B5, `currentStock()`/`availableStock()` tidak lagi butuh saringan `whereHas` ke dokumen induk |
| Service | `StockCardService` — kolom HPP di kartu stok; `currentHpp(medicineId)` |
| SAW | `SawCalculationService::getRawValue('C4')` → `currentHpp()` |
| Form | `OrderForm` — hapus `no_payment`, status; harga diketik + rule ≥ HPP; hapus kolom `total`/`discount` |
| Pages | `CreateOrder` tetap; **`EditOrder` dihapus** beserta rute & action edit di tabel |
| Opname | `MedicineStockOpnameForm` — hpp penyesuaian = `currentHpp()` |
| Page | `MedicineStockDetail` — kolom HPP |
| Laporan | `LaporanRekap` — margin kini dari `hpp` baris C yang benar |
| Seeder | `SpkTestDataSeeder`, `DemoApotekSeeder` — orders tanpa status/no_payment; jalankan replay di akhir |
| Test | `StockLedgerTest` — tambah karakterisasi rumus §4.3 persis tabel contoh; `StockMovementServiceTest` — replay setelah hapus |
| Dokumen | Bab III definisi operasional C4 (HPP rata-rata bergerak); `update-dari-wawancara.md` §4.1; PRD F-03 |

### 4.6 Dampak ke modul lain

| Modul | Dampak |
|---|---|
| SAW C4 | Definisi baru: HPP rata-rata bergerak per satuan jual. Angka contoh Bab 3.4.4 harus dihitung ulang dengan definisi ini |
| SAW C2 | Tidak berubah: Σ qty order dalam periode → proyeksi 30 hari. Filter `status != cancelled` dihapus (kolomnya tidak ada lagi; order batal = soft delete, sudah tidak terhitung) |
| Laporan rekap | Margin = Σ (harga jual − hpp) × qty — kini bermakna |
| Widget penjualan | Tidak ada filter status lagi |
| Section 13 Tahap 4 | Saat FEFO masuk, `hpp` baris C **tetap** `hpp_avg` (metode tidak diganti di tengah jalan); atribusi batch hanya untuk sisa per batch, bukan untuk biaya |

### 4.7 Verifikasi

- [ ] Tabel contoh §4.3 direproduksi persis oleh test (8 baris, 4 nilai HPP)
- [ ] Menjual di bawah HPP → ditolak dengan pesan berisi angka HPP
- [ ] Hapus penjualan 3/9 (sebelum batch 2) → HPP 5/9 berubah sesuai replay
- [ ] Obat stok 0 → `currentHpp()` = nilai terakhir, C4 tidak null
- [ ] Obat tanpa baris kartu stok → tidak ikut SAW (K0); opname penambahan pada obat itu ditolak (§7.3)
- [ ] Hapus penjualan → baris C-nya hilang dari `medicine_stocks` (bukan disaring), order tetap soft-deleted, `hpp_avg` di-replay
- [ ] Tidak ada rute/action edit penjualan
- [ ] `hpp` semua baris C = `hpp_avg` pada posisinya (query pembanding = 0 selisih)

### 4.8 Hal terbuka

- ~~`subtotal` di `orders`~~ → sudah bernama `grand_total`; dipertahankan (D3).
- Pertimbangan untuk Bab III: C4 = HPP rata-rata bergerak berarti C4 mengikuti *modal yang tertanam*, bukan *harga pesanan berikutnya*. Argumen ini perlu satu paragraf, karena wawancara §4.1 semula mengarah ke harga penerimaan terakhir.

---

## 5. Stok per Batch dan FEFO

Menarik Section 13 Tahap 3–6 CLAUDE.md ke **sebelum sidang**. Ongkosnya turun drastis karena
keputusan sebelumnya: R7 memberi lapisan pada baris D, S6 meniadakan edit penjualan (tidak ada alokasi
ulang), S4 memisahkan biaya dari batch (alokasi tidak menyentuh HPP). Alasan menariknya: batch
bercampur sudah dikonfirmasi lapangan, dan tanpa ini C3 bersandar pada *argumen* ("kalau FEFO
dijalankan, maka batch terjauh pasti bersisa"), bukan *perhitungan*.

### 5.1 Keputusan

| # | Keputusan | Dasar |
|---|-----------|-------|
| F0 | Penjualan **dialokasikan FEFO oleh sistem** (Opsi B), bukan dipilih kasir (cara Winong). Kasir mengetik jumlah; sistem memecah ke batch dengan ED terdekat lebih dulu dan **menampilkan** hasilnya (batch, ED, sisa hari) | C3 dan notifikasi hanya benar kalau kartu stok mencerminkan batch yang benar-benar keluar. Di Opsi A kebenaran itu bergantung klik kasir — sumber "salah tulis" yang disebut wawancara |
| F1 | Batch yang **sudah lewat ED tidak dialokasikan** untuk dijual. "Belum kedaluwarsa" = `expired_date > hari ini` (B1 — konsisten dengan R3: kedaluwarsa **sejak** tanggal 1). Sisa batch kedaluwarsa dikeluarkan lewat opname (pemusnahan/retur) | Keputusan wawancara #4 (tidak ada modul retur/pemusnahan) |
| F2 | **Dua pengertian stok, dua nama** (B4): *stok fisik* = Σ D − Σ C seluruh lapisan (saldo kartu stok, dasar opname); *stok tersedia* = sisa lapisan belum kedaluwarsa. **C1, validasi jual, alokasi FEFO, notifikasi stok minimum, `stock_status`, widget Low Stock, dan laporan fast/slow memakai stok tersedia** | Stok yang seluruhnya kedaluwarsa secara restock = stok 0. Satu kalimat di definisi operasional C1 Bab III |
| F3 | Opname **sadar batch**: pengurangan menyebut lapisan mana; penambahan wajib batch + ED **dan hanya untuk obat yang sudah punya HPP** (§7.3). Lapisan tanpa ED hanya dari data lama, dikonsumsi **paling dulu** | "Semua obat yang diopname punya ED" (peneliti). = Section 13 Tahap 5 |
| F4 | Baris **C** kartu stok terpecah per lapisan (`layer_stock_id` → baris D, D1); kartu stok menampilkan kolom batch/ED dan bisa difilter per batch; sisa per lapisan = Σ D − Σ C per `layer_stock_id` | = Section 13 Tahap 6, turunan |
| F5 | Notifikasi kedaluwarsa hanya untuk batch dengan **sisa > 0** | Sebelumnya membaca semua `receive_order_items` belum lewat ED, termasuk yang sudah habis terjual |
| F6 | Backfill: seluruh baris C lama diputar ulang secara FEFO urut tanggal lalu `id`, diatribusikan ke lapisan yang ada; lapisan tanpa ED (opname lama) dikonsumsi lebih dulu | Deterministik karena semua baris D sudah punya lapisan (R7) |
| F7 | `hpp` baris C **tetap rata-rata bergerak** (S4); atribusi batch hanya untuk jumlah | Metode biaya tidak diganti di tengah jalan |

### 5.2 Alokasi penjualan — contoh

Obat A: batch 1 (ED 05-2027) sisa 5, batch 2 (ED 12-2027) sisa 20. Kasir mengetik 7.

```
Obat: OBAT A            Sisa: 25 strip dalam 2 batch (belum kedaluwarsa)
Jumlah: [ 7 ]
  → Ambil 5 dari batch T10088BC  ED 05-2027  (230 hari)
  → Ambil 2 dari batch Z99       ED 12-2027  (440 hari)
Harga: [ 18.000 ]   (HPP 17.200)
```

Tersimpan dua baris C, keduanya milik order yang sama:

| Baris | C | `layer_stock_id` | hpp |
|---|---|---|---|
| Penjualan ORD-…-0031 | 5 | baris D batch 1 | 17.200 |
| Penjualan ORD-…-0031 | 2 | baris D batch 2 | 17.200 |

Sisa sesudahnya: batch 1 = 0, batch 2 = 18. Hapus penjualan → kedua baris C dihapus (B5) → sisa pulih.

Algoritma `allocateFefo(medicineId, qty)`: ambil lapisan (baris D) dengan sisa > 0 dan
`expired_date > hari ini` (lapisan tanpa ED ikut, diurutkan paling depan), urut ED naik lalu `id`;
kurangi berurutan sampai `qty` terpenuhi; bila total sisa < `qty` → tolak (validasi ketersediaan
yang sudah ada, kini menghitung stok tersedia).

### 5.3 Opname per lapisan (hitung fisik, Q3)

Bentuknya tetap **hitung fisik** seperti opname sekarang, hanya turun ke tingkat lapisan. Memilih obat
menampilkan semua lapisannya; petugas mengisi jumlah fisik per lapisan; selisih menjadi baris kartu
stok pada lapisan itu.

| Baris di form | Diisi petugas | Efek |
|---|---|---|
| Lapisan yang ada — batch · ED · **sisa sistem** | **jumlah fisik** | Fisik < sistem → baris **C** menunjuk lapisan itu (rusak, hilang, dimusnahkan, diretur — termasuk lapisan kedaluwarsa, satu-satunya jalan mengeluarkannya). Fisik > sistem → baris **D** pada lapisan itu dengan `hpp = hpp_avg` (S4). Sama → tidak ada baris |
| **+ Batch baru** — batch + ED baru | jumlah fisik | Lapisan baru: baris D dengan `batch_number`, `expired_date`, `hpp = hpp_avg`. Hanya untuk obat yang sudah punya HPP; **saldo awal obat baru lewat RO** (§7.3) |

### 5.4 Skema

| Tabel | Perubahan |
|---|---|
| `medicine_stocks` | **Lapisan = baris D** (D1). Baris D: `batch_number`, `expired_date` (semua baris D, C5), `receive_order_item_id` (jejak asal bila dari RO). Baris C: `layer_stock_id` (FK ke baris D, index) |
| `medicine_stock_opname_items` | **tambah** `layer_stock_id` (pengurangan), `batch_number` + `expired_date` (penambahan) |
| `receive_order_items` | tidak berubah |

### 5.5 Berkas yang tersentuh

| Aksi | Berkas |
|------|--------|
| Service | `StockMovementService` — `allocateFefo()`, `recordSale()` memecah baris, `recordOpname()` per lapisan; `StockCardService` — `physicalStock()`, `availableStock()` (stok tersedia), `layers(medicineId)`; `updateMedicineStockStatus()` memakai stok tersedia (B4) |
| Model | `Medicine` — `farthestExpiryDate()` membaca sisa per lapisan (menggantikan `nearestExpiryDate()`); `MedicineStock` — scope `layers()`, accessor `remaining` |
| Form | `OrderForm` — panel alokasi (tampil, tidak dipilih); `MedicineStockOpnameForm` — pilih lapisan / batch+ED baru; tolak penambahan pada obat tanpa HPP |
| Page | `MedicineStockDetail` — kolom batch/ED, filter per batch, ringkasan sisa per batch |
| Command | `CheckStockAndExpiryCommand` — **hitung ulang `stock_status` semua obat lebih dulu** (batch bisa kedaluwarsa tanpa mutasi, Q1); lapisan dengan sisa > 0 (F5); stok minimum memakai stok tersedia (B4) |
| Widget | `ExpiringMedicinesWidget`, `LowStockMedicinesWidget` — idem |
| Laporan | `LaporanMoving` — stok tersedia (B4) |
| Migration | kolom §5.4 + backfill F6 |
| Test | alokasi 7 = 5 + 2; batch kedaluwarsa dilewati; tolak bila sisa belum-kedaluwarsa < qty; hapus penjualan memulihkan lapisan; backfill deterministik |
| Dokumen | CLAUDE.md §5.4 dicabut, §13 ditandai dieksekusi; Bab 1.4 hapus batasan "stok tidak dilacak per batch"; Bab III definisi C1 & C3 |

### 5.6 Hal terbuka

- **Lapisan dari opname penambahan**: butuh tempat menyimpan batch + ED tanpa RO. Dua pilihan:
  (a) `medicine_stocks` baris D membawa `batch_number` + `expired_date` sendiri bila `receive_order_item_id` null — lapisan = baris D itu sendiri; atau
  (b) tabel `medicine_batches` sebagai sumber lapisan tunggal, diisi dari RO maupun opname.
  **Diputuskan (a)** — lihat D1 di Bagian 6.
- Dampak ke angka Bab IV: hampir nol — sisa per batch sungguhan = aturan "batch terjauh bersisa selama stok > 0" kecuali ada batch kedaluwarsa/opname. C1 berubah hanya bila ada stok kedaluwarsa (F2), dan itu perubahan yang lebih benar.

---

## 6. Keputusan Lintas Modul (D1–D12, 2026-09-13)

Semua hal yang sempat ditunda, diputuskan sekaligus supaya tidak ada pekerjaan yang "menunggu".

| # | Hal | Keputusan |
|---|-----|-----------|
| D1 | Lapisan dari opname penambahan (tanpa RO) | Baris **D** di `medicine_stocks` membawa `batch_number` + `expired_date` sendiri bila bukan dari RO. **Lapisan = baris D mana pun** (RO atau opname). Penunjuk di baris C: `layer_stock_id` → FK ke baris D (menggantikan `receive_order_item_id` pada baris C; pada baris D dari RO, `receive_order_item_id` tetap sebagai jejak asal). Tanpa tabel baru |
| D2 | Aturan margin harga jual | **Tidak dibangun.** Validasi ≥ HPP cukup; margin adalah kebijakan apotek |
| D3 | `orders.grand_total` / `order_items.total` | `grand_total` dipertahankan (cache yang tidak pernah basi karena S6); `order_items.total` dan `discount` dihapus |
| D4 | Nomor PO | Tetap `PO{YYYYMMDD}-XXXX` |
| D5 | Jalur masuk data riil Bab IV | Template Excel 4 sheet — **Obat** (nama, kategori, satuan jual, kemasan, isi), **Faktur** (no faktur, PBF, tanggal, obat, kemasan, jumlah, harga, batch, ED), **Stok** (obat, batch, ED, sisa — hanya bila kartu stok memberi sisa per batch; kalau tidak, sisa per obat dan dialokasikan ke batch terjauh), **Penjualan** (tanggal, obat, jumlah). Seeder sekali pakai membacanya, dedup obat berdasarkan nama. Dibangun sekarang, diuji dengan faktur Mei 2024; bentuk final dikunci setelah foto kartu stok |
| D6 | Database | Target deploy **MySQL di VPS**. Kode ditulis netral (tanpa fitur khusus vendor); catatan: `LIKE` MySQL tidak peka huruf, generator kode `OBT-%` aman |
| D7 | Roles & permissions | Peneliti menangani sendiri via Filament Shield. Notifikasi tetap ke semua user |
| D8 | Cron | Dijalankan tiap pagi di server; bukan pekerjaan kode |
| D9 | Ketepatan golongan obat per item | Selesai sendiri saat data riil menggantikan data demo |
| D10 | Sinkronisasi CLAUDE.md / PRD / README | **Terakhir**, setelah semua kode selesai |
| D11 | Skala konversi per satuan obat | Dibahas di Bagian 7 (SAW) |
| D12 | Apotik Winong | Hanya referensi internal peneliti; **tidak dicantumkan** di naskah |

Dengan Bagian 5 dan D1, **Section 13 CLAUDE.md seluruhnya masuk cakupan sebelum sidang**. Yang masih
menunggu hanya dua hal di luar kendali kode: foto kartu stok (M10, D5) dan data riil.

---

## 7. SPK SAW

Inti skripsi. Semua sumber data sudah terdefinisi ulang di Bagian 1–6; bagian ini menetapkan
bagaimana SAW membacanya, menutup temuan audit `IMPROVEMENT.md` (T1–T11), dan menjawab pertanyaan
dosen pembimbing tentang batas minimum yang berbeda-beda per obat.

### 7.1 Keputusan

| # | Keputusan | Dasar |
|---|-----------|-------|
| K0 | **Alternatif** = obat `status = active` yang punya **minimal satu baris kartu stok**. Obat tanpa riwayat stok dikecualikan dan dicatat di halaman SAW ("N obat tanpa riwayat stok dikecualikan") | Obat seperti itu tidak punya C1–C4 yang bermakna. Menutup T10 (kriteria pemilihan alternatif harus tertulis di Bab III) |
| K1 | **C1 = stok tersedia ÷ `min_stock`** (rasio, 2 desimal). Stok tersedia = sisa lapisan **belum kedaluwarsa** dalam satuan jual (F2) | Tabel wawancara (≤20/40/70/100) adalah tabel rasio dengan batas minimum 20; dibagi 20 → ≤1×/2×/3,5×/5×. Menjawab pertanyaan dosen ("batas minimum beda-beda per obat/satuan") dengan mekanisme, tabel tetap satu. **Syarat: `min_stock` diisi nyata dari apotek**, bukan dibiarkan bawaan |
| K2 | **C2** = Σ qty penjualan dalam periode ÷ jumlah hari × 30, dibulatkan. Jumlah hari = selisih tanggal (keduanya 00:00) + 1, bilangan bulat. Filter: "sampai" **dikunci = hari ini**, "dari" bisa diubah, bawaan 30 hari. Tanpa filter status (kolomnya dihapus, S1) | Menutup T5 (jalur terjadwal menghitung 31,99 hari) |
| K3 | **C3** = sisa hari dari hari ini ke ED **batch terjauh yang masih bersisa** (sisa per lapisan > 0). ED = tanggal 1 bulan ED (R3). **Stok tersedia 0 → 0 hari** (skor 1) | Keputusan peneliti 2026-09-12; menutup T2. Dengan FEFO, batch terjauh adalah yang dikonsumsi terakhir |
| K4 | **C4** = HPP rata-rata bergerak (M4, §4.3), bilangan bulat rupiah (dibulatkan ke atas, K14) | Tidak pernah null berkat K0 + aturan opname (§7.3) |
| K5 | Skala konversi C2, C3, C4 = ambang wawancara (`update-dari-wawancara.md` §3); C1 = versi rasio | Seeder belum diperbarui sejak wawancara |
| K6 | **Satu tabel konversi per kriteria.** Tidak ada skala per obat maupun per satuan | SAW membandingkan alternatif pada skala bersama; skala per alternatif meruntuhkan perbandingan. Perbedaan antar-obat masuk lewat nilai mentah (C1 rasio), bukan lewat tabel |
| K7 | Bobot 0,30/0,30/0,20/0,20. Validasi Σ = 1,000 **di service** (`execute()` menolak), form menolak total < maupun > 1,000 | Menutup T4 (jalur terjadwal bisa jalan dengan bobot ≠ 1; form hanya menolak > 1) |
| K8 | Kriteria dikunci tepat 4. **Toggle `is_active` dihapus** dari tabel dan form; kolom DB boleh tetap | Menutup T9: toggle menyesatkan karena menonaktifkan satu kriteria langsung membuat `execute()` gagal |
| K9 | Vi seri → **nomor sama (peringkat padat)**: 1, 2, 2, 2, 3. Kolom berlabel **"Tingkat"**. Urutan tampil di dalam kelompok seri: **rasio C1 terkecil** (B3) → permintaan terbesar → ED terdekat → `id` | Menutup T7. Bagi apoteker satu tingkat = satu keputusan; tidak membedakan yang tidak berbeda. Rasio, bukan stok mentah, karena itulah yang dinilai C1 |
| K10 | Modal "Detail Hitungan": label rumus per kriteria (**`min/X`** untuk C1, C3, C4; **`X/max`** untuk C2), tampilkan Min/Max acuan kolom, dan untuk C1 tampilkan langkah `stok ÷ min_stock = rasio` | Menutup T1 |
| K11 | Di tabel ranking: bulk action **"Buat PO"** (pilih PBF) dan penanda **"sudah dipesan"** (obat ada di PO `pending`/`partial`) | P9, P10 |
| K12 | Catatan "N obat tanpa riwayat stok dikecualikan" di halaman SAW (menggantikan rencana peringatan "tanpa harga beli") | K0, K4 |
| K13 | Test Pest yang mereproduksi contoh Bab 3.4.4 **versi baru** (setelah skala, C1 rasio, dan C4 HPP dihitung ulang) sel per sel, plus test tiap aturan di atas | Menutup T8 |
| K14 | `hpp_avg` disimpan **bilangan bulat, dibulatkan ke atas** (17.200,50 → 17.201). C1 rasio dibulatkan **2 desimal**. **Semua aturan skala inklusif** (satu semantik, B2); C1 ditulis dua desimal tanpa celah. C2, C3 sudah bulat | Menutup T6 tanpa mengubah skala C2–C4 dan tanpa dua semantik di `convertToScore()` |
| K15 | Normalisasi: `min/X` untuk cost (C1, C3, C4), `X/max` untuk benefit (C2); skor 0 dikecualikan dari Min dan dinormalisasi 0 — **tidak berubah**, hanya ditulis di Bab 3.4.3 | Menutup T3, T11 (dokumen internal masih menulis "X/max universal") |

### 7.2 Kriteria sesudah revisi

| Kode | Kriteria | Tipe | Bobot | Nilai mentah | Sumber |
|---|---|---|---|---|---|
| C1 | Rasio stok | cost | 0,30 | stok tersedia ÷ `min_stock` | `StockCardService::availableStock()` (lapisan belum kedaluwarsa), `medicines.min_stock` |
| C2 | Permintaan/bulan | benefit | 0,30 | Σ qty ÷ hari × 30 | `order_items` × `orders.order_date` |
| C3 | Sisa kedaluwarsa (hari) | cost | 0,20 | ED batch terjauh bersisa − hari ini; 0 bila stok tersedia 0 | lapisan (`medicine_stocks` baris D + sisa) |
| C4 | Harga pokok | cost | 0,20 | `hpp_avg` baris kartu stok terakhir | `medicine_stocks.hpp_avg` |

Skala konversi:

| Skor | C1 rasio stok | C2 permintaan/bln | C3 sisa ED (hari) | C4 HPP (Rp) |
|:---:|---|---|---|---|
| 1 | ≤ 1,00 | ≤ 10 | ≤ 90 | ≤ 2.000 |
| 2 | 1,01 – 2,00 | 11 – 30 | 91 – 180 | 2.001 – 10.000 |
| 3 | 2,01 – 3,50 | 31 – 65 | 181 – 365 | 10.001 – 50.000 |
| 4 | 3,51 – 5,00 | 66 – 100 | 366 – 730 | 50.001 – 100.000 |
| 5 | ≥ 5,01 | ≥ 101 | ≥ 731 | ≥ 100.001 |

C1 diturunkan dari kolom wawancara (≤20 / 21–40 / 41–70 / 71–100 / ≥101) dibagi batas waspada 20.
Semua aturan **inklusif** (B2); C1 ditulis dua desimal tanpa celah karena rasionya dibulatkan dua
desimal sebelum dicocokkan. `convertToScore()` cukup mendukung nilai desimal — semantiknya satu.

Contoh C1 pada tiga satuan berbeda:

| Obat | Satuan jual | `min_stock` | Stok tersedia | Rasio | Skor |
|---|---|---|---|---|---|
| Calortusin | Strip | 20 | 18 | 0,90 | 1 |
| Lostacef dry syr | Flask | 6 | 30 | 5,00 | 4 |
| Ketorolac inj | Ampul | 50 | 30 | 0,60 | 1 |

### 7.3 Aturan turunan

**Stok awal obat baru wajib lewat RO.** Opname penambahan hanya untuk obat yang sudah punya HPP
(memakai `hpp_avg` saat itu); pada obat tanpa HPP ditolak dengan pesan "masukkan stok awal lewat
Penerimaan". Akibatnya setiap obat yang punya baris kartu stok pasti punya HPP → C4 tidak pernah 0.

**Perhitungan C3.** Ambil lapisan obat dengan sisa > 0 dan `expired_date > hari ini` (B1), urut ED
**turun**, ambil yang pertama → `expired_date − today` dalam hari. Tidak ada lapisan bersisa yang
belum kedaluwarsa (stok tersedia 0) → 0. Lapisan tanpa ED (data lama) diabaikan dalam pencarian ED;
bila hanya itu yang bersisa → diperlakukan sebagai 0 hari (konservatif).

**Peringkat padat.** Urutkan Vi turun; obat dengan Vi sama (dibandingkan pada 6 desimal) mendapat
nomor tingkat yang sama; tingkat berikutnya = tingkat sebelumnya + 1. Di dalam satu tingkat, urutan
tampil mengikuti tie-breaker K9. Snapshot menyimpan `rank` = tingkat dan `sort_order` = urutan tampil.

**Widget Top 10** = 10 baris teratas menurut `sort_order`, bukan "semua obat bertingkat ≤ 10".
Tabel 4.2 "sepuluh teratas" dibaca dengan cara yang sama.

**Pipeline** (tidak berubah kecuali sumber nilai mentah dan pemeringkatan):

```
execute(period_start, today, trigger, user)
  ├─ validasi Σ bobot = 1,000 (K7), 4 kriteria lengkap
  ├─ alternatif = obat aktif dengan ≥ 1 baris kartu stok (K0); catat yang dikecualikan
  ├─ nilai mentah: C1 rasio, C2 proyeksi 30 hari, C3 batch terjauh bersisa, C4 hpp_avg
  ├─ konversi skor 1–5 via scale_rules (desimal-aware untuk C1)
  ├─ normalisasi min/X (cost) · X/max (benefit); skor 0 → 0 (tidak terjadi pada data lengkap)
  ├─ Vi = Σ Wj × Rij
  └─ peringkat padat + tie-breaker → simpan snapshot
```

### 7.4 Skema

| Tabel | Perubahan |
|---|---|
| `saw_calculation_results` | **tambah** `c1_stock` (stok tersedia), `c1_min_stock`, `sort_order`; `c1_raw` = rasio (decimal 8,2); `rank` = tingkat padat |
| `saw_calculations` | **tambah** `excluded_count` (obat tanpa riwayat stok) |
| `saw_criteria` | `scale_rules` C1 dalam desimal; `is_active` tidak dipakai UI |

### 7.5 Berkas yang tersentuh

| Aksi | Berkas |
|------|--------|
| Service | `SawCalculationService` — K0 (alternatif), K1–K4 (`getRawValue`), K7 (validasi bobot), K9 (peringkat padat + tie-breaker), K12 (excluded) |
| Model | `SawCriteria::convertToScore()` desimal-aware, batas atas eksklusif untuk aturan berdesimal; `Medicine` — `farthestExpiryDays()` per lapisan, hapus `nearestExpiryDate()`/`nearestExpiryDays()`; `StockCardService::availableStock()` |
| Command | `RecalculateSawCommand` — `period_end = today()->startOfDay()` (K2) |
| Seeder | `SawCriteriaSeeder` — K5 (ambang wawancara, C1 rasio); `SpkTestDataSeeder` — `min_stock` nyata per obat (bukan bawaan), distribusi rasio merata |
| Page | `SawCalculation.php` — filter "sampai" terkunci, kolom "Tingkat", tampilan "Stok 18 (min 20)", catatan excluded, bulk action Buat PO, penanda sudah dipesan |
| Blade | `saw-result-detail.blade.php` — K10 (label rumus, Min/Max, langkah rasio) |
| Resource | `SawCriterias` — hapus ToggleColumn & Layer 3 validasi aktivasi; form rules: total = 1,000 dua sisi; repeater scale_rules menerima desimal |
| Resource | `SawCalculations` (history) — kolom baru |
| Widget | `SawTop10RestockWidget` — 10 baris via `sort_order`, label "Tingkat" |
| Test | `tests/Unit/SawCalculationTest.php` — reproduksi contoh 3.4.4 baru; K0, K2 (hari bulat), K3 (stok 0 → 0 hari; batch terjauh), K7, K9 (padat + tie-breaker), K14 (rasio desimal) |
| Dokumen | Bab III: definisi operasional C1–C4, Tabel 3.5 rasio + paragraf penurunan, 3.4.3 aturan skor 0 & kriteria alternatif, 3.4.4 dihitung ulang; Bab 1.4 batasan menyempit ("ambang C2 dan C4 satu set untuk seluruh satuan"); Bab 4.1.7 koreksi "kriteria dapat dinonaktifkan"; 4.2.2 penjelasan tingkat; CLAUDE.md §5.1, §5.4, §13.8 (`min_stock` kini masuk SAW — disengaja); PRD §9; `update-dari-wawancara.md` §3 (C1 rasio), §4.1 (C4 HPP) |

### 7.6 Verifikasi

- [ ] Contoh Bab 3.4.4 (versi baru) direproduksi test sel per sel: 20 nilai mentah → 20 skor → 20 normalisasi → 5 Vi → tingkat
- [ ] Calortusin/Lostacef/Ketorolac (§7.2) → skor C1 = 1 / 4 / 1
- [ ] Obat 2 lapisan: 5 kedaluwarsa + 20 belum → stok tersedia 20; C3 dari lapisan yang belum kedaluwarsa
- [ ] Stok tersedia 0 → C3 = 0 hari → skor 1; C1 = 0 → skor 1
- [ ] Obat tanpa baris kartu stok → tidak ada di hasil; `excluded_count` bertambah
- [ ] Periode 14 Agt–12 Sep, terjual 60 → C2 = 60 pada jalur form **dan** jalur terjadwal
- [ ] Bobot 0,3/0,3/0,2/0,1 → `execute()` menolak; form menolak simpan
- [ ] 3 obat ber-Vi sama → tingkat sama, tingkat berikutnya +1; urutan tampil: rasio C1 terkecil dulu
- [ ] HPP 17.200,50 → tersimpan 17.201 → skor C4 sesuai rentang; rasio 2,005 → 2,01 → skor 3 (tidak jatuh ke celah)
- [ ] Lapisan ber-ED tepat hari ini → dianggap kedaluwarsa (tidak dialokasikan, tidak masuk C1/C3)
- [ ] Modal detail menampilkan `min/X` untuk C1/C3/C4, `X/max` untuk C2, Min/Max kolom, dan langkah rasio C1
- [ ] Bulk "Buat PO" 3 obat → PO dengan 3 baris, kemasan bawaan, jumlah ⌈min_stock ÷ isi⌉; ketiganya berpenanda "sudah dipesan" pada perhitungan berikutnya

### 7.7 Hal terbuka

- **`min_stock` nyata**: kolom di template D5, ditanyakan ke apotek bersama kartu stok ("batas waspada obat ini berapa?"). Sampai itu ada, bawaan M9 (Strip 20, lainnya isi kemasan) yang berlaku.
- **Contoh 3.4.4** dipilih dari data yang dipakai Bab IV — data riil bila sudah ada, seeder demo bila belum (T2).

---

## 8. Keputusan Konsistensi (B1–B8, C1–C6, T1–T2 — 2026-09-13)

Hasil pembacaan ulang seluruh dokumen dari sisi implementasi. Semua sudah dijahit ke bagian yang
bersangkutan; tabel ini rekap.

| # | Hal | Keputusan | Dijahit di |
|---|-----|-----------|------------|
| B1 | Batas kedaluwarsa | `expired_date > hari ini` (kedaluwarsa sejak tanggal 1) | F1, §5.2, §7.3 |
| B2 | Semantik skala | Semua inklusif; C1 dua desimal tanpa celah, rasio dibulatkan 2 desimal | K14, §7.2 |
| B3 | Tie-breaker | Rasio C1 terkecil → permintaan terbesar → ED terdekat → `id` | K9 |
| B4 | Stok fisik vs tersedia | Notifikasi stok minimum, `stock_status`, widget Low Stock, laporan fast/slow, C1, validasi jual, FEFO → **stok tersedia**. Saldo kartu stok & opname → stok fisik | F2, §5.5 |
| B5 | Hapus dokumen | Baris ledger **dihapus sungguhan** + replay HPP; dokumen tetap soft-delete. Saringan `whereHas` di `currentStock()`/`availableStock()` dihapus | §2.4, §4.5 |
| B6 | Bawaan `min_stock` | Per satuan jual: Strip 20, lainnya = `pack_size` | M9, §1.3 |
| B7 | `min_stock` = 0 lama | Backfill ke bawaan B6; form menolak 0 | M9, §1.2 |
| B8 | `pack_size`/`pack_unit_id` | NOT NULL; backfill 1 / `unit_id` | §1.2 |
| C1 | Sheet *Stok* template | Saldo **awal periode** (hari ini − 30) per obat, dialokasikan ke batch faktur terakhir sebelum tanggal itu; urutan muat: saldo awal → faktur periode → penjualan periode = saldo hari ini | D5 |
| C2 | Harga saldo awal | Sheet *Stok* punya kolom harga; importer menulisnya sebagai RO "saldo awal" (supplier & faktur khusus) → HPP ada | D5, §7.3 |
| C3 | Harga jual di sheet *Penjualan* | Opsional; kosong → = HPP saat itu (margin nol, tidak bermakna untuk laporan margin — diterima) | D5 |
| C4 | Unik + soft delete | Indeks unik `medicines(name, deleted_at)` dan `receive_orders(supplier_id, invoice_number, deleted_at)` | §1.10 |
| C5 | Batch/ED pada lapisan | Disalin ke **semua** baris D (RO dan opname) — query FEFO/C3 tanpa join | §2.3, §5.4 |
| C6 | Generator kode | Retry sekali bila tabrakan unik | §1.10 |
| T1 | Tanggal sidang / batas data | **Tidak dijadikan pengendali** — fokus membangun sistem |
| T2 | Data apotek terlambat | Bab IV memakai `SpkTestDataSeeder` versi baru; naskah **tidak** diubah untuk itu |

Pembacaan kedua (Q0–Q9, 2026-09-13):

| # | Hal | Keputusan | Dijahit di |
|---|-----|-----------|------------|
| Q0 | `config/app.php` timezone = **UTC** → `today()` pada jadwal 06:00 WIB = kemarin; B1 dan K2 meleset sehari | `timezone` → **`Asia/Jakarta`** di E0 | Bagian 9 |
| Q1 | `stock_status` basi saat batch kedaluwarsa tanpa mutasi | `sipokat:check-stock-and-expiry` menghitung ulang `stock_status` semua obat sebelum memindai | §5.5 |
| Q2 | "Saldo" di rumus HPP | Stok **fisik** | §4.3 |
| Q3 | Bentuk opname per lapisan | **Hitung fisik per lapisan** (sistem vs fisik → selisih), + baris "batch baru" | §5.3 |
| Q4 | ED vs tanggal terima | Validasi `ED > tanggal terima` | R4 |
| Q5 | ED lama bertanggal penuh | Migration menormalkan ke tanggal 1 | §2.3 |
| Q6 | Penerimaan melebihi sisa PO | Ditolak untuk baris dari PO; kelebihan = baris di luar PO | R9 |
| Q7 | Tanggal penjualan mundur | Boleh; FEFO berdasarkan lapisan saat diketik, replay HPP mengikuti tanggal | §4.2 |
| Q8 | E3/E4 saling bergantung | Digabung jadi **E3 Pengadaan**: migration PO + RO → form PO → form RO | Bagian 9 |
| Q9 | Tarif PPN bawaan | **11** (sesuai faktur contoh); dapat diubah di Pengaturan Umum | R13 |

Catatan implementasi (tanpa keputusan):

- C2 tetap memakai `whereHas('order')` pada `order_items` agar order soft-deleted tidak terhitung — B5 menghapus saringan di *ledger*, bukan di sini.
- `layer_stock_id` FK ke tabel sendiri: `ON DELETE RESTRICT` sebagai pagar tambahan R8.
- 9 pemanggil `currentStock()`/`getAvailableStock()` dipetakan satu per satu ke `physicalStock()`/`availableStock()` (widget, laporan, opname).
- `LaporanRekap` dan `SalesSummaryWidget` yang membaca `order_items.total` → `qty × price`.
- Permission Shield untuk `EditOrder` dan action cetak PO yang dihapus dibersihkan (pola CLAUDE.md §12.4).
- Mengubah ED sebuah lapisan (R8) tidak mengalokasi ulang penjualan yang sudah terjadi — konsisten S6.

Revisi template D5 akibat C1–C3:

| Sheet | Kolom |
|---|---|
| Obat | nama, kategori, satuan jual, kemasan, isi, **min_stock** |
| Saldo awal | obat, batch, ED, jumlah (satuan jual), **harga per satuan jual** — per tanggal awal periode |
| Faktur | no faktur, PBF, tanggal, obat, kemasan, isi, jumlah kemasan, harga per kemasan, batch, ED |
| Penjualan | tanggal, obat, jumlah, harga jual (opsional) |

---

## 9. Urutan Eksekusi

Prinsip: **satu bagian = satu rangkaian commit yang meninggalkan test hijau**; migration bersifat
maju saja (tidak ada `down()` bermakna — pemulihan lewat backup); angka SAW tidak dikunci sampai
seluruh kode selesai (T1).

| Tahap | Isi | Bergantung pada | Titik uji |
|---|---|---|---|
| E0 | Cabang kerja `revisi-2026-09` dari `main`; backup DB lokal; **`timezone` → `Asia/Jakarta`** (Q0) | — | `today()` pada pukul 05:00 WIB = tanggal WIB |
| E1 | **Master obat** (Bagian 1): migration drop/tambah kolom + backfill (B7, B8) + penomoran `OBT-####`; model & generator kode; form/table/importer; hapus 18 referensi `dosage`; seeder satuan (Ampul, Kaleng) | E0 | §1.9 |
| E2 | **Kartu stok & HPP** (Bagian 4 + D1/C5): `medicine_stocks` kolom lapisan (`batch_number`, `expired_date`, `receive_order_item_id`, `layer_stock_id`, `hpp_avg`); `StockMovementService` replay HPP; `StockCardService` `physicalStock()`/`availableStock()`; B5 (hapus sungguhan, buang `whereHas`); backfill lapisan D + replay; normalisasi ED ke tanggal 1 (Q5) | E1 | §4.7 tabel §4.3 direproduksi |
| E3 | **Pengadaan** (Bagian 2 + 3, Q8), urutan internal: (a) migration PO + RO sekaligus (skema ramping PO, header/item RO, `ppn_rate`); (b) form PO format RO, status turunan, Tutup PO, widget, hapus cetak; (c) form RO: konversi kemasan, ED bulan-tahun (Q4), centang item PO (Q6), R8, cetak, export | E2 | §2.6 + verifikasi §3 |
| E4 | **Penjualan & FEFO** (Bagian 4 form + Bagian 5): hapus edit; harga ≥ HPP; tanggal (Q7); `allocateFefo()`; panel alokasi; opname hitung fisik per lapisan (Q3); notifikasi/widget/laporan → stok tersedia (B4); `stock_status` dihitung ulang harian (Q1); backfill F6 | E2, E3 | §4.7, §5.5 test |
| E5 | **SAW** (Bagian 7): seeder skala (K5); K0–K4 nilai mentah; K7; K9 padat; K10 modal; K11 Buat PO + sudah dipesan; hapus toggle; test contoh 3.4.4 baru | E3, E4 | §7.6 |
| E6 | **Seeder demo & importer** (D5, T2): `SpkTestDataSeeder` versi baru (lapisan, HPP, `min_stock` nyata, faktur, FEFO); template Excel 4 sheet + seeder pembaca; uji dengan faktur Mei 2024 | E5 | seeder 2× tidak berlipat; angka SAW konsisten |
| E7 | **Smoke test browser** seluruh alur: obat → PO dari ranking → RO per faktur → jual (FEFO) → opname → SAW → laporan | E6 | checklist NEXT_STEPS C1 |
| E8 | **Sinkronisasi dokumen** (D10): CLAUDE.md (§5.1, §5.4, §12, §13, §14), PRD, README (MySQL), `update-dari-wawancara.md` §3/§4.1, `IMPROVEMENT.md` ditutup; bersihkan permission Shield yang basi | E7 | — |
| E9 | **Deploy VPS MySQL** + cron (D6, D8); roles via Shield oleh peneliti (D7) | E8 | — |

Yang bisa berjalan paralel: E6 template Excel (bukan seedernya) sejak E1; E8 dicicil per bagian
tapi dikunci di akhir.

**Setelah E9** tidak ada pekerjaan kode yang tersisa dalam rencana ini. Yang tersisa hanya data
apotek (A1–A5) dan naskah — keduanya milik peneliti.
