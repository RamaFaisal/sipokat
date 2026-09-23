# Rencana Menuju Sidang Oktober 2026

> Disusun 2026-09-18, timeline mingguan ditambahkan 2026-09-19 (hari dikoreksi: 18 Sep 2026 = Jumat).
> Asumsi yang disepakati peneliti: sidang **12–16 Oktober 2026**, naskah masuk ke pembimbing
> **5–9 Oktober**, push ke `origin` + code freeze **1 Oktober**, deploy VPS **sesudah push**
> (laptop lokal tetap disiapkan sebagai cadangan), data penjualan **simulasi yang disetujui
> pembimbing**. Pembaruan naskah dijadwalkan di sini sebagai tonggak; rinciannya menunggu arahan peneliti.

---

## 1. Kondisi Awal (2026-09-18)

| Area | Kondisi | Konsekuensi |
|---|---|---|
| Kode | E0–E9 selesai; 108 tes Pest hijau (1.088 asersi); audit 8 poin lolos terhadap data riil; cabang `revisi-2026-09` 1 commit di depan `origin` | Tidak ada fitur baru sisa pekerjaan kode hanya data, pengujian, deploy, dan perbaikan cacat |
| Data lokal (MySQL) | 127 obat riil, 10 PBF, 15 RO (14 faktur NPM digeser ke bulan berjalan + 1 manual), 1 PO, 3 opname, 2 snapshot SAW, **0 penjualan** | **C2 = 0 untuk semua obat** ranking SAW belum bermakna sampai ada penjualan (§3 jalur A) |
| Data yang belum dikonfirmasi apotek | Isi Box 17 obat (diasumsikan 100); pil KB (strip 28 vs box); isi Kaleng (diasumsikan 1000 → HPP CTM/GG Triman/Ifidex ~Rp4–6/tablet, mencurigakan) | Mempengaruhi HPP (C4) dan stok tersedia (C1) obat-obat itu |
| Role & hak akses | DB: `super_admin / manajer / staff` + 146 permission Shield; naskah Tabel 3.2: Admin / Petugas / Pemilik | Penguji akan mencocokkan; perlu disamakan atau naskah menyebut pemetaannya |
| Deploy | Jalur migrasi dari nol diverifikasi di MySQL kosong (`docs/deploy-vps.md`); belum dieksekusi di server | Dikerjakan sesudah push 1 Okt |
| Naskah Bab I–III | `draft_isi_ta.md` §3.4.2 **masih skala lama** (C1 stok absolut, C2 >80, C4 ≤10.000) sistem memakai C1 rasio, C2 ≤10/11–30/31–65/66–100/≥101, C4 ≤2.000/…; definisi C1/C3/C4 dan contoh 3.4.4 belum ditulis ulang; Bab 1.4 belum diperbarui; checklist format poin 3–8 (`revisi-penulisan-bab1-3.md`) belum; narasumber belum dikonfirmasi | **Ketidaksesuaian paling berbahaya di sidang**: tabel naskah ≠ tabel sistem |
| Naskah Bab IV–V | `draft_isi_ta_4-5.md` basi (rak, harga di master, tanggal produksi, keterbatasan "tanpa stok per batch" yang sudah dibangun); semua placeholder `【…】` kosong | Ditulis ulang dari sistem aktual sesudah data dan screenshot ada |

---

## 2. Tonggak (mundur dari sidang)

