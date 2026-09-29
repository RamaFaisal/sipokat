# Analisa Permintaan Tambahan (Dashboard, Laporan, Satuan, Nomor Dokumen)

> Disusun 2026-09-28 atas daftar permintaan peneliti, **keputusan peneliti dijawab di hari yang sama**
> dan sudah dimasukkan ke tiap bagian. **Belum ada kode yang diubah**; dokumen ini analisis, keputusan,
> dan urutan kerja.
> Setiap klaim tentang kondisi sekarang sudah dicek langsung ke kode, berkas dan barisnya ditulis.
> Konteks jadwal: sidang Oktober 2026 ([rencana-sidang-2026-10.md](rencana-sidang-2026-10.md)).

---

## 0. Keputusan peneliti (2026-09-28)

| # | Perkara | Keputusan | Rinci di |
|---|---|---|---|
| K1 | Tarif PPN | **Pindahkan ke tiap RO**, kolom nullable, bawaan dari pengaturan | §2 |
| K2 | Grafik Penjualan & PO Terbuka | **Grafik Penjualan tetap, PO Terbuka dibuang** dari dashboard | §1 |
| K3 | Singkatan bulan | **"07 Sep 2026"** (singkatan Indonesia) | §3.3, §8 |
| K4 | Data isi per strip | **Tidak tersedia**, jadi satuan jual per strip **tidak dibangun**, ditulis sebagai batasan naskah | §13 |
| K5 | Isi widget dashboard | **Ketiganya memuat seluruh data, tanpa kotak pencarian**, digulir dalam kartu bertinggi tetap 32rem (direvisi 2026-09-29) | §1.1 |
| K6 | Ambang warna ED | **30 / 60 / 90 hari**, seragam di widget, kartu stok, dan notifikasi | §1.2 |
| K7 | Laporan baru | **Keempatnya**: akan kedaluwarsa, rekap stok per obat, pembelian per PBF, hasil stok opname. **Semua sebelum sidang** | §4 |
| K8 | Catatan kosong di detail penjualan | **Belum dicek peneliti**, jadi diperiksa sendiri ke data | §5 |
| K9 | Tanggal berpasangan jam | **Ikut diseragamkan**, "07 Sep 2026 14:30" | §8 |
| K10 | Tombol bayar | **Tidak dikerjakan**, sudah ditutup Batasan Masalah naskah | §10 |
| K11 | Kolom pengganti di menu Obat | **Tidak ada pengganti** | §7 |
| K12 | Waktu penyeragaman nomor | **Dikerjakan paling dulu**, sebelum pembekuan angka Bab IV | §9, §14 |
| K13 | Nomor Stok Opname | **Ikut diseragamkan**, `OPM0001` menjadi `OPM202609280001` | §9 |
| K14 | Code freeze | **Digeser dari 1 ke 3 Oktober**, supaya seluruh lingkup yang dipilih selesai utuh | §14 |
| K15 | Penanda data simulasi | **Dihapus dari data**; pengakuan hanya lewat naskah Bab 1.3 dan Bab IV | §15 |
| K16 | Obat berkemasan kaleng | **Satuan jualnya Kaleng**, bukan Tablet; data riil dikonversi 2026-09-29 | §15 |

---

## 1. Dashboard: tiga widget dalam satu baris

**Kondisi sekarang.** Grid dashboard 4 kolom di layar besar (`app/Filament/Pages/Dashboard.php`),
berisi 4 widget statistik (span 1) dan lima widget tabel yang semuanya `columnSpan = 'full'`:
SAW Top-10, Stok Menipis, Mendekati ED, Grafik Penjualan, PO Terbuka.

**Bentuk yang dituju (K2, K5):**

| Baris | Isi |
|---|---|
| Atas | Kartu statistik, digabung jadi **satu** `StatsOverviewWidget` berisi 4 stat, span penuh |
| Tengah | Stok, Kedaluwarsa, Tingkatan SAW, masing-masing sepertiga lebar |
| Bawah | Grafik Penjualan, span penuh. Widget PO Terbuka **dihapus** |

Penggabungan 4 kartu statistik jadi satu widget diperlukan supaya grid bisa dijadikan 3 kolom tanpa
menyisakan kartu yang timpang (4 kartu di grid 3 menyisakan 3 + 1).

**Aturan ketiga widget tengah (K5, direvisi 2026-09-29):** memuat seluruh datanya, tanpa kotak
pencarian, dan digulir di dalam kartunya.

Yang dikunci adalah tinggi **kartunya** (32rem), bukan tinggi isinya. Mengunci isi saja tidak cukup:
kepala kartu berbeda tinggi begitu satu keterangan membungkus ke dua baris, sehingga ketiganya tetap
tidak sejajar. Kartu dijadikan kolom fleks dan area isi mengambil sisa ruang, jadi kesamaan tinggi
dijamin oleh struktur, bukan oleh kebetulan panjang kalimat.

Kotak pencarian dihapus atas permintaan peneliti. Konsekuensinya batas jumlah baris ikut dilepas di
ketiga widget: kalau isinya dipotong sementara pencarian tidak ada, sebagian data tidak akan bisa
dicapai dari dashboard sama sekali. Mencari obat tetap bisa lewat menu Obat dan Kartu Stok.

Ambang 90 hari tetap menentukan warna baris dan isi notifikasi harian, tetapi tidak memotong isi
widget Kedaluwarsa: pada data riil 2026-09-29 hanya 1 dari 138 lapisan yang ED-nya dalam 90 hari,
sehingga widget yang ikut ambang akan tampil hampir kosong justru ketika stok apotek sehat.
Pertanyaan yang dijawab widget itu adalah "batch mana yang paling dulu kedaluwarsa", dan itu selalu
punya jawaban.

Pencarian bekerja atas seluruh data, bukan atas baris yang kebetulan terlihat. Ada tesnya di
`DashboardWidgetTest`, dua per widget: satu memastikan tidak ada baris yang hilang dan urutannya
benar, satu memastikan pencarian menemukan baris yang berada jauh di bawah.

