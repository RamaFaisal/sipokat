# Rencana: Notifikasi Berbasis Aksi & SAW Perhitungan Langsung

> Disusun 2026-09-27. Keputusan peneliti: **riwayat snapshot SAW dihapus**, perhitungan SAW
> ditampilkan selalu terkini; notifikasi dipicu saat keadaan berubah, bukan hanya pemindaian harian.
> Peneliti menyatakan siap karena naskah Bab 1–5 akan dirombak menyeluruh sesudah perubahan ini.
>
> Prasyarat yang sudah aman: bukti angka Bab IV **tidak lagi bergantung pada snapshot database**,
> karena sudah dibekukan sebagai berkas `storage/app/exports/verifikasi-manual-saw-2026-09-23.xlsx`
> (127 obat lengkap dengan nilai mentah, skor, normalisasi, Vi, tingkat, plus rumus hidup).

---

## 1. Alasan Perubahan

| Keadaan sekarang | Masalah |
|---|---|
| SAW hanya dihitung cron 06:00 atau tombol manual | Yang dilihat pengguna bisa basi sampai 24 jam tanpa tanda apa pun |
| `execute()` selalu menulis snapshot baru | Auto-update mustahil tanpa membanjiri riwayat (5.220 entri/tahun) |
| Notifikasi stok hanya dari pemindaian harian | Obat habis jam 10 pagi baru diberitahu besok jam 08:00 |
| Notifikasi diulang tiap hari selama kondisi belum berubah | Lonceng penuh pesan sama, sinyal penting tenggelam |
| Batch yang **sudah** kedaluwarsa keluar dari rentang peringatan | Justru saat itulah tindakan (musnah/retur) diperlukan |
| Cron gagal tanpa pemberitahuan | SAW bisa gagal berhari-hari tanpa ada yang tahu |

Angka pendukung (diukur 2026-09-27 pada 127 obat, 243 penjualan/17 hari):

- Perhitungan SAW penuh: **542 ms** (hitung) + 57 ms (tulis) = 599 ms
- Transisi stok melewati ambang sepanjang riwayat: **3 kali** (0,18/hari) bandingkan 14,3 penjualan/hari
- Notifikasi bila tiap aksi berbunyi: 43/hari; bila hanya transisi: **0,18/hari**

---

## 2. Bagian A Notifikasi

### A.1 Dipicu aksi (berbunyi saat dokumen disimpan)

| # | Pemicu | Tempat pasang | Catatan |
|---|---|---|---|
| N1 | Stok turun melewati batas minimum (aman → menipis) | `StockCardService::updateMedicineStockStatus()` | Baca status lama sebelum menimpa; kirim hanya bila **turun derajat** |
| N2 | Stok jadi habis (→ 0) | sama | Satu mekanisme dengan N1 |

Kenapa satu tempat cukup: method itu sudah dipanggil otomatis di **setiap** mutasi stok penjualan,
penerimaan, opname, bahkan saat dokumen dihapus. Tidak perlu menyentuh form satu per satu.

Aturan:
- Hanya arah **turun** (`available` → `almost_empty` → `empty`). Pemulihan tidak diberitahu.
- Pesan **menyebut nama obat** dan angkanya: "NIFEDIPINE 10MG DEXA tersisa 157, di bawah batas minimum 200".
- Notifikasi adalah baris database di dalam transaksi yang sama → kalau penyimpanan gagal/di-rollback,
  notifikasinya ikut batal sendiri. Tidak perlu penanganan khusus.
- Satu opname bisa menurunkan banyak obat sekaligus → **gabungkan jadi satu pesan per dokumen**
  bila lebih dari 3 obat terdampak.

### A.2 Dipicu waktu (tetap lewat pemindaian harian)

Tidak bisa dipicu aksi, karena yang berubah kalendernya bukan datanya.

| # | Pemicu | Status sekarang | Tindakan |
|---|---|---|---|
| N3 | Batch masuk zona ED ≤ 90 hari | ✅ sudah jalan | Pertahankan |
| N4 | Batch **sudah lewat ED** dan masih bersisa | ❌ celah | Perbaiki filter: rentangnya `[hari_ini, +90]` sehingga yang sudah kedaluwarsa terlewat. Pisahkan jadi peringatan tersendiri ("N batch kedaluwarsa belum ditangani musnahkan/retur lewat opname") |
| N5 | PO terbuka > 14 hari belum diterima | ❌ belum ada | Tambahkan; tindakan: tagih PBF |
| N6 | Pengingat berkala untuk yang belum ditangani | ❌ sekarang mengulang tiap hari | Kirim ulang hanya bila kondisinya **belum berubah** dan sudah lewat N hari sejak pemberitahuan terakhir |