| # | Tonggak | Tanggal | Syarat lolos |
|---|---|---|---|
| T1 | **Data kunci terkunci** | Min 27 Sep | Simulasi penjualan disetujui & terpasang; 3 konfirmasi data dijawab (atau ditetapkan sebagai asumsi); `sipokat:recalculate-saw` menghasilkan ranking yang C2-nya hidup |
| T2 | **Angka Bab IV dibekukan** | Sel 29 Sep | Snapshot SAW "final" tersimpan; tabel nilai mentah, skor, normalisasi, Vi, tingkat diekspor; satu obat diverifikasi manual sel per sel |
| T3 | **Push + code freeze** | Kam 1 Okt | Semua commit di `revisi-2026-09` dipush; sesudah ini hanya `fix:` untuk cacat yang ditemukan pengujian/demo |
| T4 | **Deploy VPS selesai** | Sel 6 Okt (paling lambat) | Data = salinan `mysqldump` lokal (bukan seeder ulang lihat R2); cron aktif; role tersusun; HTTPS; login diuji dari HP |
| T5 | **Naskah lengkap Bab I–V** | Min 4 Okt | Bab III sinkron dengan sistem; Bab IV berisi screenshot, Tabel 4.1–4.2, verifikasi manual, hasil blackbox; Bab V diperbarui |
| T6 | Naskah masuk pembimbing | Sen 5 – Jum 9 Okt | Sesuai tenggat prodi |
| T7 | **Gladi bersih demo** | Jum 9 – Min 11 Okt | Skenario 10–12 menit dijalankan 2× tanpa hambatan di VPS dan di laptop cadangan |
| T8 | **Sidang** | Sen 12 – Jum 16 Okt | |

---

## 3. Jalur Kerja

### A. Data (T1–T2)

| # | Pekerjaan | Pemilik | Tenggat |
|---|---|---|---|
| A1 | Konfirmasi ke apotek: isi Box 17 obat, pil KB, isi Kaleng. Jawaban → perbaiki `FakturNpmSeeder::ASSUMED_BOX_SIZE` / master obat → `migrate:fresh --seed` + seeder faktur → audit ulang HPP. Tidak terjawab sampai tenggat → tetap asumsi sekarang, dicatat di Bab 1.4/IV sebagai asumsi | peneliti (tanya), Claude (terapkan) | Kam 24 Sep |
| A2 | **Rancangan simulasi penjualan** untuk diajukan ke pembimbing (satu halaman): periode = tanggal faktur pertama → hari ini; permintaan per obat proporsional terhadap jumlah yang dibeli pada faktur (apotek membeli apa yang laku), dibagi acak-terkendali per hari dengan *seed* tetap (deterministik); qty ≤ stok tersedia, alokasi FEFO lewat `StockMovementService::recordSale` (bukan tulis ledger langsung); harga jual = HPP × markup tetap (mis. 1,25); ditandai sebagai simulasi. Dinyatakan jujur di Bab 1.4 dan Bab IV | Claude (tulis), peneliti (ajukan) | Sen 21 Sep |
| A3 | Implementasi `SimulasiPenjualanSeeder` / perintah `sipokat:simulasi-penjualan`: **idempoten per hari** (hanya membuat hari yang belum ada), sehingga dijalankan ulang menjelang sidang memperpanjang data sampai hari itu; tes Pest: total qty ≤ stok, tidak ada baris C ke lapisan kedaluwarsa, jalan 2× tidak berlipat | Claude | Jum 25 Sep |
| A4 | Jalankan `sipokat:recalculate-saw`; periksa sebaran skor C1–C4 (tidak boleh satu kriteria seragam); simpan snapshot | Claude | Sab 26 Sep |
| A5 | Ekspor bahan angka Bab IV: nilai mentah C1–C4 seluruh obat, tabel top-10 (Tabel 4.2), langkah verifikasi manual satu obat (Tabel 4.3-an), kandidat contoh 3.4.4 baru dari data riil (rencana §7.7) | Claude | Sel 29 Sep |
| A6 | Cadangan basis data: `mysqldump` beri nama `sipokat-T2-2026-09-29.sql` dasar deploy dan pemulihan demo | Claude | Sel 29 Sep |

### B. Sistem & pengujian (T3)

