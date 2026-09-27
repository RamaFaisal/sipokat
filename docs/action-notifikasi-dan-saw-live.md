# Action List Notifikasi Berbasis Aksi & SAW Perhitungan Langsung

> Daftar kerja yang bisa dicentang. Alasan dan analisis lengkap ada di
> [rencana-notifikasi-dan-saw-live.md](rencana-notifikasi-dan-saw-live.md).
> Disusun 2026-09-27.

## Keputusan yang mendasari

| Keputusan | Dasar |
|---|---|
| **Riwayat snapshot SAW dihapus** (menu + penumpukan) | Keputusan peneliti; bukti angka Bab IV sudah aman di berkas `storage/app/exports/verifikasi-manual-saw-2026-09-23.xlsx` |
| **Tabel `saw_calculations` / `saw_calculation_results` dihapus** | Keputusan peneliti 2026-09-27: skema bersih, tanpa tabel yang hanya menampung hasil turunan. Opsi menyimpannya sebagai cache (~35 KB konstan) ditawarkan dan ditolak |
| **SAW dihitung setiap halaman/dashboard dibuka**, tanpa cache | Konsekuensi yang diterima: ~550 ms per tampilan. Kasir tidak menanggung apa pun karena perhitungan tidak terjadi saat menyimpan transaksi |
| Tabel Filament memakai `Table::records()` (data di memori) | Filament 4 mendukung tabel berbasis array; aksi massal "Buat PO" (K11) tetap jalan dengan kunci baris `__key` |
| **Notifikasi dipicu perubahan keadaan**, bukan tiap aksi | Diukur: transisi stok 0,18×/hari vs 14,3 penjualan/hari 80× lebih sedikit kebisingan, tanpa kehilangan satu pun kejadian penting |

---

## Tahap S SAW Perhitungan Langsung ✅ SELESAI 2026-09-27

### S1. Service tidak lagi menyimpan ✅
- [x] `SawCalculationService::calculate(periodStart, periodEnd)` hasil di memori, **tanpa** menulis apa pun
- [x] `columnStats()` Min/Max skor per kriteria (K10) ikut dikembalikan untuk rincian perhitungan
- [x] `execute()` dihapus; baris hasil membawa `__key` agar bisa dipakai tabel Filament berbasis array
- **Hasil uji**: suite Pest hijau; peringkat 1 atas data nyata = NIFEDIPINE Vi 0,8400 cocok dengan berkas Excel verifikasi, matematika tidak berubah

### S2. Halaman & widget menghitung langsung ✅
- [x] `app/Filament/Pages/SawCalculation.php` `Table::records()` dari hasil `calculate()`; tombol header jadi "Hitung Ulang"
- [x] `app/Filament/Widgets/SawTop10RestockWidget.php` sama, 10 baris teratas
- [x] Rincian perhitungan (`saw-result-detail.blade.php`) menerima array (dibungkus `Fluent`) + `columnStats`, tidak lagi query model
- [x] Aksi massal **"Buat PO" (K11) tetap berfungsi** kunci baris `__key` = id obat
- **Hasil uji**: `/admin/saw-calculation` HTTP 200 (±1 s termasuk boot), dashboard HTTP 200

### S3. Jejak riwayat dihapus ✅
- [x] `app/Filament/Resources/SawCalculations/` (4 berkas) dihapus
- [x] `app/Console/Commands/RecalculateSawCommand.php` dihapus
- [x] Model `SawCalculation`, `SawCalculationResult`, dan `SawCalculationPolicy` dihapus
- [x] Jadwal 06:00 dihapus dari `routes/console.php`
- [x] Migrasi `2026_09_27_000001_drop_saw_snapshot_tables` membuang kedua tabel
- [x] 10 permission Shield yatim dihapus (`View:SawCalculation` **tetap** itu izin halaman)
- [x] `RoleSeeder` disesuaikan: `View:SawCalculation` pindah ke daftar halaman; Pemilik boleh memuat ulang karena tidak menulis apa pun
- **Hasil uji**: `schedule:list` = 1 baris (08:00); `/admin/saw-calculations` → 404; kedua tabel hilang dari skema

---

## Tahap N Notifikasi