Catatan untuk widget SAW: tabelnya berbasis array (`Table::records()`, keputusan 2026-09-27), bukan
query Eloquent. Selama sempat ada kotak pencarian, penyaringannya harus ditulis manual dan **harus di
dalam closure `records()`**, karena objek tabel di-cache Filament sehingga baris yang dihitung di luar
closure membeku pada render pertama. Sesudah pencarian dihapus, penyaring manual itu ikut dihapus.

Tinggi kotak yang dikunci belum ada bawaannya di Filament, jadi perlu kelas CSS pembungkus dengan
`max-height` dan `overflow-y: auto`. Kecil, tapi bukan satu baris konfigurasi.

### 1.1 Widget Stok

Isi: nama obat dan sisa stok, diurutkan dari **stok tersedia paling sedikit**, seluruh obat aktif
termasuk yang stoknya 0.

Ada ganjalan teknis yang harus diselesaikan dulu. "Sisa stok" yang benar adalah **stok tersedia**
(lapisan yang belum kedaluwarsa), dan itu sekarang dihitung di PHP lewat
`StockCardService::availableStock()`, sehingga **tidak bisa** dipakai `orderBy` SQL.

Widget yang ada sekarang menyiasatinya dengan mengurutkan `stock_status` (tiga ember:
habis / menipis / tersedia) memakai `orderByRaw("FIELD(...)")`
(`app/Filament/Widgets/LowStockMedicinesWidget.php:29`). Dua catatan:

- `FIELD()` khusus MySQL. Dashboard tidak ikut smoke test render (`tests/Feature/PanelPagesRenderTest.php`
  hanya menguji halaman obat dan form transaksi), jadi ketidakcocokan ini tidak pernah ketahuan di tes.
- Urut per ember bukan urut "paling sedikit".

**Cara yang dipakai:** satu `selectSub` yang menghitung sisa lapisan belum kedaluwarsa (polanya sudah
ada di `MedicineStock::scopeWithRemainingStock()`), lalu `orderBy` dikerjakan SQL. Satu query untuk
seluruh widget, bukan 127, dan `FIELD()` yang khusus MySQL itu ikut hilang.

### 1.2 Widget Kedaluwarsa

Isi: nama obat dan sisa kedaluwarsa, urut ED terdekat, seluruh batch bersisa tanpa memandang ambang,
baris diberi warna menurut ambang.

Widget sudah ada dan sudah urut ED terdekat. Yang kurang: kolomnya delapan, terlalu ramai untuk
sepertiga lebar, dan belum ada pewarnaan baris. `->recordClasses()` tersedia di Filament 4 yang
terpasang (`vendor/filament/tables/src/Table/Concerns/HasRecordClasses.php`), jadi didukung.

**Ambang warna (K6), dipakai seragam di semua permukaan:**

| Sisa hari | Warna |
|---|---|
| ≤ 30 | Merah |
| 31–60 | Kuning |
| 61–90 | Hijau |

Ini menyatukan ambang badge yang sekarang (30 dan 60) dengan ambang notifikasi harian (90), sehingga
tidak ada lagi dua definisi "mendesak" yang berbeda di dua layar.

### 1.3 Widget Tingkatan SAW

Isi: nama obat saja, urut peringkat, seluruh obat yang diperingkat.
Sejak `calculateCached()` dipakai (commit `d74d355`), menampilkan ini di dashboard sudah tidak mahal.

### 1.4 Efek samping

Menggabung, menghapus, atau mengganti nama kelas widget **mengubah nama izin Shield**, jadi
`RoleSeeder` dan `shield:generate` ikut disesuaikan. Widget PO Terbuka yang dihapus juga berarti satu
izin dicabut.

---

## 2. Tarif PPN dipindahkan ke tiap RO (K1)

**Kondisi sekarang.** `ppn_rate` satu angka global di pengaturan (`app/Settings/GeneralSettings.php:18`),
dipakai **hanya** untuk memecah total menjadi DPP dan PPN di cetakan RO
(`resources/views/print/print-receive-order.blade.php:313`). Tidak ada perhitungan pajak di mana pun;
harga faktur PBF sudah termasuk PPN.

**Dua masalah yang diselesaikan.** Yang peneliti sebut: tarif pemerintah berubah, dan tiap PBF
berbeda (ada faktur yang mencantumkan pajak, ada yang tidak). Yang kedua lebih tajam: **setting global
menulis ulang sejarah**. Begitu tarif diubah 11 ke 12, cetakan faktur Mei 2024 ikut tercetak 12%,
padahal faktur aslinya 11%. Tarif melekat pada faktur, bukan pada aplikasi.

**Bentuk yang dipakai:**

- Kolom `ppn_rate` **nullable** di `receive_orders`.
- Terisi otomatis dari nilai bawaan di Pengaturan saat RO dibuat, tetap bisa diubah atau dikosongkan.
- Kosong berarti faktur tidak mencantumkan pajak, sehingga cetakan **tidak** memecah DPP dan PPN.
- Pengaturan global tetap ada, tetapi turun status jadi nilai bawaan, bukan kebenaran.

Ongkos: 1 migrasi, 1 field di form RO, 1 percabangan di blade cetakan. Tidak menyentuh kartu stok,
HPP, maupun SAW, karena tarif ini memang tidak pernah dipakai menghitung apa pun.

---

## 3. Kartu stok: kolom kedaluwarsa dan format tanggal

### 3.1 Kolom ED

Belum ada. Yang bermakna di level obat adalah **ED terdekat dari lapisan yang masih bersisa**, diberi
warna memakai ambang K6.

Perhatikan bahwa C3 SAW justru memakai batch **terjauh** (alasannya di CLAUDE.md §6). Keduanya benar
untuk tujuan masing-masing, tetapi labelnya harus dibedakan di layar: "ED terdekat" di kartu stok,
"ED batch terjauh" di rincian SAW. Kalau dua-duanya ditulis "ED" saja, penguji berhak bingung.