| # | Pekerjaan | Pemilik | Tenggat |
|---|---|---|---|
| B1 | **Role sesuai Tabel 3.2**: seeder role Admin / Petugas / Pemilik dengan matriks permission (Admin semua; Petugas: master, PO/RO, penjualan, opname, kartu stok, laporan; Pemilik: dashboard, SAW, laporan, baca-saja). Ganti nama role lama atau petakan keputusan K2 | Claude | Kam 24 Sep |
| B2 | **Protokol blackbox** Tabel 3.17 → dokumen langkah demi langkah (7 skenario, tiap skenario: data awal, langkah klik, hasil diharapkan, kolom hasil aktual + nama berkas screenshot). Skenario 4 dan 6 menuntut kasus gagal (stok kurang, Σ bobot ≠ 1) | Claude | Jum 25 Sep |
| B3 | **Eksekusi blackbox** di browser, isi hasil aktual + screenshot bukti | peneliti | Rab 30 Sep |
| B4 | Jendela perbaikan cacat dari B3 dan pemakaian harian; suite tes tetap hijau | Claude | Rab 30 Sep |
| B5 | **Daftar screenshot Bab IV** (Gambar 4.1–4.9 + tambahan: kartu stok per batch, opname per batch, alokasi FEFO di form penjualan, detail hitungan SAW, Buat PO dari ranking) tiap gambar: halaman, kondisi data yang harus tampak, ukuran jendela | Claude (daftar), peneliti (ambil) | daftar Jum 25 Sep; ambil Sel 29 Sep – Jum 2 Okt |
| B6 | Sinkronkan `CLAUDE.md` §1 (jumlah tes, data), `README.md`, `PRD.md` dengan kondisi akhir; catat simulasi penjualan | Claude | Rab 30 Sep |
| B7 | **Push** `revisi-2026-09` → `origin`; buka PR ke `main` bila alur prodi/repo memerlukannya; sesudah ini hanya `fix:` | peneliti ("oke push") | **Kam 1 Okt** |

### C. Deploy & demo (T4, T7)

| # | Pekerjaan | Pemilik | Tenggat |
|---|---|---|---|
| C1 | Tentukan penyedia VPS + domain (K5); server siap dengan PHP 8.4, MySQL 8, Nginx | peneliti | Jum 25 Sep |
| C2 | Deploy mengikuti `docs/deploy-vps.md`; **data dari `mysqldump` A6/terbaru**, bukan `--seed` + `FakturNpmSeeder` (R2); `shield:generate`; role B1; cron; `schedule:list`; HTTPS | peneliti (eksekusi), Claude (pendamping) | Jum 2 – Sel 6 Okt |
| C3 | Uji pasca-deploy: login tiap role dari HP & laptop; notifikasi 08:00 muncul keesokan hari; SAW 06:00 menghasilkan snapshot; ekspor Excel/PDF | peneliti | Rab 7 Okt |
| C4 | **Skenario demo sidang** 10–12 menit (urutan: dashboard → ranking SAW → detail hitungan satu obat → Buat PO → RO per faktur dengan batch/ED → penjualan FEFO (chip batch) → kartu stok per batch + HPP → opname per batch → notifikasi → laporan) + skrip "jika ditanya X, tunjukkan Y" | Claude | Sel 6 Okt |
| C5 | **Paket pemulihan demo**: `mysqldump` "demo-ready" + skrip restore satu perintah untuk VPS dan laptop, supaya setiap gladi/demo bisa direset ke kondisi awal | Claude | Sel 6 Okt |
| C6 | Laptop cadangan: Laragon + DB identik dengan VPS; `php artisan optimize`; uji tanpa internet | peneliti | Kam 8 Okt |
| C7 | Gladi bersih 2× (VPS dan lokal), catat waktu tiap segmen | peneliti | Jum 9 – Min 11 Okt |

### D. Naskah (T5–T6) pemilik peneliti; Claude sesuai arahan berikutnya

Urutan disarankan (masing-masing bergantung pada jalur A/B):

