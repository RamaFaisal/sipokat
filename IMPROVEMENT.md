# IMPROVEMENT — Audit Perhitungan SAW Sipokat

Dibuat: 2026-08-20 · **Ditutup: 2026-09-14** (revisi September, commit E5 `434729d` dan sesudahnya).
Lingkup: audit tahapan SAW (Bab 3.4.3–3.4.4) terhadap implementasi kode dan tampilan sistem.

## Status penutupan

| Temuan | Status | Penyelesaian |
|---|---|---|
| T1 Label rumus di modal detail | ✅ | `saw-result-detail.blade.php` menampilkan `min/X` untuk cost dan `X/max` untuk benefit, beserta Min/Max kolom |
| T2 Obat berstok 0 kehilangan C3 | ✅ | C3 = ED batch terjauh yang bersisa; stok tersedia 0 → 0 hari → skor 1 (K1) |
| T3 Aturan skor 0 tidak ada di naskah | ✅ (kode) | Skor 0 → R = 0 hanya untuk kriteria tanpa data; dengan aturan T2 dan HPP, obat aktif berhistori selalu punya skor ≥ 1. Tulis di Bab III |
| T4 Validasi Σ bobot = 1 hanya di UI | ✅ | `SawCalculationService::activeCriteria()` menolak Σ ≠ 1,000; CLI ikut; toggle aktif/non-aktif di tabel dihapus (K7) |
| T5 Pembagi periode C2 meleset di CLI | ✅ | Jumlah hari inklusif bilangan bulat; `RecalculateSawCommand` memakai `today()` sebagai akhir (K2) |
| T6 Celah skala untuk nilai non-bulat | ✅ | Semua rentang inklusif dan sadar desimal; C1 rasio 2 desimal; HPP bilangan bulat dibulatkan ke atas (B2, K14) |
| T7 Vi kembar, peringkat seri arbitrer | ✅ | Peringkat padat ("Tingkat") + tie-breaker tampilan rasio → permintaan → ED (K9) |
| T8 Unit test SAW hilang | ✅ | `tests/Feature/SawCalculationTest.php` (contoh 5 alternatif) + `EndToEndFlowTest` |
| T9 Klaim kriteria bisa dinonaktifkan | ✅ | Toggle dihapus; keempat kriteria wajib aktif. Hapus klaim di naskah |
| T10 Batasan alternatif belum tertulis | ✅ (kode) | Alternatif = obat aktif dengan ≥ 1 baris kartu stok; jumlah yang dikecualikan disimpan di `excluded_count` (K0, K12). Tulis di Bab III |
| T11 Dokumen internal masih skema lama | ✅ | CLAUDE.md, README, `update-dari-wawancara.md` §3/§4.1 disinkronkan 2026-09-14 |

Yang tersisa dari daftar ini hanya pekerjaan **naskah** (T3, T9, T10) — bukan kode. Isi audit asli
dipertahankan di bawah sebagai jejak.

---


## Ringkasan audit per tahapan

| # | Tahap | Status |
|---|---|---|
| 1 | Penentuan alternatif | Benar |
| 2 | Penentuan kriteria | Benar, satu klaim di naskah keliru |
| 3 | Penentuan bobot | Benar, validasi bocor satu sisi |
| 4 | Matriks keputusan | Benar, ada dua celah konversi |
| 5 | Normalisasi | Rumus benar, label di layar salah |
| 6 | Nilai preferensi (Vi) | Benar |
| 7 | Perangkingan | Urutan benar, penyajian nilai seri cacat |
| 8 | Cakupan tahapan | Lengkap 7/7 |

**Kesimpulan:** inti metode sudah benar. Contoh perhitungan Bab 3.4.4 diverifikasi ulang sel per sel
(20 sel matriks, 20 nilai normalisasi, 5 nilai Vi) — seluruhnya cocok dengan seeder dan kode.
Yang bermasalah adalah kasus tepi, label, dan dokumentasi — bukan rumusnya.

---

## Daftar temuan