### N1. Transisi stok (dipicu aksi, saat simpan)
- [ ] `StockCardService::updateMedicineStockStatus()` baca status lama sebelum menimpa
- [ ] Kirim notifikasi hanya bila **turun derajat**: `available` → `almost_empty` → `empty`
- [ ] Pesan menyebut nama obat + angka: *"NIFEDIPINE 10MG DEXA tersisa 157, di bawah batas minimum 200"*
- [ ] Bila satu dokumen (mis. opname) menurunkan **> 3 obat**, gabungkan jadi satu pesan
- [ ] Pemulihan (`empty` → `available`) **tidak** diberi notifikasi
- **Uji**: jual sampai habis → notifikasi muncul **saat simpan**, bukan besok pagi. Dokumen di-rollback → notifikasi ikut batal

### N2. Perbaiki celah kedaluwarsa & PO menua (dipicu waktu)
- [ ] `CheckStockAndExpiryCommand` filter sekarang `whereBetween('expired_date', [hari_ini, +90])` membuat batch yang **sudah** lewat ED terlewat dari peringatan. Pisahkan jadi peringatan tersendiri: *"N batch kedaluwarsa belum ditangani musnahkan/retur lewat opname"*
- [ ] Tambah pemeriksaan **PO terbuka > 14 hari** belum diterima → *"tagih PBF"*
- **Uji**: buat data batch yang ED-nya kemarin dengan sisa > 0 → muncul di peringatan

### N3. Deduplikasi & pengingat berkala
- [ ] Jangan kirim ulang notifikasi yang isinya sama persis bila kondisinya **belum berubah**
- [ ] Kirim ulang hanya sebagai pengingat setelah lewat N hari sejak pemberitahuan terakhir
- **Uji**: jalankan command 2× berturut-turut → jumlah notifikasi **tidak** bertambah

### N4. Penjaga kegagalan cron
- [ ] `CheckStockAndExpiryCommand` dibungkus `try/catch`; kegagalan dikirim sebagai notifikasi ke admin (bukan hanya `report()` ke log yang tidak dibaca siapa pun)
- **Uji**: paksa gagal → notifikasi kegagalan terkirim

---

## Tahap D Sinkronisasi dokumen (bagian SAW ✅, bagian notifikasi menyusul)

- [x] `CLAUDE.md` §2 F-06/F-07, §5 peta kode & daftar perintah snapshot & jadwal 06:00 dicabut
- [x] `docs/PRD.md` klaim "audit trail SAW dengan snapshot historis" dicabut, ERD & alur diperbarui
- [x] `docs/README.md` daftar command terjadwal tinggal satu
- [x] `docs/deploy-vps.md` `schedule:list` kini 1 baris, cron tetap wajib
- [x] `docs/update-dari-wawancara.md` instruksi `recalculate-saw` dicabut
- [ ] `CLAUDE.md` §2 F-04 diperbarui setelah Tahap N selesai
- [ ] `docs/rencana-sidang-2026-10.md` catat perubahan ini di kondisi awal

---

## Cron sesudah perubahan

| Jadwal | Perintah | Nasib |
|---|---|---|
| 06:00 | `sipokat:recalculate-saw` | **Dihapus** SAW dihitung saat dibuka |
| 08:00 | `sipokat:check-stock-and-expiry` | **Tetap** hanya untuk kejadian berbasis waktu (N2, N3) + penjaga (N4) |

Cron 08:00 tidak bisa ikut dihapus: masuknya batch ke zona ED, kedaluwarsanya batch, dan menuanya PO
terjadi karena **kalender bergerak**, bukan karena ada dokumen disimpan. `* * * * * php artisan
schedule:run` di server **tetap wajib**.

---

## Urutan pengerjaan

`S1 → S2 → S3 → N1 → N2 → N3 → N4 → D`

SAW dikerjakan lebih dulu supaya lanskap cron sudah final sebelum penjaga kegagalan cron (N4) dipasang.
Tiap tahap meninggalkan suite tes hijau sebelum lanjut.

---

## Dampak ke naskah (perombakan Bab 1–5 oleh peneliti)

| Bagian | Yang berubah |
|---|---|
| Bab I 1.4 | Hapus pernyataan perhitungan terjadwal; SAW dihitung saat diakses |
| Bab III F-04 | Notifikasi bukan lagi semata pemindaian harian ada yang dipicu perubahan keadaan |
| Bab III F-06/F-07 | Hilangkan "riwayat snapshot" → "perhitungan langsung atas kondisi terkini" |
| Bab IV | Tabel hasil SAW bersumber dari berkas beku Excel, bukan menu Riwayat |