### A.3 Teknis

| # | Pemicu | Tindakan |
|---|---|---|
| N7 | Cron gagal | `CheckStockAndExpiryCommand` dibungkus `try/catch`; kegagalan dikirim sebagai notifikasi ke admin, bukan hanya `report()` ke log yang tidak dibaca siapa pun |

### A.4 Yang sengaja TIDAK diberi notifikasi

Penjualan/RO/PO/opname tersimpan (pelaku sudah lihat toast Filament) · penjualan dihapus (hapus lalu
buat ulang **adalah** alur koreksi normal, S6) · master data baru · stok pulih · PO selesai diterima.

---

## 3. Bagian B SAW Perhitungan Langsung

### B.1 Bentuk akhir

- Halaman **Hitung Prioritas Restock** dan **widget dashboard Top-10** selalu menampilkan hasil
  perhitungan atas kondisi **saat itu juga**.
- **Menu "Riwayat Perhitungan" dihapus** beserta Resource-nya.
- Cron SAW 06:00 dihapus.

### B.2 Cara perhitungan disajikan

`SawCalculationService::execute()` sekarang menyatukan "menghitung" dan "menyimpan riwayat".
Dipecah jadi:

```
calculate(periodStart, periodEnd) → hasil di memori, tanpa menulis apa pun
```

**Keputusan akhir peneliti (2026-09-27): kedua tabel dihapus, tanpa cache.** Opsi menyimpan satu
set baris sebagai cache (~35 KB konstan) sempat diajukan, tetapi ditolak demi skema yang bersih
tanpa tabel yang hanya menampung hasil turunan.

Kekhawatiran awal bahwa widget harus ditulis ulang ternyata tidak terbukti: **Filament 4 mendukung
tabel berbasis data di memori** lewat `Table::records()`, asalkan tiap baris punya kunci unik
`__key`. Jadi tabel ranking dan widget tetap memakai komponen tabel Filament berikut sorting,
badge, tooltip, dan aksi massal "Buat PO" (K11) hanya sumber datanya yang berganti dari query
Eloquent menjadi hasil `calculate()`.

Konsekuensi yang diterima: **tidak ada cache**, sehingga setiap kali halaman SAW atau dashboard
dibuka perhitungan dijalankan lagi (~550 ms). Kasir tidak menanggung apa pun karena perhitungan
tidak terjadi saat menyimpan transaksi.

### B.3 Kapan perhitungan dijalankan

**Setiap halaman SAW atau dashboard dibuka** bukan saat menyimpan transaksi. Dengan begitu ~550 ms
ditanggung orang yang memang sedang ingin melihat angkanya, bukan kasir yang sedang melayani pembeli.
Tidak ada penanda basi maupun cache: hasilnya selalu dihitung dari kondisi terkini.

### B.4 Berkas yang tersentuh

| Berkas | Perubahan |
|---|---|
| `app/Services/SawCalculationService.php` | `execute()` diganti `calculate()` (tanpa menulis) + `columnStats()` |
| `app/Filament/Pages/SawCalculation.php` | `Table::records()` dari hasil hitung; tombol header jadi "Hitung Ulang" |
| `app/Filament/Widgets/SawTop10RestockWidget.php` | Sama, 10 baris teratas; deskripsi "per <waktu>" tetap ditampilkan |
| `resources/views/filament/pages/partials/saw-result-detail.blade.php` | Menerima array (`Fluent`) + `columnStats`, tidak lagi query model |
| `app/Filament/Resources/SawCalculations/**` | **Dihapus** (4 berkas) |
| `app/Console/Commands/RecalculateSawCommand.php` | **Dihapus** |
| `routes/console.php` | Hapus jadwal 06:00 |
| `app/Models/SawCalculation.php`, `SawCalculationResult.php`, `SawCalculationPolicy.php` | **Dihapus** |
| Migrasi | `2026_09_27_000001_drop_saw_snapshot_tables` membuang kedua tabel |
| Permission Shield | Bersihkan permission Resource yang dihapus |
| Tes | `SawCalculationTest`, `PanelPagesRenderTest`, `EndToEndFlowTest` menyesuaikan |