### T1 — Label rumus normalisasi di modal detail masih rumus lama

- **Berkas:** `resources/views/filament/pages/partials/saw-result-detail.blade.php:63`
- **Masalah:** header kolom tertulis `Normalisasi (R = X/max)` untuk semua kriteria. Untuk C1, C3, C4
  (cost) nilai yang ditampilkan sebenarnya `min/X`. Sisa skema lama (Opsi X) yang belum dibersihkan.
- **Dampak:** modal ini akan jadi Gambar 4.8 dan dasar verifikasi manual 4.2.3 — kontradiksi langsung
  dengan Bab 3.4.3 dan mudah terlihat penguji.
- **Tambahan:** modal tidak menampilkan nilai Min/Max acuan kolom, padahal verifikasi manual butuh itu.
- **Prioritas:** TINGGI — satu baris, nol risiko ke angka.

### T2 — Obat berstok 0 kehilangan kriteria C3 sepenuhnya

- **Berkas:** `app/Models/Medicine.php:91-95` (`nearestExpiryDate()` return null bila `currentStock() <= 0`)
- **Masalah:** C3 raw null → skor 0 → normalisasi 0 → bobot 0,20 hangus.
- **Dampak:** obat habis stok (kandidat restock paling mendesak) justru terpenalti. Vi maksimalnya
  hanya 0,80, kalah dari obat bersisa stok 8 yang bisa mencapai 0,96. Distorsi arah prioritas.
- **Keputusan yang dibutuhkan (metodologis, harus bisa dipertahankan di sidang):** obat tanpa batch
  aktif diberi perlakuan C3 apa — pakai batch terdekat walau stok 0, atau skor netral, atau lainnya.
- **Prioritas:** TINGGI — satu-satunya temuan yang mengubah kualitas rekomendasi sistem.
- **Catatan waktu:** kerjakan SEKARANG atau tidak sama sekali; setelah Bab 4 terisi ongkosnya berlipat.

### T3 — Aturan penanganan skor 0 tidak ada di naskah

- **Berkas:** `app/Services/SawCalculationService.php:165-179`, naskah `docs/draft_isi_ta.md` Bab 3.4.3
- **Masalah:** kode mengecualikan skor 0 saat mencari `Min` (`$positiveScores`) dan memaksa
  normalisasi 0. Pengecualian ini perlu — tanpa itu satu data kosong membuat seluruh kolom cost jadi 0.
  Tapi Bab 3.4.3 hanya menulis `Rij = Min Xij / Xij` tanpa aturan data kosong.
- **Dampak:** rumus di naskah ≠ implementasi.
- **Aksi:** tambah satu kalimat di Bab 3.4.3, mis. "nilai 0 yang menandakan data tidak tersedia
  dikecualikan dari penentuan Min dan Max, dan nilai normalisasinya ditetapkan 0".
- **Prioritas:** SEDANG — edit naskah saja, kode dan angka tidak berubah.

### T4 — Validasi total bobot = 1 hanya di UI, tidak di service/CLI

- **Berkas:** validasi ada di `app/Filament/Pages/SawCalculation.php:165-173`;
  TIDAK ada di `SawCalculationService::execute()` maupun `app/Console/Commands/RecalculateSawCommand.php`
- **Masalah:** snapshot terjadwal (`sipokat:recalculate-saw`) bisa jalan dengan total bobot ≠ 1
  sehingga Vi keluar rentang 0–1. Widget dashboard memakai snapshot itu.
- **Bocor kedua:** `app/Filament/Resources/SawCriterias/Schemas/SawCriteriaForm.php:73` hanya menolak
  total `> 1.000`, tidak menolak `< 1.000`. Admin bisa menyimpan konfigurasi bertotal 0,900.
- **Aksi:** pindahkan/duplikasi validasi ke `execute()`; rapatkan validasi form dua sisi.
- **Prioritas:** SEDANG — murah, tidak mengubah angka selama bobot memang 0,3/0,3/0,2/0,2.