### 3.2 Dua temuan di tabel yang sama

Berkas: `app/Filament/Resources/MedicineStocks/Tables/MedicineStocksTable.php`

1. **N+1.** Tiap baris memanggil service dua kali (Stok Awal dan Stok Saat Ini). Kolom ED akan jadi
   panggilan ketiga: 75 query untuk 25 baris. Ketiganya sekalian dijadikan subquery.
2. **Filter periode tidak menyaring apa pun.** `Filter::make('period')` punya `query()` yang
   mengembalikan `$query` apa adanya; nilai tahun dan bulan hanya dibaca kolom lewat
   `$livewire->tableFilters`. Jalan, tetapi menyesatkan bagi yang membaca kode.

### 3.3 Format tanggal di detail kartu stok

Sekarang `translatedFormat('d F Y')` di `app/Services/StockCardService.php:99` menghasilkan
"07 September 2026". Diubah ke `d M Y` dengan lokal Indonesia menjadi **"07 Sep 2026"** (K3).
Singkatan Indonesia: Jan Feb Mar Apr Mei Jun Jul Agu Sep Okt Nov Des.

---

## 4. Menu Laporan: digabung dan ditambah empat laporan (K7)

**Bentuk:** satu halaman **Laporan** dengan tab, grup navigasi "Laporan" dihapus supaya tidak menjadi
"Laporan > Laporan". Tab yang ada:

| Tab | Status | Sumber data |
|---|---|---|
| Rekap Penjualan & Pembelian | Sudah ada (`LaporanRekap`) | `order_items`, `receive_order_items` |
| Fast / Slow Moving | Sudah ada (`LaporanMoving`) | `order_items` |
| Obat Akan Kedaluwarsa | **Baru** | Lapisan bersisa + ED, sudah tersedia penuh |
| Rekap Stok per Obat | **Baru** | Kartu stok: stok awal, masuk, keluar, akhir per periode |
| Pembelian per PBF | **Baru** | `receive_orders` dikelompokkan per supplier |
| Hasil Stok Opname | **Baru** | `medicine_stock_opname_items` beserta selisih dan catatannya |

Keempat laporan baru dikerjakan **sebelum sidang**. Perkiraan tambahan satu sampai dua hari, dan tiap
laporan perlu diverifikasi angkanya sendiri, jangan hanya dilihat tampil atau tidak.

Yang perlu diurus saat menggabung: kedua halaman lama punya aksi ekspor PDF dan Excel sendiri-sendiri
(`LaporanRekap::exportPdf/exportExcel`, idem `LaporanMoving`), jadi ekspornya harus jadi satu aksi
yang sadar tab aktif.

**Apakah sudah auto update: ya.** Keduanya query langsung ke `order_items` dan `receive_order_items`
saat halaman dibuka (`mount()` memanggil `generate()`) dan tiap kali filter diubah. Tidak ada tabel
hasil, tidak ada snapshot, tidak ada cache. Angkanya selalu kondisi terkini, dan empat laporan baru
akan mengikuti pola yang sama.

**Disamakan bentuknya 2026-09-29.** Empat laporan baru sempat berbeda dari dua laporan lama: dua di
antaranya memakai tabel Filament dengan filter dan tombol ekspor di toolbar, semuanya tanpa ringkasan
dan tanpa PDF. Sekarang keenamnya sama: filter di atas, tiga aksi header (`generate`, `export`,
`exportPdf`), kartu ringkasan, lalu tabel. Yang dipakai bersama:

| Berkas | Peran |
|---|---|
| `app/Filament/Pages/Concerns/LaporanSeragam.php` | Aksi header + `exportExcel`/`exportPdf`; halaman menyediakan `judulLaporan`, `labelPeriode`, `ringkasan`, `kolomEkspor`, `barisEkspor`, `namaBerkas` |
| `resources/views/filament/pages/partials/laporan-ringkasan.blade.php` | Kartu ringkasan, isinya dari `ringkasan()` yang sama dengan yang tercetak |
| `resources/views/pdf/laporan.blade.php` | Cetakan PDF bertabel tunggal |
| `app/Support/LaporanExcel.php` | Penulis Excel, ringkasan dicetak di atas tabel |

Dua laporan lama tetap memakai penulis ekspornya sendiri: susunannya beberapa blok ringkasan dan dua
tabel, tidak muat di penulis bersama, sedangkan nama aksi dan urutan tombolnya sudah sama.

Filter yang dulu ada di toolbar tabel dipindah ke form: Akan Kedaluwarsa memakai rentang pantauan
(30/60/90/180/365 hari) + tingkat, Hasil Stok Opname memakai periode + arah. Rentang bawaan opname
adalah tahun berjalan, bukan 30 hari, karena opname dilakukan sesekali dan laporan yang selalu kosong
tidak berguna.

---

## 5. Detail penjualan: subtotal, batch, catatan, dan total kosong

**Sebabnya sudah ditemukan.** `OrderResource` tidak punya `infolist()`, sehingga halaman View memakai
**skema form** dalam mode nonaktif. Tiga dari empat medan itu memang bukan data tersimpan:

| Medan | Kenapa kosong |
|---|---|
| Subtotal | `dehydrated(false)`, hanya diisi `syncRow()` saat mengetik |
| Batch | `fefo_batches`, `dehydrated(false)`, pratinjau alokasi dari stok saat ini |
| Total | `grand_total_display`, `dehydrated(false)`; nilai aslinya di kolom `grand_total` |
| Catatan | Kolom asli `orders.note`. **Belum dipastikan** (K8): perlu dicek apakah ada record yang note-nya terisi tapi tidak tampil. Kalau ada, itu cacat keempat dengan sebab berbeda |

Berkas: `app/Filament/Resources/Orders/Schemas/OrderForm.php:123,133,157`.

