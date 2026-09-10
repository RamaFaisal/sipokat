# Update dari Wawancara — Apotek Anugrah Husada

> Hasil wawancara lapangan yang mengubah konfigurasi sistem dan naskah TA.
> Sumber jawaban: `docs/instrumen_pengumpulan_data.md`.
> Dibuat: 2026-09-10

---

## 1. Profil Objek Penelitian

| Item | Isi |
|------|-----|
| Nama apotek | Apotek Anugrah Husada |
| Alamat | Ds. Raji, Kec. Demak, Kab. Demak |
| Tahun mulai beroperasi | 2020 |
| Jumlah pegawai | 6 orang |
| Narasumber | *belum dikonfirmasi — akan ditanyakan kembali* |

Dipakai untuk Bab 2.3 (Tinjauan Obyek Penelitian).

---

## 2. Bobot Kriteria — TIDAK BERUBAH

Bobot tetap **C1 0,30 · C2 0,30 · C3 0,20 · C4 0,20** sesuai proposal. Tidak ada perubahan pada
`saw_criteria.weight` maupun Tabel 3.4.

Wawancara tetap memberi pijakan untuk angka ini. Narasumber menilai kepentingan tiap kriteria
secara lisan:

| Kriteria | Penilaian narasumber | Kelompok | Nilai | Bobot |
|---|---|---|---|---|
| C1 Stok | penting sekali | Penting | 3 | 0,30 |
| C2 Permintaan | penting | Penting | 3 | 0,30 |
| C3 Sisa kedaluwarsa | biasa saja | Cukup penting | 2 | 0,20 |
| C4 Harga beli | biasa saja | Cukup penting | 2 | 0,20 |
| | | | **10** | **1,00** |

Konversinya memakai dua tingkat kepentingan (Penting = 3, Cukup Penting = 2), lalu dinormalkan
terhadap total 10. Hasilnya persis sama dengan bobot proposal, sehingga bobot yang semula asumtif
kini berstatus terkonfirmasi lapangan.

**Alasan C3 hanya "biasa saja"** — layak dikutip di Bab III: masa kedaluwarsa obat umumnya
2–3 tahun, sehingga jarang menjadi penentu saat memutuskan restock.

**Catatan konsistensi**: pada pertanyaan pemeringkatan (No. 13) narasumber menempatkan harga beli
di atas kedaluwarsa, sedangkan pada penilaian kepentingan keduanya dinilai sama. Perbedaan ini
muncul karena pemeringkatan memaksa urutan ketat sementara penilaian kepentingan membolehkan nilai
sama. Penilaian kepentingan dipakai sebagai acuan.

---

## 3. Skala Konversi Baru (Tabel 3.5–3.8)

Nilai mentah tiap kriteria dikonversi ke skala 1–5 sebelum dinormalisasi. Ambang di bawah ini
menggantikan angka lama yang berasal dari asumsi proposal.

| Skor | C1 Stok (unit) | C2 Permintaan (unit/bln) | C3 Sisa ED (hari) | C4 Harga beli (Rp) |
|:---:|---|---|---|---|
| 1 | ≤ 20 | ≤ 10 | ≤ 90 | ≤ 2.000 |
| 2 | 21 – 40 | 11 – 30 | 91 – 180 | 2.001 – 10.000 |
| 3 | 41 – 70 | 31 – 65 | 181 – 365 | 10.001 – 50.000 |
| 4 | 71 – 100 | 66 – 100 | 366 – 730 | 50.001 – 100.000 |
| 5 | ≥ 101 | ≥ 101 | ≥ 731 | > 100.000 |

Arah skor mengikuti keputusan normalisasi baku (Opsi Y): skor disusun searah nilai mentah, dan
prioritas dibentuk lewat rumus normalisasi — `min/X` untuk kriteria *cost* (C1, C3, C4) dan `X/max`
untuk kriteria *benefit* (C2).

### Dasar tiap ambang

**C1 — Stok.** Narasumber menyebut batas waspada **20 unit**. Angka itu dipakai sebagai batas
skor 1 (paling mendesak), lalu dinaikkan bertingkat.

**C2 — Permintaan.** Narasumber memberi tiga tingkat untuk satuan kapsul: ramai **≥ 101/bulan**,
sedang **31–100**, sepi **≤ 30**. Ketiga batas itu dipertahankan apa adanya; rentang sedang dan
sepi hanya dibelah agar cukup untuk lima tingkat skor. Perlu dicatat bahwa jawaban awal "ribuan
transaksi per bulan" merujuk total transaksi seluruh apotek, bukan per jenis obat — per jenis
berada di kisaran ratusan.