### T5 — Pembagi periode C2 meleset (~3% under-estimate) di jalur CLI

- **Berkas:** `app/Services/SawCalculationService.php:118-131` (`getMonthlyDemand()`)
- **Masalah:** `$start->diffInDays($end) + 1`. Carbon 3.11 mengembalikan float, dan
  `RecalculateSawCommand` memakai `endOfDay()` → pembagi jadi 30,99 hari bukan 30.
- **Dampak:** permintaan C2 under-estimate ~3% pada snapshot terjadwal. Jalur form (DatePicker,
  tengah malam) tidak terpengaruh. Berpotensi bikin angka manual 4.2.3 tidak cocok dengan sistem.
- **Aksi:** normalisasi ke `startOfDay()` di kedua sisi sebelum `diffInDays`, atau bulatkan.
- **Prioritas:** SEDANG — perbaikan satu baris.

### T6 — Celah skala untuk nilai non-bulat (khususnya harga)

- **Berkas:** `database/seeders/SawCriteriaSeeder.php` (scale_rules C1/C3/C4),
  `app/Models/SawCriteria.php` → `convertToScore()`
- **Masalah:** rentang hanya rapat untuk bilangan bulat. Harga Rp10.000,50 tidak cocok rule mana pun
  (≤10000 atau ≥10001) → skor 0 → normalisasi 0 → bobot C4 hilang diam-diam.
  `purchase_price` bertipe decimal sehingga secara teknis mungkin.
- **Aksi:** CEK DULU data aktual apakah ada harga berdesimal. Kalau tidak ada, biarkan dan tidak perlu
  disebut di naskah. Kalau ada, ubah ke batas eksklusif.
- **Prioritas:** RENDAH — verifikasi dulu, jangan langsung ubah (mengubah scale_rules = seed ulang +
  hitung ulang).

### T7 — Banyak Vi kembar, peringkat nilai seri arbitrer

- **Berkas:** `app/Services/SawCalculationService.php:69-73` (`sortByDesc` + `rank = i + 1`)
- **Masalah:** karena normalisasi diterapkan pada skor 1–5 (bukan nilai mentah), dengan ratusan obat
  hampir pasti Min=1 dan Max=5 di tiap kolom. Normalisasi jadi tabel tetap:
  benefit → 0,2 / 0,4 / 0,6 / 0,8 / 1,0 ; cost → 1 / 0,5 / 0,33 / 0,25 / 0,2.
  Akibatnya Vi hanya punya maksimal 625 nilai unik → banyak obat bernilai Vi identik.
  Sistem tetap memberi peringkat berurutan berbeda (mis. 8 dan 9) seolah ada bedanya; urutan
  sebenarnya ditentukan `medicine_id` — deterministik tapi tanpa dasar.
- **Dampak:** Tabel 4.2 "sepuluh teratas" sulit dipertahankan bila peringkat 8–15 nilainya sama persis.
- **Aksi yang disarankan:** JANGAN ubah rumus. Tambah tie-breaker deterministik (stok mentah terkecil,
  lalu permintaan tertinggi) + jelaskan satu paragraf di 4.2.2 bahwa Vi identik = prioritas setara.
- **Jalan yang DITOLAK:** normalisasi langsung pada nilai mentah. Menghilangkan masalah di akarnya,
  tapi membatalkan seluruh Tabel 3.5–3.8 dan contoh 3.4.4 — tidak sepadan menjelang sidang.