**Perbaikan yang benar** bukan menambal form, melainkan `infolist()` tersendiri untuk halaman View.
Satu peningkatan penting di dalamnya: kolom Batch diisi dari **alokasi yang sesungguhnya terjadi**,
yaitu baris C di `medicine_stocks` yang menunjuk lapisannya (batch + ED + jumlah per batch), bukan
dari pratinjau FEFO. Itu data yang benar secara historis, sekaligus menjawab pertanyaan A di §11.

---

## 6. Nama PBF terlalu panjang di tabel PO dan RO

Nama memang panjang ("PT Millenium Pharmacon International Tbk"). Karena kode PBF sudah dibuat
otomatis dari inisial nama (`Supplier::nextCode`), tabel menampilkan **kode PBF** (NPM, KFA) dengan
tooltip nama penuh. Nama penuh tetap ada di halaman detail dan di cetakan.

Berkas: `PurchaseOrdersTable.php:34`, `ReceiveOrdersTable.php:34`. Perubahan sepele.

---

## 7. Menu obat: buang tiga kolom, ganti label Status (K11)

Kolom Satuan Jual, Kemasan Beli, dan Stok Min. dibuang dari
`app/Filament/Resources/Medicines/Tables/MedicinesTable.php`, **tanpa kolom pengganti**. Label
`status` menjadi "Status Obat".

Penggantian label itu memang perlu: di tabel yang sama sudah ada "Status Stok", sehingga dua kolom
sama-sama tampil sebagai "Status..." dan membingungkan. Catatan kecil: Stok Min. adalah penyebut C1
SAW; setelah dibuang dari tabel, nilainya tetap terlihat di form obat, jadi penguji masih bisa
menelusurinya.

---

## 8. Satu format tanggal untuk seluruh aplikasi (K3, K9)

Sekarang ada **delapan** bentuk dipakai bersamaan di UI dan blade:

| Format | Jumlah pemakaian |
|---|---|
| `d M Y` | 10 |
| `m-Y` | 6 |
| `d M Y H:i` | 4 |
| `d-m-Y` | 3 |
| `d/m/Y` | 2 |
| `d F Y`, `F Y`, `d M` | 1 masing-masing |

**Yang dituju:** satu tetapan terpusat (misal `App\Support\Tanggal`) dengan lokal Indonesia:

| Jenis | Bentuk |
|---|---|
| Tanggal | `07 Sep 2026` |
| Tanggal + jam | `07 Sep 2026 14:30` |
| Bulan-tahun (ED) | `Sep 2026` |

Sekitar 20 titik sentuhan, risiko rendah. Dua hal yang **jangan** ikut diubah: format di nama berkas
ekspor (`Ymd_His`) dan `Ymd` di generator nomor dokumen.

---

## 9. Nomor PO, RO, dan penjualan tanpa dash dan seragam (K12)

| Dokumen | Sekarang | Menjadi |
|---|---|---|
| PO | `PO20260916-0001` | `PO202609160001` |
| RO | `RO20260916-0001` | `RO202609160001` |
| Penjualan | `ORD-202609270001` | `ORD202609270001` |
| Stok Opname (K13) | `OPM0001` | `OPM202609280001` |

Dash-nya bahkan berada di posisi yang berbeda antara penjualan dan dua lainnya, dan opname punya
skema sendiri yang tidak memuat tanggal sama sekali.

**Generatornya ada lima, bukan tiga**, dan salinan logika inilah yang membuat formatnya menyimpang
sejak awal:

| Generator | Berkas |
|---|---|
| PO | `PurchaseOrderForm::generatePONumber()` |
| RO | `ReceiveOrder::nextNumber()` |
| Penjualan (form) | `OrderForm::generateOrderCode()`, memakai `now()`, bukan tanggal penjualan |
| Penjualan (impor) | `RealDataImporter::nextImportedOrderCode()`, memakai tanggal yang diimpor |
| Stok Opname | `MedicineStockOpnameForm::nextNumber()`, berurut murni tanpa tanggal |

Kelimanya disatukan ke satu helper, misal `App\Support\DocumentNumber`.

Yang perlu disiapkan:

- **Nomor lama wajib ikut ditulis ulang** lewat migrasi. Kalau tidak, pencarian `like` prefiks dan
  `substr` penghitung nomor berikutnya akan salah membaca data campuran. Presedennya sudah ada
  (migrasi resync kode obat 2026_09_07 dan 2026_09_14).
- Opname butuh perlakuan khusus: skemanya berubah total, jadi tanggalnya diambil dari kolom
  `opname_date` pada barisnya masing-masing, bukan dari nomor lama.
- Panjang nomor tetap, jadi pengurutan leksikografis tetap benar.
- `invoice_number` pada RO **tidak** ikut berubah, itu nomor milik PBF.
- **Fixture tes tidak perlu diubah.** Pola `PO-FIX-`, `RO-FIX-`, `ORD-FIX-`, dan data demo `RO-SPK-`
  tidak pernah bertabrakan dengan pencarian prefiks bertanggal, jadi boleh tetap seperti sekarang.

**Butir ini dikerjakan paling dulu** (K12), karena nomor tersimpan di data dan akan ikut terbawa ke
lampiran Bab IV. Lihat §14 untuk pemisahan tenggatnya.

---

## 10. Tombol bayar: tidak dikerjakan (K10)

Keputusan peneliti: dikerjakan hanya bila disebut di naskah. **Tidak disebut**, dan lebih dari itu,
naskah justru menutupnya secara eksplisit.

`docs/draft_isi_ta.md` **1.3 Batasan Masalah poin 2**:

> Penjualan dicatat hanya untuk pengurangan stok dan sumber data permintaan, tidak mencakup resep
> elektronik, kasir atau *point of sale*, maupun **manajemen keuangan**.

Pencarian kata "bayar", "pembayaran", "jatuh tempo", "hutang", "lunas", dan "tagihan" di
`draft_isi_ta.md` maupun `draft_isi_ta_4-5.md` tidak menghasilkan satu pun kecocokan.

Jadi pembayaran dan jatuh tempo **di luar lingkup penelitian**, dan membangunnya justru akan
bertentangan dengan batasan yang sudah tertulis. Tidak perlu masuk Bab V sebagai saran pun, kecuali
peneliti memang ingin menyebutnya di sana.