**C3 — Sisa kedaluwarsa.** Batas **90 hari** adalah tenggat retur ke PBF. Lewat dari itu obat tidak
lagi dapat dikembalikan, sehingga seluruh risiko kerugian berpindah ke apotek. Inilah alasan
90 hari dipakai sebagai batas skor paling mendesak, bukan sekadar angka bulat.

**C4 — Harga beli.** Narasumber menyebut murah **≤ Rp2.000** dan mahal **> Rp100.000** per strip.
Kedua ujung itu dipakai sebagai batas skor 1 dan skor 5.

### Keterbatasan yang harus ditulis di Bab 1.4

Jawaban narasumber untuk C2 diawali "tergantung satuannya", dan itu tepat. Sistem hanya menyimpan
satu set `scale_rules` per kriteria yang berlaku untuk seluruh obat, sehingga ambang bersatuan
kapsul ikut diterapkan pada sirup dan botol. Akibatnya obat bersatuan botol yang terjual 20 botol
per bulan akan dinilai sepi padahal sebenarnya laris.

Perbaikannya adalah skala konversi per satuan obat, dan itu di luar cakupan penelitian ini. Cukup
diakui sebagai batasan pada Bab 1.4 dan diusulkan pada saran Bab 5.2.

### Yang perlu diubah

- `database/seeders/SawCriteriaSeeder.php` — field `scale_rules` untuk keempat kriteria.
- Tabel 3.5–3.8 pada naskah.
- Jalankan ulang `php artisan sipokat:recalculate-saw` setelah seeder diperbarui.

Bobot pada seeder **tidak perlu disentuh**.

---

## 4. Temuan Lain yang Mengubah Naskah

### 4.1 Pembelian ke PBF — perbandingan harga per transaksi

Obat yang sama tersedia di beberapa PBF dengan harga berbeda, dan katalog tiap PBF tidak identik.
Alur pemesanannya: apotek lebih dulu melihat **PBF mana yang menyediakan** obat tersebut saat itu,
lalu **membandingkan harga antar-PBF yang tersedia**, dan membeli dari yang termurah. Harga adalah
satu-satunya pertimbangan.

Pemilihan ini diulang setiap kali memesan, bukan langganan tetap. Contoh dari narasumber: obat A
sebelumnya termurah di PBF B; saat dipesan lagi PBF B sedang kosong, maka apoteker membandingkan
PBF C dan PBF D, dan memilih PBF C.

Tiga konsekuensinya:

1. **Struktur data yang ada sudah benar.** Karena PBF tidak melekat permanen pada satu obat,
   keputusan melepas `supplier_id` dari tabel `medicines` dan menempatkan PBF di tingkat PO/RO
   sudah sesuai praktik lapangan.
2. **Definisi operasional C4 perlu ditulis eksplisit.** Harga beli bukan atribut tetap milik obat,
   melainkan hasil perbandingan saat pemesanan. Di Bab III, C4 sebaiknya didefinisikan sebagai
   *harga beli acuan, yaitu harga dari PBF termurah pada penerimaan terakhir* — nilai yang
   diperbarui setiap kali penerimaan dicatat.
3. **Obat yang sering kosong bukan karena terkunci di satu PBF.** Obat yang hanya tersedia di satu
   PBF jumlahnya sedikit, dan kelima obat yang paling sering kosong justru tersedia di PBF lain.
   Apotek juga tidak menunggu PBF termurah kembali tersedia, melainkan langsung membandingkan PBF
   lain yang punya stok. Karena itu kekosongan yang terjadi berasal dari ketiadaan pasokan di
   tingkat distributor, di luar kendali apotek — dan **tidak boleh** dijelaskan sebagai akibat
   eksklusivitas PBF maupun keengganan membeli lebih mahal.

### 4.2 Batch bercampur memang terjadi

Saat konsumsi sedang tinggi, obat dipesan sebelum stok lama habis sehingga satu obat dapat memiliki
dua tanggal kedaluwarsa atau lebih di rak. Pengambilan barang memakai FEFO di gudang dan FIFO di
apotek.

Ini mengesahkan asumsi FEFO pada perhitungan C3, sekaligus menegaskan bahwa keterbatasan
`MedicineStock` yang belum melacak stok per batch adalah batasan nyata — layak ditulis di Bab 1.4
dan diusulkan sebagai pengembangan di Bab 5.2.

### 4.3 Penanganan obat kedaluwarsa — cukup lewat Stok Opname