| # | Bagian | Yang harus berubah (sumber: `rencana-revisi-2026-09.md` baris 205, 390, 538, 647, 794) | Bergantung pada | Tenggat |
|---|---|---|---|---|
| D1 | Bab I 1.4 Batasan | Hapus "stok tidak dilacak per batch"; tambah "SAW menentukan urutan restock, bukan jumlah"; "ambang C2 dan C4 satu set untuk seluruh satuan"; "data penjualan Bab IV berupa simulasi yang disetujui pembimbing"; retur/pemusnahan lewat opname | | Jum 25 Sep |
| D2 | Bab I 1.1, Bab 2.3, Bab 3.2.2–3.2.4 | Isi data lapangan dari `update-dari-wawancara.md`; konfirmasi nama narasumber; ejaan "Anugrah Husada" | | Sen 28 Sep |
| D3 | Bab III 3.4.2 | Tabel 3.5–3.8 → skala sistem (CLAUDE.md §4); C1 = rasio stok tersedia ÷ batas minimum + paragraf penurunan (ambang wawancara ÷ 20); C2 = proyeksi 30 hari; C3 = ED batch **terjauh** yang bersisa, stok 0 → 0 hari; C4 = HPP rata-rata bergerak per satuan jual + paragraf "modal tertanam vs harga pesanan berikutnya" (rencana §4.8) | | Sen 28 Sep |
| D4 | Bab III 3.4.3 | Kriteria alternatif (obat aktif dengan ≥ 1 baris kartu stok); aturan skor 0 → R = 0; peringkat padat ("Tingkat"); normalisasi `min/X` cost, `X/max` benefit (sudah benar) | | Sen 28 Sep |
| D5 | Bab III 3.4.4 | Contoh dihitung ulang dengan skala baru; ambil 5 obat dari data riil (A5) | A5 | Rab 30 Sep |
| D6 | Bab III 3.4.1 & format | Checklist `revisi-penulisan-bab1-3.md` poin 3–8 (nomor gambar, daftar isi, 2.2, typo, sitasi Lubis & Rosnelly); ERD/Use Case dicek terhadap skema akhir (`UML-plan.md`) | | Rab 30 Sep |
| D7 | Bab IV 4.1 | Tulis ulang dari sistem aktual: master 5 isian tanpa harga/rak; PO → RO per faktur dalam kemasan; penjualan FEFO + HPP; opname per batch; kartu stok per batch; SAW dengan peringkat padat + Buat PO; sisipkan screenshot B5 | B5 | Sab 3 Okt |
| D8 | Bab IV 4.2 | Tabel 4.1 (nilai mentah), Tabel 4.2 (top-10 + tingkat), 4.2.3 verifikasi manual satu obat; paragraf kejujuran simulasi penjualan | A5 | Sab 3 Okt |
| D9 | Bab IV 4.3 | Tabel 4.5 diisi hasil aktual B3 + rujukan screenshot bukti | B3 | Sab 3 Okt |
| D10 | Bab IV 4.4 & Bab V | Pembahasan menjawab 3 rumusan masalah dengan bukti; keterbatasan diperbarui (per batch **sudah**; skala diskrit 1–5; simulasi penjualan; hak akses sudah tersusun bila B1 selesai); saran 5.2 diganti (mis. integrasi POS/resep, skala kontinu, pembanding WP/TOPSIS) | D7–D9 | Min 4 Okt |
| D11 | Serahkan ke pembimbing; siklus revisi | | T5 | Sen 5 – Jum 9 Okt |

### E. Persiapan sidang (T8)

| # | Pekerjaan | Pemilik | Tenggat |
|---|---|---|---|
| E1 | **Daftar pertanyaan penguji + jawaban** (perluasan CLAUDE.md §6): kenapa SAW bukan WP/TOPSIS; kenapa C1 rasio; kenapa C3 batch terjauh; kenapa C4 HPP bukan harga master; kenapa FEFO otomatis tanpa pilih batch (analisis 2026-09-18: wawancara §4.2, F0, opname sebagai koreksi); kenapa peringkat padat; kejujuran simulasi; keterbatasan | Claude | Rab 7 Okt |
| E2 | Slide (≤ 15): masalah → tujuan → metode → kriteria & skala → contoh hitung → arsitektur ringkas → demo → hasil (top-10, verifikasi manual, blackbox) → kesimpulan → keterbatasan | peneliti | Kam 8 Okt |
| E3 | Lampiran cetak: tabel SAW lengkap, hasil blackbox, foto faktur asli sebagai bukti data riil, transkrip wawancara | peneliti | Jum 9 Okt |