Analisis penempatan disimpan di bawah ini kalau suatu saat dibutuhkan setelah sidang: jatuh tempo
melekat pada faktur PBF (RO), bukan penjualan; kolom `status` dan `no_payment` pada tabel `orders`
justru **sudah dihapus** migrasi `2026_09_13_000004`; dan pembayaran harus jadi aksi terpisah yang
tidak menyentuh kartu stok, karena menyimpan form RO memicu `syncReceipt()` yang menulis ulang ledger
dan HPP.

---

## 11. Pertanyaan A: bagaimana pengguna tahu ada dua batch, salah satunya akan kedaluwarsa?

**Yang sudah memberi tahu:**

| Permukaan | Isi |
|---|---|
| Notifikasi harian 08:00 | Batch bersisa dengan ED ≤ 90 hari |
| Widget "Obat Mendekati Kedaluwarsa" | Per batch, bukan per obat |
| Kartu stok detail | Satu baris per lapisan, lengkap dengan Batch dan ED |
| Form stok opname | Daftar lapisan per obat |
| Form penjualan | Pratinjau FEFO: batch mana yang akan diambil |

**Yang belum:** di daftar obat dan daftar kartu stok, satu obat tampil satu baris tanpa petunjuk ED
sama sekali. Obat X dengan batch A (ED 2 bulan lagi, sisa 30) dan batch B (ED 2 tahun, sisa 100)
hanya terlihat sebagai "stok 130, tersedia".

**Tiga perubahan yang sudah diputuskan menutup lubang ini**, tanpa fitur baru: kolom "ED terdekat"
berwarna di daftar kartu stok (§3.1), baris berwarna di widget Kedaluwarsa (§1.2), dan kolom Batch
yang benar di detail penjualan (§5). Laporan "Obat Akan Kedaluwarsa" (§4) menjadi lapis keempat untuk
pemeriksaan berkala.

---

## 12. Pertanyaan B: kalau user salah catat di PO atau RO?

Mekanismenya sudah ada dan sudah matang, jadi ini soal dokumentasi, bukan kode.

| Dokumen | Koreksi yang diizinkan |
|---|---|
| PO | Bebas diedit dan dihapus; PO belum menyentuh stok |
| RO | `syncReceipt()`: batch, ED, dan harga boleh berubah kapan saja; jumlah hanya boleh turun sampai batas yang sudah terjual; baris boleh dihapus hanya bila lapisannya belum terpakai |
| Hapus RO | Ditolak bila ada batch yang sudah terjual, dengan pesan yang mengarahkan ke Stok Opname |
| Penjualan | Tidak ada edit; hapus lalu buat ulang, stok kembali ke batch asalnya |

Jadi jawabannya: koreksi langsung kalau barang belum terpakai, lewat Stok Opname kalau sudah. Aturan
ini sudah tertulis sebagai R8 di CLAUDE.md dan sudah diuji (`ProcurementTest`, `FefoLayersTest`).

**Tidak perlu ditulis sebagai batasan**, karena jalur koreksinya memang ada. Yang kurang hanya satu
kalimat penjelas di layar edit RO supaya pengguna tahu batasnya sebelum menabraknya, plus satu
paragraf di Bab III.

---

## 13. Pertanyaan C: leveling satuan, ditulis sebagai batasan (K4)

**Data riil.** Dari 127 obat di `database/seeders/data/master-data-obat.csv`:

| Satuan jual | Jumlah obat |
|---|---|
| Tablet | 69 |
| Botol | 20 |
| Tube | 10 |
| Kaplet | 9 |
| Kapsul | 5 |
| Vial | 2 |
| **Strip** | **2** |
| Pasang, Ampul | 1 masing-masing |

Hampir semua yang bersatuan Tablet dibeli per BOX isi 100 atau 200. Jadi kekhawatiran peneliti bukan
kasus langka, itu mayoritas katalog: kalau pembeli minta 1 strip, kasir harus tahu 1 strip berapa
tablet dan mengetik angka tabletnya.

**Inti masalahnya lebih sempit dari "leveling":** sistem tidak menyimpan **isi per strip** di mana
pun. `pack_size` adalah isi kemasan **beli** (Box = 100 tablet), bukan isi strip. Yang hilang satu
datum, bukan satu mekanisme.

**Keputusan (K4): data isi per strip tidak tersedia, jadi tidak dibangun.** Penjualan tetap dicatat
dalam satuan jual, dan konversi strip ke satuan jual dilakukan kasir. Ini ditulis sebagai **poin baru
di Batasan Masalah** naskah.

Usulan kalimatnya:

> Pencatatan penjualan dilakukan dalam satuan jual terkecil yang ditetapkan pada data obat, misalnya
> tablet. Sistem tidak menyediakan konversi satuan antara kemasan eceran seperti strip dan satuan
> jual, sehingga penjualan dalam bentuk strip dicatat setelah dikonversi ke satuan jual oleh petugas.

Kalau suatu saat data isi strip tersedia, jalur pengembangannya sudah jelas dan berongkos kecil:
kolom `sell_pack_unit_id` dan `sell_pack_size` di `medicines`, lalu form penjualan memakai pola
konversi `PackLine` yang sudah dipakai PO dan RO. Yang tersimpan ke ledger tetap satuan jual,
sehingga kartu stok, HPP, FEFO, dan C1–C4 tidak berubah sama sekali. Layak ditulis di Bab V sebagai
saran pengembangan.

---

## 14. Urutan kerja dan dua tenggat yang berbeda

Ada **dua** tenggat yang sering tertukar, dan butir-butir di bawah dibagi menurut keduanya:

| Tenggat | Tanggal | Yang terpengaruh |
|---|---|---|
| **Pembekuan angka Bab IV** (T2) | 29 Sep | Apa pun yang mengubah **data tersimpan**, terutama nomor dokumen |
| **Pengambilan tangkapan layar** menjelang T5 | sekitar 2–3 Okt | Apa pun yang mengubah **tampilan**: format tanggal, kolom, dashboard, laporan |