Praktik apotek menempuh tiga jalur: penyesuaian stok opname, retur ke PBF, dan pemusnahan.
Keputusan: **tidak membangun modul retur maupun pemusnahan**. Keduanya dicatat sebagai penyesuaian
melalui Stok Opname yang sudah ada. Tulis pembatasan ini di Bab 1.4.

Pemusnahan di lapangan dilakukan dengan melarutkan tablet ke air lalu dibuang ke tanah, atau
menitipkannya ke apotek yang memiliki IPAL. Frekuensinya sekitar dua tahun sekali, mencakup
±1.500 obat dengan kerugian ratusan ribu rupiah.

### 4.4 Notifikasi kedaluwarsa — sudah sesuai, tidak perlu diubah

Terdapat dua angka berbeda yang sama-sama sah dan tidak boleh dicampur:

- **90 hari** — ambang peringatan, diturunkan dari tenggat retur PBF.
- **30 hari** — penanda kritis, sesuai keinginan narasumber "notifikasi muncul kurang dari 1 bulan".

Kalau peringatan baru muncul di 30 hari, kesempatan retur sudah lewat. Perintah
`sipokat:check-stock-and-expiry` sudah menerapkan keduanya: ambang `--expiry-days=90` dengan
penghitungan terpisah untuk batch kritis ≤ 30 hari. Tidak ada perubahan kode yang diperlukan.

### 4.5 Laporan — perlu tambahan export PDF

Laporan yang diminta: rekap penjualan, rekap pembelian, fast moving, dan slow moving. Format yang
diinginkan: tampil di layar, export Excel, **dan export PDF**. Frekuensi: sesuai kebutuhan dan
tahunan — keduanya terlayani oleh filter rentang tanggal bebas.

Seluruh jenis laporan sudah tersedia. Yang ditambahkan pada iterasi ini adalah **export PDF** pada
kedua halaman laporan.

---

## 5. Data Kuantitatif untuk Bab I

Angka-angka berikut berasal langsung dari narasumber dan mengisi bagian latar belakang yang
sebelumnya masih berupa pernyataan umum.

| Temuan | Angka |
|---|---|
| Selisih stok fisik vs catatan | 8–10%, berulang, baru ketahuan saat stok opname |
| Penyebab selisih | lupa mencatat dan salah tulis, terutama salah dosis |
| Dampak selisih | kerugian, yang akhirnya diputihkan lewat opname |
| Dampak stock out | pelanggan pindah ke apotek lain sehingga penjualan hilang |
| Lead time pemulihan stock out | hampir 1 bulan |
| Waktu menentukan prioritas restock | ± 1 jam per periode, karena harus menelusuri penjualan dulu |
| Pemusnahan obat kedaluwarsa | ± 1.500 obat per 2 tahun, kerugian ratusan ribu rupiah |

**Contoh kekeliruan prioritas** — bahan terbaik untuk paragraf pembuka latar belakang: Vitamin B
Complex sedang sangat dibutuhkan, tetapi yang terbeli justru Vitamin D yang stoknya masih banyak.
Kekeliruan serupa juga sering terjadi antar-dosis pada obat yang sama.

Contoh ini menunjukkan persis kegagalan yang hendak diperbaiki SAW: keputusan yang bertumpu pada
satu faktor tanpa membandingkan stok dan tingkat konsumsi antar-obat secara bersamaan. Sejalan
dengan itu, kekeliruan antar-dosis muncul dua kali secara terpisah dalam wawancara — sebagai
penyebab selisih catatan dan sebagai contoh salah beli — sehingga keberadaan field dosis pada data
obat punya pembenaran lapangan yang konkret.

---

## 6. Sisa yang Belum Tersedia

| Item | Status |
|---|---|
| Nama & jabatan narasumber | akan ditanyakan kembali |
| Hak akses tiga peran (Tabel 3.2) | dilewati — ditangani sendiri di luar cakupan ini |
| Foto kartu stok gudang | sedang diminta ke apotek |

Kartu stok adalah dokumen terpenting yang tersisa. Dari enam dokumen yang diminta, baru data
penerimaan PBF beserta tanggal ED, nomor batch, dan faktur yang tersedia. Data itu cukup untuk
membentuk daftar obat, harga beli (C4), tanggal kedaluwarsa (C3), dan daftar PBF — tetapi belum
mencakup stok berjalan (C1) dan data penjualan (C2). Kartu stok menutup keduanya sekaligus, karena
memuat stok berjalan sekaligus riwayat keluar-masuk. Tanpa itu, Bab IV belum dapat menampilkan
perhitungan SAW di atas data nyata.