---

## 4. Timeline Mingguan

Ringkasan: **4 minggu penuh + sisa akhir pekan ini.** Minggu dihitung Senin–Minggu.

| Minggu | Tanggal | Tema | Tonggak yang jatuh |
|---|---|---|---|
| 0 | Sab 19 – Min 20 Sep | Persiapan & keputusan | |
| 1 | Sen 21 – Min 27 Sep | Data kunci + Bab I/III disinkronkan | **T1** (27 Sep) |
| 2 | Sen 28 Sep – Min 4 Okt | Bekukan angka, freeze kode, tulis Bab IV | **T2** (29 Sep) · **T3** (1 Okt) · **T5** (4 Okt) |
| 3 | Sen 5 – Min 11 Okt | Deploy, serahkan naskah, gladi bersih | **T4** (6 Okt) · **T6** (5–9 Okt) · **T7** (9–11 Okt) |
| 4 | Sen 12 – Jum 16 Okt | **Sidang** | **T8** |

---

### Minggu 0 Sab 19 s.d. Min 20 Sep · *Persiapan & keputusan*

| Hari | Claude | Peneliti |
|---|---|---|
| Sab 19 | Rencana ini + timeline; mulai draf A2 (rancangan simulasi penjualan) | Baca rencana; hubungi apotek untuk 3 konfirmasi data (A1/K4) |
| Min 20 | Selesaikan draf A2 | Siapkan pertanyaan untuk pembimbing (K1); pertimbangkan K2, K3, K5 |

**Target akhir minggu:** rancangan simulasi siap diajukan; pertanyaan ke apotek sudah terkirim.

---

### Minggu 1 Sen 21 s.d. Min 27 Sep · *Data kunci + Bab I/III disinkronkan*

| Hari | Claude | Peneliti |
|---|---|---|
| Sen 21 | **A2 final** rancangan simulasi satu halaman | Ajukan A2 ke pembimbing |
| Sel 22 | Mulai **A3** kerangka `sipokat:simulasi-penjualan` + tes idempoten | Kejar jawaban pembimbing & apotek |
| Rab 23 | Lanjut A3 (alokasi FEFO lewat service, seed tetap) | **K1 tenggat** keputusan simulasi |
| Kam 24 | **B1** role Admin/Petugas/Pemilik; terapkan **A1** bila jawaban apotek masuk | **K2, K4 tenggat** |
| Jum 25 | **A3 selesai**; **B2** protokol blackbox; **B5** daftar screenshot; **D1** Bab 1.4 (bila diminta) | **K3, K5 tenggat**; sewa VPS (**C1**) |
| Sab 26 | **A4** jalankan `recalculate-saw`, periksa sebaran skor C1–C4 | Mulai **D2/D3** naskah Bab I & III |
| Min 27 | **T1** tinjau bersama: ranking SAW masuk akal? | Tinjau ranking; putuskan lanjut atau perbaiki simulasi |

**Target akhir minggu (T1):** data penjualan hidup, C2 tidak lagi nol, ranking SAW bermakna, role sesuai Tabel 3.2, protokol blackbox siap dijalankan.

---

### Minggu 2 Sen 28 Sep s.d. Min 4 Okt · *Bekukan angka, freeze kode, tulis Bab IV*