### Status pengerjaan

> Jadwal harian dipindah ke [rencana-sidang-2026-10.md](rencana-sidang-2026-10.md) §8
> (timeline revisi 2026-09-29). Tabel di bawah hanya melacak status tiap butir.

| Butir | Isi | Status |
|---|---|---|
| 1 | Nomor PO, RO, penjualan, opname diseragamkan; helper `DocumentNumber`; migrasi menulis ulang nomor lama | ✅ 2026-09-28, data riil ikut dimigrasi 2026-09-29 |
| 2 | Detail penjualan: `infolist()`, kolom Batch dari baris C sesungguhnya | ✅ 2026-09-29 |
| 3 | Kolom ED terdekat di kartu stok; ambang 30/60/90 dipusatkan (`App\Support\AmbangEd`); Stok Awal dan Stok Akhir ikut jadi subquery, filter periode diperjelas | ✅ 2026-09-29 |
| 4 | Menu obat (buang 3 kolom, label Status Obat); PBF jadi kode + tooltip | ✅ 2026-09-29 |
| 5 | `App\Support\Tanggal`, termasuk tanggal berjam; locale Carbon `id` | ✅ 2026-09-29 |
| 6 | Dashboard: gabung kartu statistik, buang PO Terbuka, tiga widget sebaris | ✅ 2026-09-29 |
| 7 | Halaman Laporan bertab + empat laporan baru | ✅ 2026-09-29 |
| 8 | Tarif PPN pindah ke tiap RO | ✅ 2026-09-29 |
| 9 | Batasan satuan strip ditulis di naskah 1.3 | 🔜 naskah, draf kalimat ada di §13 |
| - | Tombol bayar | ⏸️ dibatalkan (§10) |

Di luar daftar itu, dikerjakan menyusul keputusan peneliti: penanda simulasi dihapus dari data (K15)
dan satuan obat kaleng diperbaiki (K16), keduanya tercatat di §15.

Butir 7 sengaja dipecah lima supaya bisa dihentikan di tengah tanpa meninggalkan menu setengah jadi:
sesudah 7a selesai, menu Laporan sudah utuh dengan dua laporan lama, dan sisanya hanya menambah tab.

Satu commit per butir, suite Pest hijau sebelum dilaporkan, commit dan push ditahan sampai peneliti
menyatakan setuju (aturan CLAUDE.md §9).

**Catatan K8.** Pemeriksaan "Catatan kosong di detail penjualan" menjadi tidak relevan untuk data yang
ada: seluruh catatan penjualan sudah dikosongkan pada 2026-09-29 (§15.1). Medan Catatan tetap dibuat
membaca kolom `orders.note` di `infolist()` butir 2, sehingga penjualan yang diberi catatan lewat form
akan menampilkannya.

### Catatan kecil untuk penyelarasan naskah

CLAUDE.md §4 menyebut batasan ditulis di "Bab 1.4", padahal di `draft_isi_ta.md` Batasan Masalah ada
di **1.3** dan 1.4 adalah Tujuan Penelitian. Perlu diselaraskan saat naskah disunting supaya rujukan
antar dokumen tidak saling bertentangan.

---

## 15. Perubahan data riil 2026-09-29 (sebelum pembekuan angka Bab IV)

Dikerjakan langsung ke basis data lokal, tiap langkah didahului `mysqldump` ke `storage/backups/`.

### 15.1 Penanda simulasi dihapus dari data (K15)

241 penjualan hasil `SimulasiPenjualanSeeder` menyimpan catatan berpenanda `[SIMULASI]`. Catatannya
dikosongkan sehingga penjualan tampil seperti penjualan tanpa catatan. Seeder juga diubah: tidak lagi
menulis penanda apa pun, dan penjaga idempotensinya berpindah dari "sudah ada penjualan bertanda
simulasi" menjadi "sudah ada penjualan sama sekali", supaya seeder tidak pernah menambah baris di
atas transaksi sungguhan.

**Konsekuensi yang harus dijaga:** setelah penanda hilang dari data, satu-satunya tempat status
simulasi dinyatakan adalah **naskah Bab 1.3 dan Bab IV**. Kalimat itu tidak boleh dihapus dari sana.

Catatan teknis: teks catatan di data lama masih memuat em dash, sisa sebelum pembersihan commit
`e4e953a`, sehingga pencocokan persis dengan konstanta di kode tidak menemukan apa pun. Migrasi
penomoran karena itu mencocokkan awalan `[SIMULASI]`, bukan seluruh kalimat.

### 15.2 Obat berkemasan kaleng dikonversi ke satuan Kaleng (K16)

Tiga obat tercatat bersatuan jual Tablet dengan kemasan Kaleng isi 1000, sehingga kartu stok, harga,
dan penjualan semuanya dalam tablet. Akibatnya muncul nota penjualan 16.800 tablet sekali transaksi
dan HPP Rp 5 per tablet. Karena apotek menjual obat kaleng **per kaleng**, satuan jualnya diperbaiki.

| Obat | Sebelum | Sesudah |
|---|---|---|
| CTM 4MG KLG TRIMAN | 28.000 tablet, min 1.000, HPP Rp 4 | 28 kaleng, min 1, HPP Rp 3.100 |
| GG TRIMAN KLG | 13.800 tablet, min 1.000, HPP Rp 7 | 14 kaleng, min 1, HPP Rp 6.750 |
| IFIDEX 0,5MG KLG | 70.000 tablet, min 1.000, HPP Rp 5 | 70 kaleng, min 1, HPP Rp 4.500 |

Jalannya: penjualan dibalik lewat `reverseSale`, master diperbaiki, item penerimaan dikonversi
(jumlah ke kaleng, harga per kaleng), lapisan disusun ulang lewat `syncReceipt`, item penjualan
dibulatkan ke kaleng terdekat, lalu dicatat ulang lewat `recordSale`. Seluruhnya memakai
`StockMovementService`, bukan tulis ledger langsung, sehingga FEFO dan HPP tetap konsisten.

