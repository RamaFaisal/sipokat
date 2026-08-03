# Draft Isi TA — Bab IV & V

> File isi Bab IV dan V. Pendamping: `draft_isi_ta.md` (Bab I–III), `instrumen_pengumpulan_data.md`, `CLAUDE.md` (status sistem).
> Bagian bertanda `【...】` diisi dari data nyata / screenshot sistem. Jangan dikarang.
> Nomor Gambar dan Tabel bersifat sementara, sesuaikan urutannya saat gambar final disisipkan.
> Catatan kerja lengkap (dependensi, urutan pengerjaan) ada di bagian bawah file.

---

# BAB IV — HASIL DAN PEMBAHASAN

Bab ini memaparkan hasil pembangunan sistem inventory obat berbasis web yang terintegrasi dengan Sistem Pendukung Keputusan metode SAW, hasil perhitungan SAW dengan data aktual Apotek Anugrah Husada, hasil pengujian sistem, serta pembahasan yang menjawab rumusan masalah penelitian.

## 4.1 Implementasi Sistem

Implementasi sistem dilakukan pada tahap *construction* menggunakan bahasa pemrograman PHP dengan framework Laravel 12, kerangka kerja panel administrasi Filament 4, basis data MySQL, dan Tailwind CSS 4. Berikut disajikan hasil implementasi tiap modul beserta tampilan antarmukanya, yang dipetakan pada kebutuhan fungsional F-01 hingga F-07.

### 4.1.1 Login dan Autentikasi (F-01)

Modul login digunakan pengguna untuk masuk ke sistem sesuai hak aksesnya. Tampilan halaman login ditunjukkan pada Gambar 4.1.

【Sisipkan gambar di sini】

**Gambar 4. 1. Halaman Login**

Halaman login menampilkan formulir autentikasi berupa isian email dan kata sandi. Pengguna memasukkan kredensial, kemudian sistem memvalidasinya terhadap data pengguna. Apabila valid, pengguna diarahkan ke dashboard sesuai hak aksesnya, sedangkan bila tidak valid sistem menampilkan pesan kesalahan. Sesi pengguna dikelola oleh sistem dan dapat diakhiri melalui menu logout pada bagian header panel.

### 4.1.2 Pengelolaan Data Master (F-02)

Modul ini digunakan untuk mengelola data obat serta data pendukung seperti supplier, kategori, rak, dan satuan. Tampilan pengelolaan data obat ditunjukkan pada Gambar 4.2.

【Sisipkan gambar di sini】

**Gambar 4. 2. Halaman Pengelolaan Data Obat**

Halaman pengelolaan data obat menyediakan fungsi tambah, ubah, hapus, dan lihat data obat. Setiap obat memiliki kode yang dibuat otomatis oleh sistem serta atribut seperti nama, kategori, satuan, rak penyimpanan, harga beli, harga jual, dan batas stok minimum. Selain data obat, modul ini juga mengelola data pendukung berupa supplier, kategori, rak, dan satuan. Sistem menyediakan fitur impor data obat melalui berkas Excel untuk mempercepat pemasukan data dalam jumlah banyak.

### 4.1.3 Pencatatan Obat Masuk (F-03)

Modul pencatatan obat masuk digunakan untuk mencatat penerimaan obat dari pemasok beserta nomor batch dan tanggal kedaluwarsa. Tampilan modul ditunjukkan pada Gambar 4.3.

【Sisipkan gambar di sini】

**Gambar 4. 3. Halaman Pencatatan Obat Masuk**

Pencatatan obat masuk dilakukan melalui dua tahap, yaitu pembuatan pesanan pembelian (Purchase Order) kepada supplier dan pencatatan penerimaan barang (Receive Order). Pada setiap item penerimaan, petugas memasukkan jumlah, nomor batch, tanggal produksi, dan tanggal kedaluwarsa. Nomor transaksi dibuat otomatis oleh sistem dan penerimaan dapat dilakukan secara bertahap dari satu pesanan. Setelah penerimaan disimpan, sistem menambah stok obat secara otomatis dan mencatat masa kedaluwarsa per batch sebagai dasar pemantauan.