| Hari | Claude | Peneliti |
|---|---|---|
| Sen 28 | Siapkan ekspor **A5** (nilai mentah, skor, normalisasi, Vi, tingkat) | **D2, D3, D4** Bab I 1.4, Bab III 3.4.2 & 3.4.3 |
| Sel 29 | **T2** **A5** ekspor bahan angka + **A6** `mysqldump` baseline | Mulai ambil screenshot (**B5**) hanya setelah T2 |
| Rab 30 | **B4** perbaikan cacat dari B3; **B6** sinkron CLAUDE.md/README/PRD | **B3** eksekusi blackbox 7 skenario + screenshot bukti; **D5, D6** |
| Kam 1 Okt | Siapkan commit terakhir; pastikan suite hijau | **B7 push + code freeze** ("oke push") |
| Jum 2 | Pendamping deploy (**C2**); hanya `fix:` mulai hari ini | **C2** deploy VPS dimulai; screenshot lanjut |
| Sab 3 | Bantu angka/tabel bila diminta | **D7, D8, D9** tulis Bab IV 4.1–4.3 |
| Min 4 | | **T5** **D10** Bab IV 4.4 + Bab V; naskah lengkap |

**Target akhir minggu (T2, T3, T5):** angka Bab IV beku dan tercadangkan, kode berhenti berubah, naskah Bab I–V lengkap, deploy sedang berjalan.

---

### Minggu 3 Sen 5 s.d. Min 11 Okt · *Deploy, serahkan naskah, gladi bersih*

| Hari | Claude | Peneliti |
|---|---|---|
| Sen 5 | Pendamping deploy | **T6 mulai** serahkan naskah ke pembimbing (**D11**); lanjutkan C2 |
| Sel 6 | **C4** skenario demo 10–12 menit; **C5** paket pemulihan (dump + skrip restore) | **T4** deploy VPS selesai (cron, role, HTTPS) |
| Rab 7 | **E1** daftar pertanyaan penguji + jawaban | **C3** uji pasca-deploy (login tiap role dari HP, notifikasi, SAW terjadwal, ekspor) |
| Kam 8 | Bantu slide bila diminta | **E2** slide; **C6** laptop cadangan disiapkan & diuji offline |
| Jum 9 | Siaga perbaikan dari gladi | **E3** lampiran cetak; **gladi bersih #1** di VPS; batas akhir T6 |
| Sab 10 | Perbaikan `fix:` dari temuan gladi | Kerjakan revisi dari pembimbing |
| Min 11 | | **Gladi bersih #2** di laptop cadangan (mode offline); **T7 selesai** |

**Target akhir minggu (T4, T6, T7):** sistem hidup di VPS dan di laptop cadangan, naskah sudah di tangan pembimbing, demo sudah dijalankan dua kali tanpa hambatan.

---

### Minggu 4 Sen 12 s.d. Jum 16 Okt · *Sidang*

| Hari | Kegiatan |
|---|---|
| H-1 (sore sebelum) | Restore data "demo-ready" (**C5**); cek VPS + laptop cadangan; isi baterai; siapkan hotspot HP |
| Pagi hari-H | Buka semua tab yang dipakai demo; login ketiga role; pastikan notifikasi & snapshot SAW tampil |
| Hari-H | **Sidang** ikuti skenario **C4**; rujuk **E1** untuk pertanyaan penguji |
| Sesudah | Catat revisi dari penguji; jadwalkan perbaikan |

---

### Beban Kerja per Minggu (perkiraan)

| Minggu | Claude (jam) | Peneliti (jam) | Catatan |
|---|:---:|:---:|---|
| 0 | 3–4 | 2–3 | Sebagian besar menunggu jawaban apotek/pembimbing |
| 1 | 10–14 | 8–12 | Minggu terberat untuk kode (A3, B1, B2) |
| 2 | 8–10 | 20–25 | Minggu terberat untuk peneliti: blackbox + seluruh Bab IV |
| 3 | 5–7 | 15–20 | Deploy + slide + gladi + revisi pembimbing |
| 4 | 1–2 | 5–8 | Hanya kesiapan dan sidang |

**Jalur kritis = naskah (jalur D).** Kode, data, dan deploy punya 2–4 hari cadangan; naskah tidak.
Kalau harus memilih apa yang dikorbankan saat waktu menipis, korbankan kerapian deploy (pakai laptop
lokal, R3), **bukan** kesesuaian Bab III dengan sistem (R4).

---

## 5. Keputusan yang Perlu Diambil Peneliti