Efek sampingan yang menguntungkan: HPP ketiga obat sekarang **tepat**. Sebelumnya HPP disimpan sebagai
bilangan bulat dibulatkan ke atas, sehingga Rp 3,10 per tablet tersimpan sebagai Rp 4, meleset 29
persen. Dalam satuan kaleng angkanya bulat apa adanya.

**Dampak ke hasil SAW: tidak ada pada 10 besar.** Peringkat 1 tetap NIFEDIPINE 10MG DEXA dengan
V 0,8400, sama dengan berkas verifikasi manual yang sudah dibekukan. Ketiga obat kaleng bergerak ke
tingkat 23, 25, dan 29. Normalisasi obat lain tidak bergeser karena skalanya tetap 1 sampai 5 dan
nilai Min serta Max kolomnya tidak berubah.

Angka lain: omzet Rp 16.769.456 menjadi Rp 16.767.656 (selisih Rp 1.800 dari pembulatan ke kaleng
terdekat), jumlah baris kartu stok tetap 389, tidak ada obat berstok negatif, tidak ada harga jual di
bawah HPP, dan nota terbesar turun dari 16.800 satuan menjadi 900 satuan.

`database/seeders/data/master-data-obat.csv` ikut diperbaiki untuk ketiga obat itu, sehingga deploy
baru menghasilkan satuan yang sama tanpa perlu konversi ulang.

### 15.3 Harga jual dibiarkan apa adanya

Pembulatan harga ke ratusan sempat dicoba lalu **dibatalkan dan dipulihkan dari backup**: obat
berharga Rp 5 sampai Rp 9 ikut naik ke Rp 100, dan omzet melonjak 42 persen. Harga tetap HPP × 1,25
seperti yang sudah didokumentasikan di seeder dan bisa dipertanggungjawabkan asalnya.

### 15.4 Yang perlu diselaraskan di dokumen lain

`rencana-sidang-2026-10.md` T1 masih mencatat "isi Kaleng (diasumsikan 1000 → HPP ~Rp4–6/tablet,
mencurigakan)" sebagai data yang belum dikonfirmasi. Pertanyaan itu sekarang terjawab: isinya memang
1000, tetapi obatnya dijual per kaleng, sehingga satuan jualnya yang keliru, bukan isinya.

---

## 16. Catatan pelaksanaan 2026-09-29 (butir 2 sampai 8)

Beberapa hal berbeda dari rencana awal, atau ditemukan saat mengerjakan. Dicatat di sini supaya
tidak hilang.

### 16.1 Koreksi: dashboard ternyata sudah diuji, query widgetnya tidak

§1.1 menyatakan dashboard tidak ikut smoke test. Itu keliru: `PanelPagesRenderTest` memang membuka
`/admin`. Yang tidak pernah dijalankan adalah **query widget-nya**, karena widget tabel Filament
dimuat tertunda sehingga membuka halaman tidak menyentuh basis data. Itu sebabnya
`orderByRaw("FIELD(...)")` yang khusus MySQL bisa bertahan tanpa ketahuan.

Sekarang widget diuji lewat `Livewire::test()`, dan tes itu langsung gagal pada `FIELD()` sebelum
widget ditulis ulang. Pengujian widget yang benar adalah lewat Livewire, bukan lewat render halaman.

### 16.2 Stok tersedia kini punya bentuk SQL

`Medicine::scopeWithAvailableStock()` menambahkan kolom `stok_tersedia` lewat subquery. Sebelumnya
stok tersedia hanya ada di `StockCardService::availableStock()` yang dipanggil per obat, sehingga
mengurutkan tabel menurut sisa stok tidak mungkin dilakukan di SQL. Aturannya tetap satu: sisa
lapisan yang belum kedaluwarsa dikurangi baris C yang tidak teratribusi.

### 16.3 Nama bulan Indonesia tanpa mengubah locale aplikasi

`Carbon::setLocale('id')` di `AppServiceProvider`, bukan `APP_LOCALE=id`. Filament memformat tanggal
lewat `translatedFormat()` sehingga seluruh kolom ikut, sementara label bawaan Filament tetap seperti
sekarang. Mengubah locale aplikasi akan menerjemahkan seluruh antarmuka Filament dan mengubah semua
tangkapan layar.

### 16.4 Satu format tanggal, dengan satu pengecualian

`expired_month` di form penerimaan **tetap** `m-Y`. Itu nilai input yang diurai balik oleh
`parseExpiredMonth()`, bukan teks tampilan. Tes `ProcurementTest` menangkapnya ketika sempat ikut
diubah.

### 16.5 Menu Laporan: tab, bukan satu halaman raksasa

Tiap laporan tetap halaman tersendiri; hanya satu yang terdaftar di sidebar, sisanya dicapai lewat
tab (`App\Support\MenuLaporan`). Dua laporan lama beserta ekspornya tidak dibongkar. Tab yang tidak
boleh diakses pengguna tidak ditampilkan, mengikuti `HasPageShield` tiap halaman.

### 16.6 Cacat lama yang ikut diperbaiki

- Cetakan penerimaan menampilkan teks mentah `($item->pack_size > 1)` karena penjaga `@if` tertulis
  sebagai teks biasa. Ikut tercetak di setiap lembar penerimaan.
- Lima `placeholder()` memakai karakter em dash, melanggar aturan penulisan CLAUDE.md §10.
- `StockCardService::getReferenceNumber()` membungkus nomor dokumen dengan `str_pad(..., 6, '0')`,
  sisa zaman nomor masih berupa angka polos.

### 16.7 Izin Shield

Empat widget statistik digabung menjadi `RingkasanStatWidget` dan widget PO Terbuka dihapus, sehingga
lima izin lama menjadi satu. `shield:generate` hanya membuat izin, tidak memangkas yang usang, jadi
lima baris izin milik widget yang sudah tidak ada dihapus manual dari basis data lokal. Pemasangan
baru tidak akan memilikinya karena kelasnya memang tidak ada.