### 4.1.4 Pencatatan Obat Keluar (F-03)

Modul pencatatan obat keluar digunakan untuk mencatat penjualan obat sekaligus mengurangi stok secara otomatis dengan validasi ketersediaan. Tampilan modul ditunjukkan pada Gambar 4.4.

【Sisipkan gambar di sini】

**Gambar 4. 4. Halaman Pencatatan Obat Keluar**

Pencatatan obat keluar digunakan untuk mencatat penjualan obat kepada pelanggan. Saat menambahkan item, sistem memeriksa ketersediaan stok. Apabila jumlah yang dimasukkan melebihi stok yang tersedia, sistem menolak dan menampilkan pesan bahwa stok tidak mencukupi, sedangkan bila mencukupi transaksi disimpan dan stok obat berkurang secara otomatis. Nomor transaksi dibuat otomatis dan setiap perubahan transaksi diproses secara transaksional agar data stok tetap konsisten.

### 4.1.5 Notifikasi Stok Minimum dan Kedaluwarsa (F-04)

Modul notifikasi memberikan peringatan dini terhadap obat yang stoknya menipis maupun mendekati masa kedaluwarsa. Tampilan notifikasi ditunjukkan pada Gambar 4.5.

【Sisipkan gambar di sini】

**Gambar 4. 5. Notifikasi Stok Minimum dan Kedaluwarsa**

Modul notifikasi berjalan melalui pemeriksaan terjadwal harian yang memindai kondisi stok dan masa kedaluwarsa obat. Obat yang stoknya mencapai batas minimum serta batch yang mendekati kedaluwarsa, yaitu kurang dari atau sama dengan 90 hari, akan memicu notifikasi. Notifikasi ditampilkan pada ikon lonceng di bagian header panel sehingga pengguna memperoleh peringatan dini untuk melakukan restock maupun penanganan obat yang mendekati kedaluwarsa.

### 4.1.6 Laporan Inventory (F-05)

Modul laporan menyajikan rekap data inventory secara otomatis. Tampilan laporan ditunjukkan pada Gambar 4.6.

【Sisipkan gambar di sini】

**Gambar 4. 6. Halaman Laporan Inventory**

Modul laporan menyajikan beberapa jenis laporan inventory secara otomatis. Laporan yang tersedia meliputi kartu stok per obat, rekap penjualan dan pembelian dalam periode tertentu, serta analisis obat cepat laku (fast moving), lambat laku (slow moving), dan tidak bergerak (dead stock). Setiap laporan dilengkapi ringkasan dan dapat diekspor ke berkas Excel untuk keperluan dokumentasi maupun pelaporan kepada pemilik apotek.

### 4.1.7 Pengelolaan Kriteria dan Bobot SAW (F-06)

Modul ini digunakan admin untuk mengatur kriteria, bobot, dan skala penilaian SAW. Tampilan modul ditunjukkan pada Gambar 4.7.

【Sisipkan gambar di sini】

**Gambar 4. 7. Halaman Pengelolaan Kriteria dan Bobot SAW**

Halaman pengelolaan kriteria dan bobot SAW digunakan admin untuk mengatur empat kriteria penilaian beserta jenisnya (cost atau benefit), bobot, dan skala konversi nilainya. Sistem melakukan validasi agar total bobot seluruh kriteria yang aktif sama dengan 1 sehingga perhitungan SAW tetap sahih. Kriteria dapat diaktifkan atau dinonaktifkan, dan skala konversi 1 sampai 5 dapat disesuaikan dengan kebijakan apotek tanpa perlu mengubah kode program.

### 4.1.8 Perhitungan Prioritas Restock SAW (F-06 dan F-07)

Modul perhitungan menjalankan proses SAW dan menampilkan hasil perangkingan prioritas restock. Tampilan modul ditunjukkan pada Gambar 4.8.

【Sisipkan gambar di sini】

**Gambar 4. 8. Halaman Perhitungan dan Hasil Perangkingan SAW**