| # | Keputusan | Tenggat | Kalau tidak diputuskan |
|---|---|---|---|
| K1 | Setujui rancangan simulasi penjualan A2 (lalu ajukan ke pembimbing) | Rab 23 Sep | Simulasi dibuat sesuai A2, disetujui pembimbing belakangan |
| K2 | Role: ganti nama `super_admin/manajer/staff` → Admin/Petugas/Pemilik, atau pertahankan nama dan tulis pemetaannya di naskah | Kam 24 Sep | Diganti nama sesuai Tabel 3.2 |
| K3 | Blackbox dijalankan peneliti sendiri, atau bersama petugas apotek (bila prodi meminta bukti pengguna) | Jum 25 Sep | Peneliti sendiri; bukti = screenshot |
| K4 | Jawaban 3 konfirmasi data (Box 17 obat, pil KB, Kaleng) | Kam 24 Sep | Asumsi sekarang dipertahankan dan dicatat |
| K5 | Penyedia VPS + domain | Jum 25 Sep | Deploy bergeser; demo lokal jadi utama |
| K6 | Kapan dan sejauh mana Claude membantu naskah (D1–D10) | kapan pun | Naskah sepenuhnya oleh peneliti; Claude tetap memasok A5, B2, B5, C4, E1 |

---

## 6. Risiko

| # | Risiko | Dampak | Mitigasi |
|---|---|---|---|
| R1 | Pembimbing menolak simulasi penjualan | C2 tak punya dasar | Sheet **Penjualan** pada template import sudah ada minta rekap per obat per bulan dari apotek (cukup jumlah, tanpa tanggal per transaksi) dan impor; skala waktu 2–3 hari |
| R2 | `FakturNpmSeeder` menghitung geser bulan dari **hari ini**: dijalankan di server pada Oktober → faktur & ED bergeser satu bulan lebih jauh daripada DB lokal, angka Bab IV tidak sama dengan yang dibekukan | Bab IV ≠ demo | Pindahkan data ke VPS lewat `mysqldump` (A6/C2); jangan seeder ulang. Bila terpaksa seeder ulang, tambahkan opsi bulan tujuan tetap |
| R3 | VPS terlambat / internet ruang sidang buruk | Demo gagal | Laptop cadangan C6 dengan DB identik; skenario demo tidak bergantung internet |
| R4 | Tabel skala Bab III masih versi lama saat sidang | Penguji menemukan sistem ≠ naskah | D3 dikerjakan minggu 1–2, sebelum Bab IV |
| R5 | Screenshot diambil sebelum data final | Gambar Bab IV ≠ angka Bab IV | Screenshot hanya sesudah T2 (Sel 29 Sep) |
| R6 | Cacat ditemukan sesudah code freeze | Demo terganggu | Hanya `fix:` kecil dengan tes; paket pemulihan C5 untuk reset cepat |
| R7 | Simulasi diperpanjang sampai hari sidang (A3 idempoten per hari) mengubah ranking yang sudah ditulis di Bab IV | Bab IV ≠ layar demo | Bekukan: **jangan** jalankan simulasi lagi sesudah T2; kalau ingin data "segar" saat demo, jelaskan bahwa Bab IV memakai snapshot Sel 29 Sep (riwayat snapshot tersedia di halaman SAW) |
| R8 | 3 konfirmasi data tak terjawab | HPP beberapa obat tidak realistis | Catat sebagai asumsi; pilih obat verifikasi manual (A5) dari yang tidak terdampak |

---

## 7. Cara Memakai Dokumen Ini

- Tiap pekerjaan selesai → tandai di tabelnya dengan tanggal dan commit (pola status eksekusi di
  `rencana-revisi-2026-09.md` §9).
- Aturan commit/push tetap CLAUDE.md §9: Claude mengerjakan → tes hijau → lapor; commit dan push
  menunggu "oke commit" / "oke push".
- Perubahan asumsi (tanggal sidang, keputusan K1–K6) dicatat di bagian atas dokumen ini.