Empat halaman laporan baru menambah empat izin; `RoleSeeder` sudah memuatnya untuk ketiga peran.

---

## 17. Batch kembar: satu batch, dua penerimaan (2026-09-29)

Widget kedaluwarsa menampilkan dua baris untuk batch yang sama, misalnya MICROGYNON LIBI 10'S batch
2310430 dengan sisa 38 dan 28. Itu benar sebagai data: keduanya lapisan berbeda dari dua faktur
berbeda (01955/NPM/5/24 tanggal 07 Sep dan 02026/NPM/5/24 tanggal 11 Sep). Tetapi salah sebagai
jawaban atas pertanyaan "batch mana yang akan kedaluwarsa", karena di rak hanya ada satu tumpukan.

### 17.1 Sebaran kasusnya

Pada data riil 2026-09-29, dari 140 lapisan:

| Pola | Jumlah |
|---|---|
| Batch sama dan ED sama, lebih dari satu lapisan | 9 kelompok, masing-masing 2 lapisan |
| Di antaranya berasal dari satu faktur yang sama | 1 (ALLOPURINOL 100MG NOVA, dua baris dalam satu faktur) |
| Batch **berbeda** tetapi ED sama | 1 (RECO TM, ED Sep 2028) |
| Batch dengan ED berbeda (salah ketik) | 0 |
| Lapisan tanpa nomor batch | 0 |

Setelah digabung, 140 lapisan menjadi 131 baris.

### 17.2 Kuncinya nomor batch, bukan tanggal kedaluwarsa

RECO TM membuktikan kenapa. Dua batch berbeda bisa kebetulan kedaluwarsa pada bulan yang sama, dan
menyatukannya akan menghapus nomor batch, yaitu identitas yang dipakai saat retur ke PBF atau
pemusnahan. Pengelompokan memakai `medicine_id` + `batch_number` (`App\Support\BatchBersisa`).

Bila suatu saat satu batch tercatat dengan dua ED berbeda karena salah ketik, yang ditampilkan adalah
ED yang paling dekat, karena itu peringatan yang lebih aman.

### 17.3 Di mana digabung, di mana tidak

| Layar | Perlakuan |
|---|---|
| Widget Obat Mendekati Kedaluwarsa | Digabung per **obat** (ED terdekat di antara batch bersisa) |
| Laporan Akan Kedaluwarsa | Digabung per batch, sisa dan nilai terancam dijumlahkan |
| Pratinjau FEFO di form penjualan | Digabung per batch, satu chip per batch |
| **Kartu Stok** | **Tetap satu baris per dokumen**, sesuai sifatnya sebagai buku besar |
| Alokasi FEFO, HPP, koreksi R8 | Tetap per lapisan, tidak tersentuh |

Nilai terancam dihitung dari HPP tiap lapisan, tidak mengasumsikan harga kedua faktur sama. Pada data
sekarang kebetulan sembilan kelompok itu berharga sama, tetapi faktur berikutnya bisa berbeda.

### 17.4 Yang belum dikerjakan: Stok Opname

Form opname masih menampilkan lapisan satu per satu, sehingga untuk batch kembar petugas melihat dua
baris padahal di rak hanya ada satu tumpukan. Menggabungkannya bukan pekerjaan tampilan: penyesuaian
wajib menunjuk `layer_stock_id`, jadi perlu ditetapkan dulu aturan pembagian selisihnya, misalnya
kekurangan mengurangi lapisan tertua lebih dahulu mengikuti FEFO, dan kelebihan ditambahkan ke
lapisan terbaru. Itu mengubah cara menulis ledger dan menyentuh aturan R8 beserta tesnya, jadi
diputuskan terpisah.

### 17.5 Widget kedaluwarsa: satu baris per obat

Kolom widget dipangkas atas permintaan peneliti menjadi nama obat dan sisa hari saja. Begitu kolom
batch hilang, baris per batch menjadi ambigu: dua batch berbeda milik obat yang sama bisa kedaluwarsa
pada bulan yang sama, sehingga muncul dua baris yang benar-benar tidak bisa dibedakan. Pada data riil
2026-09-29 itu terjadi pada RECO TM (batch 0091223019 dan 0091223003, keduanya ED Sep 2028).

Karena itu widget memakai `BatchBersisa::perObat()`: satu baris per obat, memakai ED terdekat di
antara batch yang masih bersisa. Hasilnya 127 baris tanpa nama ganda. Rincian per batch tetap ada di
Laporan Akan Kedaluwarsa dan Kartu Stok.

### 17.6 Pelajaran: SQLite longgar, MySQL ketat

Versi pertama pengelompokan ini memakai `GROUP BY` biasa. Suite tes hijau, tetapi halaman langsung
galat di MySQL:

> Expression #2 of ORDER BY clause is not in GROUP BY clause and contains nonaggregated column
> `sipokat.medicine_stocks.id` ... incompatible with sql_mode=only_full_group_by

Sebabnya tabel Filament menambahkan `order by medicine_stocks.id` sebagai pemecah seri, dan kolom itu
tidak ada di GROUP BY. SQLite menerimanya, MySQL dengan `ONLY_FULL_GROUP_BY` menolak.

Perbaikannya: pengelompokan dibungkus sebagai subquery, sehingga `id` menjadi kolom biasa milik tabel
turunan dan pengurutan, penyaringan, serta penghitungan halaman bekerja apa adanya.

Ini kebalikan dari kasus `FIELD()` di §16.1, dan pelajarannya satu: **suite tes berjalan di SQLite,
jadi kecocokan dengan MySQL tidak pernah teruji di sana.** Query agregat yang baru sebaiknya
diverifikasi sekali ke MySQL sebelum dianggap selesai. Sebagai penjaga, ada tes yang memastikan
bentuk query-nya tetap subquery (`BatchBersisaTest`), karena bentuk itulah yang membuatnya aman.