Halaman perhitungan prioritas restock digunakan untuk menjalankan proses SAW. Pengguna menentukan periode perhitungan kemudian menekan tombol hitung, dan sebelum diproses sistem memeriksa bahwa total bobot kriteria sama dengan 1. Sistem selanjutnya memproses seluruh obat dan menampilkan hasil perangkingan yang diurutkan berdasarkan nilai preferensi (Vi) dari tertinggi ke terendah. Setiap baris dilengkapi fitur detail perhitungan yang menampilkan langkah konversi, normalisasi, dan penjumlahan terbobot per obat, serta terdapat riwayat perhitungan untuk keperluan audit.

### 4.1.9 Dashboard

Dashboard menyajikan ringkasan informasi penting dalam bentuk widget. Tampilan dashboard ditunjukkan pada Gambar 4.9.

【Sisipkan gambar di sini】

**Gambar 4. 9. Halaman Dashboard**

Dashboard menampilkan ringkasan informasi penting dalam bentuk widget. Widget yang tersedia meliputi sepuluh obat prioritas restock teratas berdasarkan hasil SAW, daftar obat dengan stok menipis, daftar batch obat yang mendekati kedaluwarsa, daftar pesanan pembelian yang belum selesai, serta ringkasan penjualan dalam periode terakhir. Dashboard memberikan gambaran cepat mengenai kondisi inventory sebagai dasar pengambilan keputusan.

## 4.2 Hasil Perhitungan SAW dengan Data Aktual

Subbab ini menyajikan hasil perhitungan SAW menggunakan data aktual obat di Apotek Anugrah Husada. Berbeda dengan contoh ilustrasi pada subbab 3.4.4 yang bertujuan mendemonstrasikan langkah metode, perhitungan berikut menggunakan data nyata sehingga hasil perangkingannya dapat digunakan sebagai rekomendasi restock yang sesungguhnya. Metode, kriteria, dan bobot yang digunakan tetap sama dengan yang ditetapkan pada Bab III.

### 4.2.1 Data Obat Aktual

Data nilai awal obat diperoleh melalui studi dokumentasi di Apotek Anugrah Husada, meliputi jumlah stok, tingkat permintaan per bulan, sisa masa kedaluwarsa, dan harga beli. Sebagian data disajikan sebagai contoh pada Tabel 4.1, sedangkan seluruh data diproses oleh sistem.

**Tabel 4.1. Data Nilai Awal Obat (Data Aktual)**

| Kode | Nama Obat | Stok | Permintaan/Bulan | Sisa ED (Hari) | Harga Beli (Rp) |
|---|---|:---:|:---:|:---:|:---:|
| 【A1】 | 【nama obat】 | 【…】 | 【…】 | 【…】 | 【…】 |
| 【…】 | 【…】 | 【…】 | 【…】 | 【…】 | 【…】 |

### 4.2.2 Hasil Perangkingan oleh Sistem

Setelah data dimasukkan, sistem memproses seluruh obat secara otomatis melalui tahapan konversi, normalisasi, dan perhitungan nilai preferensi, kemudian mengurutkannya. Hasil perangkingan prioritas restock (menampilkan sepuluh obat prioritas teratas) disajikan pada Tabel 4.2.

**Tabel 4.2. Hasil Perangkingan Prioritas Restock oleh Sistem (Data Aktual)**

| Peringkat | Kode | Nama Obat | Stok (C1) | Permintaan (C2) | Sisa ED (C3) | Harga (C4) | Nilai Vi |
|:---:|:---:|---|:---:|:---:|:---:|:---:|:---:|
| 1 | 【…】 | 【…】 | 【…】 | 【…】 | 【…】 | 【…】 | 【…】 |
| 2 | 【…】 | 【…】 | 【…】 | 【…】 | 【…】 | 【…】 | 【…】 |
| 【…】 | 【…】 | 【…】 | 【…】 | 【…】 | 【…】 | 【…】 | 【…】 |

【Penjelasan singkat hasil: obat pada peringkat teratas menjadi prioritas restock utama, disertai alasan berdasarkan kombinasi kriterianya (mis. stok rendah dengan permintaan tinggi).】