---

## 4. Cron Sesudah Perubahan

Saat ini **ada dua**, sesudah perubahan **tinggal satu**:

| Jadwal | Perintah | Nasib |
|---|---|---|
| 06:00 | `sipokat:recalculate-saw` | **Dihapus** SAW dihitung saat dibuka |
| 08:00 | `sipokat:check-stock-and-expiry` | **Tetap** perannya menyempit ke kejadian berbasis waktu (N3–N6) dan penjaga (N7) |

Cron 08:00 tidak bisa ikut dihapus: masuknya batch ke zona ED, kedaluwarsanya batch, dan menuanya PO
terjadi karena **kalender bergerak**, bukan karena ada yang menyimpan dokumen tidak ada aksi yang
bisa memicunya.

Konsekuensi: `* * * * * php artisan schedule:run` di server **tetap wajib** (`docs/deploy-vps.md` §5).

---

## 5. Urutan Eksekusi

SAW dikerjakan lebih dulu supaya lanskap cron sudah final sebelum notifikasi penjaga-cron (N7) dipasang.

| Tahap | Isi | Titik uji |
|---|---|---|
| S1 ✅ | `execute()` → `calculate()` yang tidak menulis apa pun | Suite hijau; Vi peringkat 1 cocok dengan berkas Excel verifikasi |
| S2 ✅ | Halaman SAW & widget memakai `Table::records()` dari hasil hitung | Jual 1 obat → buka dashboard → angka sudah berubah tanpa klik apa pun |
| S3 ✅ | Hapus Resource Riwayat, command, model, tabel, jadwal 06:00, permission Shield | `schedule:list` = 1 baris; `/admin/saw-calculations` → 404 |
| N1 | Notifikasi transisi stok di `updateMedicineStockStatus()` | Jual sampai habis → notifikasi muncul **saat simpan**, menyebut nama obat |
| N2 | Perbaiki celah batch sudah kedaluwarsa + PO menua | Uji dengan data batch lewat ED |
| N3 | Deduplikasi & pengingat berkala | Jalankan command 2× → tidak ada notifikasi ganda |
| N4 | Penjaga kegagalan cron | Paksa gagal (Σ bobot ≠ 1) → notifikasi kegagalan terkirim |
| D1 | Sinkronkan `CLAUDE.md`, `PRD.md`, `README.md`, `docs/deploy-vps.md` | |

---

## 6. Dampak ke Naskah (dikerjakan peneliti saat perombakan Bab 1–5)

| Bagian | Yang berubah |
|---|---|
| Bab I 1.4 Batasan | Hapus/ubah pernyataan soal perhitungan terjadwal; SAW kini dihitung saat diakses |
| Bab III F-04 | Notifikasi bukan lagi semata pemindaian harian ada yang dipicu perubahan keadaan |
| Bab III F-06/F-07 | Hilangkan "riwayat snapshot"; ganti dengan "perhitungan langsung atas kondisi terkini" |
| Bab IV | Tabel hasil SAW bersumber dari berkas beku `verifikasi-manual-saw-2026-09-23.xlsx`, bukan dari menu Riwayat |
| PRD.md | Klaim "audit trail SAW dengan snapshot historis" dicabut |
| CLAUDE.md §2 | Baris F-07 diperbarui |

---

## 7. Risiko

| # | Risiko | Mitigasi |
|---|---|---|
| R1 | Dashboard terasa lambat (+542 ms saat basi) | Hanya dihitung ulang bila ada perubahan; setelah itu instan. Optimasi query agregat bisa menyusul |
| R2 | Bukti angka Bab IV hilang bersama riwayat | Sudah aman berkas Excel beku sudah ada di luar database |
| R3 | Bobot diubah di kemudian hari → ranking lama tak bisa dijelaskan | Bobot tercatat di CLAUDE.md §4 dan di dalam berkas Excel; bobot memang tidak direncanakan berubah |
| R4 | Notifikasi transisi membanjir saat opname besar | Digabung jadi satu pesan per dokumen bila > 3 obat |
| R5 | Cron 08:00 mati diam-diam di server | N7 memberi tahu saat gagal; tapi kalau cron sama sekali tidak jalan, tidak ada yang bisa memberi tahu verifikasi manual `schedule:list` tetap bagian dari checklist deploy |