- **Catatan:** ini nyambung dengan saran Bab 5.2 yang sudah ditulis ("memperhalus skala penilaian
  kriteria") dan usulan pembandingan dengan WP/TOPSIS.
- **Prioritas:** SEDANG.

### T8 — Unit test verifikasi SAW hilang dari repo

- **Berkas:** `tests/Unit/` hanya berisi `ExampleTest.php`. `SawNormalizationTest.php` yang dulu
  membuktikan kode = contoh 3.4.4 (A1 = 0,96) sudah tidak ada.
- **Dampak:** klaim verifikasi di 4.2.3 tanpa penopang otomatis; tidak ada jaring pengaman kalau kode
  diubah lagi.
- **Aksi:** kembalikan test yang mereproduksi contoh Bab 3.4.4.
- **Prioritas:** SEDANG bila T2 dikerjakan (wajib, sebagai pengaman), RENDAH bila kode tidak diubah.

### T9 — Klaim "kriteria dapat diaktifkan atau dinonaktifkan" di naskah keliru

- **Berkas:** `docs/draft_isi_ta_4-5.md:86` (sub-bab 4.1.7)
- **Masalah:** tombol toggle `is_active` memang ada, tetapi begitu salah satu C1–C4 dinonaktifkan,
  `execute()` langsung melempar "Kriteria SAW kurang lengkap". Sistem mengunci tepat 4 kriteria
  (`canCreate()` dan `canDelete()` = false, kolom DB hard-coded `c1..c4`).
- **Aksi:** koreksi kalimat — yang benar-benar dapat diubah adalah bobot, nama, jenis, dan skala.
- **Prioritas:** SEDANG — edit naskah saja.

### T10 — Alternatif: batasan dan jumlah belum tertulis di naskah

- **Berkas:** naskah Bab 3.4.3 poin 1 dan Bab 4.2.1
- **Masalah:** Bab 3 tidak menyebut bahwa alternatif dibatasi pada obat berstatus aktif
  (`status = 'active'`, plus SoftDeletes mengecualikan obat terhapus). Bab 4.2.1 juga belum menyebut
  jumlah total alternatif (m), padahal sistem sudah menyimpannya di `total_alternatives`.
- **Aksi:** tulis kriteria pemilihan alternatif di Bab 3; cantumkan angka m di Bab 4.2.
- **Prioritas:** SEDANG — penguji hampir pasti menanyakan "berapa obat yang dihitung?".

### T11 — Dokumen internal masih mendeskripsikan skema lama (Opsi X)

- **Berkas:** `CLAUDE.md:66,85,211-213` dan `docs/PRD.md:323`
- **Masalah:** masih menulis normalisasi `R = X/max` universal dan hasil lama A2 = 0,94, padahal kode
  dan naskah sudah memakai rumus baku (benefit `X/max`, cost `min/X`, A1 = 0,96).
- **Dampak:** risiko mengutip angka lama ke dalam naskah. Penguji tidak membaca file ini.
- **Prioritas:** RENDAH — tapi kerjakan sebelum mulai mengisi angka Bab 4.

---

## Urutan pengerjaan yang disarankan

**Sebelum mulai mengisi angka dan mengambil screenshot Bab 4** (ongkosnya nol sekarang, berlipat nanti):

1. T1 — label rumus di modal detail (+ tampilkan Min/Max acuan)
2. T2 — perlakuan C3 untuk obat berstok 0 **(butuh keputusan metodologis dari peneliti dulu)**
3. T4 — validasi bobot di service/CLI + rapatkan validasi form
4. T5 — pembagi periode C2
5. T7 — tie-breaker deterministik
6. T3, T9, T10 — koreksi dan penambahan di naskah

**Setelah itu:**

7. T6 — cek data harga berdesimal, ubah hanya bila terbukti ada
8. T8 — kembalikan unit test (wajib bila T2 dikerjakan)
9. T11 — sinkronkan CLAUDE.md dan PRD.md

---

## Yang TIDAK perlu diubah

- Rumus normalisasi benefit/cost — sudah sesuai Bab 3.4.3
- Bobot 0,30 / 0,30 / 0,20 / 0,20 — total tepat 1
- Tabel skala 3.5–3.8 dan seeder — konsisten satu sama lain, arah natural sudah benar
- Contoh perhitungan Bab 3.4.4 — diverifikasi ulang, seluruh angkanya benar
- Perangkingan contoh 5 alternatif (A1, A2, A7, A9, A6) — benar
- Desain penguncian 4 kriteria — konsisten dan aman