### 4.2.3 Verifikasi Perhitungan Sistem

Untuk memastikan sistem menerapkan metode SAW dengan benar, dilakukan verifikasi dengan menghitung ulang nilai preferensi salah satu obat secara manual, kemudian membandingkannya dengan keluaran sistem. 【Ambil satu obat (mis. peringkat pertama), tampilkan langkah konversi nilai ke skala 1 sampai 5, normalisasi cost/benefit, dan perhitungan Vi = ΣWj × Rij, lalu bandingkan hasilnya dengan nilai Vi yang ditampilkan sistem.】 Kesesuaian antara hasil perhitungan manual dan keluaran sistem menunjukkan bahwa implementasi metode SAW pada sistem telah sesuai dengan rumusan pada Bab III.

## 4.3 Hasil Pengujian Sistem

Pengujian dilakukan dengan metode *blackbox testing* terhadap skenario yang telah direncanakan pada Tabel 3.17. Hasil pelaksanaan pengujian disajikan pada Tabel 4.5.

**Tabel 4.5. Hasil Pengujian Sistem (Blackbox Testing)**

| No | Fitur | Skenario Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status |
|---|---|---|---|---|:---:|
| 1 | Login | Memasukkan kredensial benar dan salah | Masuk saat benar, pesan gagal saat salah | 【…】 | 【Berhasil】 |
| 2 | Kelola Data Obat | Tambah, ubah, hapus, lihat data obat | Data tersimpan dan tampil sesuai | 【…】 | 【Berhasil】 |
| 3 | Obat Masuk | Catat penerimaan beserta batch dan kedaluwarsa | Stok bertambah, kedaluwarsa tercatat | 【…】 | 【Berhasil】 |
| 4 | Obat Keluar | Penjualan melebihi stok dan dalam batas stok | Ditolak saat melebihi, tersimpan saat cukup | 【…】 | 【Berhasil】 |
| 5 | Notifikasi | Obat stok minimum atau mendekati kedaluwarsa | Notifikasi peringatan muncul | 【…】 | 【Berhasil】 |
| 6 | Perhitungan SAW | Total bobot sama dengan 1 dan tidak sama dengan 1 | Berjalan saat valid, ditolak saat tidak valid | 【…】 | 【Berhasil】 |
| 7 | Hasil Perangkingan | Menampilkan hasil perhitungan SAW | Obat terurut berdasarkan nilai preferensi | 【…】 | 【Berhasil】 |

【Penjelasan singkat: kesimpulan pengujian, seluruh fungsi berjalan sesuai kebutuhan.】

## 4.4 Pembahasan

Berdasarkan hasil implementasi, perhitungan SAW, dan pengujian, berikut pembahasan yang menjawab rumusan masalah penelitian.

**1. Penerapan metode SAW untuk prioritas restock.** 【Bahas bagaimana sistem menerapkan SAW menghasilkan perangkingan prioritas restock secara objektif, dengan bukti dari subbab 4.1.8 dan 4.2.】

**2. Perancangan sistem inventory sebagai fondasi.** 【Bahas bagaimana sistem mengelola data obat, stok, obat masuk, dan obat keluar secara terintegrasi sebagai sumber data SAW, dengan bukti dari subbab 4.1.2 hingga 4.1.4.】

**3. Pemantauan stok dan kedaluwarsa otomatis.** 【Bahas bagaimana sistem memantau stok menipis dan kedaluwarsa serta memberi notifikasi, dengan bukti dari subbab 4.1.5.】

**Keterbatasan sistem.** Sebagai catatan, terdapat beberapa keterbatasan yang diketahui, yaitu konversi skala kriteria masih menggunakan rentang diskrit 1 sampai 5, pemantauan kedaluwarsa menggunakan pendekatan FEFO tanpa pencatatan stok per batch secara penuh, serta pemisahan hak akses per peran yang masih berupa rancangan dan belum diaktifkan. Keterbatasan ini menjadi dasar bagi saran pengembangan pada Bab V.

---

# BAB V — PENUTUP

## 5.1 Kesimpulan

Berdasarkan hasil penelitian, dapat disimpulkan sebagai berikut.

1. Metode *Simple Additive Weighting* (SAW) berhasil diterapkan dalam sistem pendukung keputusan untuk menentukan prioritas restock obat berdasarkan kriteria jumlah stok, tingkat permintaan, masa kedaluwarsa, dan harga beli, sehingga menghasilkan urutan prioritas restock yang objektif dan terukur. 【Rujuk bukti Bab IV.】
2. Sistem inventory obat berbasis web berhasil dibangun untuk mengelola data obat, stok, obat masuk, dan obat keluar secara terintegrasi dan akurat, sekaligus menjadi fondasi data bagi perhitungan metode SAW. 【Rujuk bukti Bab IV.】
3. Sistem mampu memantau stok menipis dan masa kedaluwarsa secara otomatis melalui notifikasi, sehingga membantu mengurangi risiko kehabisan stok dan obat kedaluwarsa. 【Rujuk bukti Bab IV.】

## 5.2 Saran

1. Bagi Apotek Anugrah Husada, disarankan mengaktifkan pemisahan hak akses per peran (Admin, Petugas, Pemilik) yang infrastrukturnya telah tersedia pada sistem.
2. Bagi pengembangan sistem selanjutnya, disarankan menambahkan pencatatan stok per batch agar pemantauan kedaluwarsa lebih akurat, memperhalus skala penilaian kriteria, serta mempertimbangkan integrasi dengan resep elektronik atau *point of sale* yang saat ini berada di luar batasan masalah.
3. Bagi peneliti lain, disarankan melakukan penelitian lanjutan, misalnya membandingkan metode SAW dengan metode lain seperti WP atau TOPSIS menggunakan data apotek yang sama untuk mengukur konsistensi hasil perangkingan.

---

# Catatan Kerja (bukan bagian naskah)

## Janji dari Bab III yang harus ditepati
- 3.4.4: "perhitungan dengan data aktual Apotek Anugrah Husada beserta keluaran sistem disajikan pada Bab IV" → dipenuhi di 4.2.
- 3.5: "Hasil implementasi sistem secara lengkap beserta tampilan antarmukanya disajikan pada Bab IV" → dipenuhi di 4.1.
- 3.6: "Hasil pelaksanaan pengujian secara lengkap disajikan pada Bab IV" → dipenuhi di 4.3.

## Dependensi / Blocker

| # | Kebutuhan | Status | Blocker |
|---|-----------|:---:|---------|
| 1 | Data obat riil Apotek Anugrah Husada (untuk 4.2) | ❌ belum ada | Data lapangan belum dikumpulkan (lihat [[ta-proposal-sipokat]]). Bisa pakai `SpkTestDataSeeder` sebagai placeholder sementara bila deadline mepet, tapi harus diganti data asli sebelum sidang final |
| 2 | Screenshot tiap modul (4.1) | ❌ belum diambil | Perlu sistem jalan (`composer run dev`), masuk sebagai admin, jalani skenario per modul |
| 3 | Hasil eksekusi nyata blackbox testing (4.3) | ❌ belum dijalankan | Ikuti skenario Tabel 3.17, catat hasil aktual (bukan "diharapkan") |
| 4 | Objek penelitian Bab 2.3 dan placeholder Bab I / 3.2 | ❌ belum diisi | Sama seperti #1, tergantung wawancara/observasi ke apotek |

**Urutan pengerjaan disarankan:** selesaikan #4 dan #1 dulu (satu kunjungan apotek bisa sekaligus) → ambil screenshot (#2) → jalankan pengujian (#3) → tulis Bab IV & V penuh dari kerangka di atas.

## Format penulisan (ikuti `draft_isi_ta.md`)
- Ejaan "Anugrah Husada", istilah "inventory" bukan "inventaris".
- Placeholder ditulis `【...】`, jangan dikarang.
- Screenshot diberi nomor "Gambar 4.x" berurutan langsung.
- Pembahasan (4.4) menjawab rumusan masalah dengan bukti Bab IV; Bab V hanya menyimpulkan, tidak memunculkan klaim baru.
