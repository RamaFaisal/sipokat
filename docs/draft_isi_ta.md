# Draft Isi Tugas Akhir

> Dokumen isi TA versi kerja — disusun bertahap dari judul hingga Bab V, menyesuaikan sistem Sipokat yang sudah dibangun.
> Pendamping: `perbaikan_skripsi.md` (checklist revisi) dan `instrumen_pengumpulan_data.md` (alat ambil data lapangan).
> Terakhir diperbarui: 2026-07-17.

---

## JUDUL (FINAL — terdaftar di prodi, tidak diubah)

**RANCANG BANGUN SISTEM INVENTORY OBAT BERBASIS WEB DENGAN SISTEM PENDUKUNG KEPUTUSAN METODE *SIMPLE ADDITIVE WEIGHTING* (SAW) PADA APOTEK ANUGRAH HUSADA**

**Ketentuan penulisan yang mengikat seluruh naskah:**
- Ejaan nama apotek konsisten: **"Anugrah Husada"** (perbaiki semua "Anugerah" → "Anugrah").
- Istilah konsisten: **"inventory"** (bukan "inventaris") di seluruh dokumen.
- Framing: **SAW sebagai ide utama**, sistem inventory sebagai fondasi/platform data.

---

## STATUS PENYUSUNAN

| Bagian | Status |
|--------|:------:|
| Judul | ✅ Final |
| Bab I — 1.1 Latar Belakang | 🟡 Draft (menunggu data lapangan) |
| Bab I — 1.2 Rumusan Masalah | ✅ Draft |
| Bab I — 1.3 Batasan Masalah | ✅ Draft |
| Bab I — 1.4 Tujuan Penelitian | ✅ Draft |
| Bab I — 1.5 Manfaat Penelitian | ✅ Draft |
| Bab II — Tinjauan Pustaka | 🟡 Draft (teori inti + Filament; 2.1/WP/TOPSIS pakai proposal) |
| Bab III — 3.1 Tahapan Penelitian | ✅ Draft (Prototyping) |
| Bab III — 3.2 Tahapan Awal | 🟡 Draft (menunggu data lapangan 3.2.2–3.2.4) |
| Bab III — 3.3 Perencanaan | ✅ Draft |
| Bab III — 3.4.1 Perancangan Sistem | ✅ Draft (UML + ERD, gambar via UML-plan.md) |
| Bab III — 3.4.2 Penentuan Kriteria | ✅ Draft (tabel skala arah natural) |
| Bab III — 3.4.3 Perumusan Model SAW | ✅ Draft (langkah 1–7) |
| Bab III — 3.4.4 Perhitungan & Prioritas | ✅ Draft (contoh ilustrasi 5 obat; data nyata → Bab IV) |
| Bab III — 3.5 Implementasi Sistem | ✅ Draft |
| Bab III — 3.6 Pengujian Sistem | ✅ Draft (blackbox, Tabel 3.17) |
| Bab IV — Hasil & Pembahasan | ⏳ (tulis dari nol, berbasis sistem aktual) |
| Bab V — Penutup | ⏳ |

---

<!-- Konten bab akan ditambahkan di bawah ini secara bertahap. -->

# BAB I — PENDAHULUAN

## 1.1 Latar Belakang

> **Catatan penyusunan:** bagian bertanda `【ISI DARI WAWANCARA/OBSERVASI: ...】` wajib diisi dengan temuan nyata dari Apotek Anugrah Husada (lihat `instrumen_pengumpulan_data.md`). Jangan dikarang — kalimat di sekitarnya sudah dirancang agar tetap utuh setelah angka dimasukkan.

Apotek memegang peran penting dalam rantai distribusi obat sehingga membutuhkan pengelolaan inventory yang akurat, termasuk pemantauan masa kedaluwarsa untuk mencegah kerugian finansial sekaligus menjamin keamanan obat yang diterima masyarakat (Bangun et al., 2023). Namun pada banyak apotek pengelolaan masih dilakukan secara manual sehingga **otomatisasi dan pengolahan data belum berjalan optimal**. Kondisi ini juga terjadi di Apotek Anugrah Husada, Demak. Berdasarkan observasi dan wawancara awal yang dilakukan peneliti, apotek yang mengelola sekitar 【ISI DARI WAWANCARA: jumlah jenis obat】 jenis obat ini masih mencatat transaksi dan stok melalui buku catatan dan Microsoft Excel, dengan pencatatan obat masuk dan keluar dilakukan 【ISI DARI OBSERVASI: cara/pelaku pencatatan】, sehingga tidak efisien dan rentan kesalahan manusia (*human error*) yang mempersulit pengambilan keputusan pengadaan (Maulana et al., 2025).

Pengelolaan manual tersebut menimbulkan sejumlah dampak nyata. Data stok yang tercatat sering tidak sesuai dengan jumlah fisik di gudang, kondisi kehabisan stok (*stock out*) baru diketahui saat obat habis, dan sebagian obat terlanjur kedaluwarsa sehingga harus dimusnahkan dan menimbukan kerugian (Syam et al., 2025). Lebih mendasar lagi, **proses penentuan prioritas obat yang harus dipesan ulang (*restock*) belum berjalan otomatis** dan masih bergantung pada perkiraan pengelola tanpa analisis terstruktur. Karena itu diperlukan sistem inventory berbasis web yang mengelola data secara terpusat sekaligus memberi notifikasi stok menipis dan obat mendekati kedaluwarsa (Hastuti et al., 2025). sistem ini sekaligus menjadi fondasi, karena data stok, penjualan, harga beli, dan masa kedaluwarsa di dalamnya merupakan sumber data bagi pengambilan keputusan restock.

Penentuan prioritas restock tersebut melibatkan beberapa faktor yang saling bertentangan, yaitu jumlah stok, tingkat permintaan, sisa masa kedaluwarsa, dan harga beli, sehingga tergolong pengambilan keputusan multi-kriteria (*Multi Attribute Decision Making*) yang menuntut Sistem Pendukung Keputusan (SPK). Terdapat beberapa pendekatan untuk menyelesaikannya: *machine learning* dapat memprediksi permintaan tetapi memerlukan data historis dalam jumlah besar dan kurang transparan bagi pengguna awam, sedangkan metode SPK/MADM seperti *Simple Additive Weighting* (SAW), *Weighted Product* (WP), dan *Technique for Order Preference by Similarity to Ideal Solution* (TOPSIS) dirancang khusus untuk perankingan berbasis kriteria terbobot. Penelitian terdahulu menunjukkan metode SPK ini efektif, namun umumnya berhenti pada proses perankingan dan belum terintegrasi ke sistem operasional. Pada konteks apotek pun SAW lebih diarahkan untuk pemilihan supplier (Lubis & Rosnelly, 2026), bukan penentuan prioritas restock yang datanya bersumber langsung dari transaksi inventory harian.

Berdasarkan pertimbangan tersebut, penelitian ini memilih metode **SAW** karena perhitungannya sederhana, transparan, dan hasilnya mudah ditelusuri oleh apoteker maupun pengelola persediaan (Pasaribu, 2023). Dibandingkan WP dan TOPSIS, SAW lebih mudah diterapkan dan penelitian terdahulu menunjukkan akurasinya tertinggi, yaitu sebesar 0,940 (Junaidi Muhammad & Ridha Rasyid, 2024). Untuk mengisi kesenjangan yang ada, penelitian ini merancang dan membangun sistem inventory obat berbasis web yang terintegrasi dengan SPK metode SAW pada Apotek Anugrah Husada. Melalui sistem ini, penentuan prioritas restock diharapkan dihasilkan secara objektif dan terukur, ditopang oleh pengelolaan data obat, stok, obat masuk, dan obat keluar yang terintegrasi serta pemantauan stok menipis dan masa kedaluwarsa yang berjalan otomatis.

## 1.2 Rumusan Masalah

Berdasarkan latar belakang yang telah diuraikan, rumusan masalah dalam penelitian ini adalah sebagai berikut.

1. Bagaimana menerapkan metode *Simple Additive Weighting* (SAW) dalam sistem pendukung keputusan untuk menentukan prioritas restock obat berdasarkan kriteria jumlah stok, tingkat permintaan, masa kedaluwarsa, dan harga beli pada Apotek Anugrah Husada?
2. Bagaimana merancang dan membangun sistem inventory obat berbasis web yang mampu mengelola data obat, stok, obat masuk, dan obat keluar secara terintegrasi dan akurat sebagai fondasi data bagi perhitungan metode SAW?
3. Bagaimana sistem dapat memantau stok menipis dan masa kedaluwarsa secara otomatis untuk mengurangi risiko kehabisan stok dan obat kedaluwarsa?

## 1.3 Batasan Masalah

Agar penelitian ini lebih terarah dan tidak melebar, ditetapkan batasan masalah sebagai berikut.

1. Sistem pendukung keputusan menggunakan metode *Simple Additive Weighting* (SAW) dengan empat kriteria, yaitu jumlah stok, tingkat permintaan, masa kedaluwarsa, dan harga beli. Penelitian ini tidak menggunakan metode prediksi kompleks seperti *machine learning*.
2. Sistem inventory mencakup pengelolaan data obat, stok, obat masuk berupa penerimaan dari pemasok, dan obat keluar berupa penjualan atau pengeluaran. Penjualan dicatat hanya untuk pengurangan stok dan sumber data permintaan, tidak mencakup resep elektronik, kasir atau *point of sale*, maupun manajemen keuangan.
3. Pemantauan stok menipis dan masa kedaluwarsa dilakukan berdasarkan data yang dimasukkan ke dalam sistem, tanpa menggunakan sensor fisik maupun perangkat keras tambahan.
4. Objek penelitian terbatas pada Apotek Anugrah Husada di Demak sehingga data dan kebutuhan sistem menyesuaikan kondisi apotek tersebut.
5. Sistem ditujukan untuk penggunaan pada satu apotek dan tidak mencakup pengelolaan multi cabang.

## 1.4 Tujuan Penelitian

Berdasarkan rumusan masalah yang telah ditetapkan, tujuan penelitian ini adalah sebagai berikut.

1. Menerapkan metode *Simple Additive Weighting* (SAW) sebagai sistem pendukung keputusan untuk menentukan prioritas restock obat berdasarkan kriteria jumlah stok, tingkat permintaan, masa kedaluwarsa, dan harga beli secara objektif dan terukur.
2. Merancang dan membangun sistem inventory obat berbasis web yang mampu mengelola data obat, stok, obat masuk, dan obat keluar secara terintegrasi dan akurat sebagai fondasi data bagi perhitungan metode SAW.
3. Mengembangkan fitur pemantauan stok menipis dan masa kedaluwarsa secara otomatis untuk mengurangi risiko kehabisan stok dan obat kedaluwarsa.

## 1.5 Manfaat Penelitian

Penelitian ini diharapkan memberikan beberapa manfaat kepada pihak-pihak berikut.

1. Bagi Apotek
   1. Mempermudah proses pengelolaan stok sehingga tidak lagi bergantung pada pencatatan manual di buku atau Excel.
   2. Mengurangi risiko kesalahan pencatatan, kehilangan data, dan duplikasi informasi.
   3. Membantu apotek mengambil keputusan terkait kebutuhan pembelian barang melalui informasi stok yang akurat dan laporan yang lebih terstruktur.
2. Bagi Pegawai atau Petugas Gudang Apotek
   1. Mempercepat proses pencatatan barang masuk dan barang keluar.
   2. Memberikan sistem yang lebih rapi dan mudah digunakan sehingga meminimalkan kesalahan input.
   3. Mempermudah akses riwayat stok kapan pun dibutuhkan tanpa harus mencari catatan lama secara manual.
3. Bagi Pemilik atau Manajer Apotek
   1. Mendukung pengambilan keputusan pembelian barang berdasarkan data yang lebih akurat dan terkini.
   2. Memberikan gambaran kebutuhan dan ketersediaan stok melalui laporan otomatis, seperti laporan obat cepat habis dan lambat habis.
   3. Mengurangi risiko kekosongan obat maupun penumpukan barang yang tidak laku.
4. Bagi Peneliti
   1. Menjadi referensi dan contoh penerapan sistem informasi inventory yang dapat dikembangkan lebih lanjut.
   2. Menambah wawasan mengenai implementasi sistem inventory berbasis web dan dasar pengambilan keputusan pada lingkungan apotek.
5. Bagi Instansi Pendidikan
   1. Menjadi bahan pembelajaran dan contoh implementasi untuk mata kuliah terkait sistem informasi, basis data, dan pengambilan keputusan.
   2. Menambah koleksi penelitian yang dapat dijadikan acuan untuk penelitian serupa di masa depan.

---

# BAB II — TINJAUAN PUSTAKA DAN LANDASAN TEORI

## 2.1 Tinjauan Studi

> **Catatan penyusunan:** gunakan naskah proposal yang sudah ada (enam penelitian terdahulu beserta Tabel 2.1 State of The Art). Tutup sub-bab dengan paragraf research gap di bawah ini (menggantikan/menambah paragraf penutup proposal), lalu lanjut ke 2.2.

Berdasarkan tinjauan terhadap beberapa penelitian terdahulu, metode Simple Additive Weighting (SAW) terbukti efektif digunakan dalam berbagai bidang karena prosedurnya yang transparan dan mampu menghasilkan perankingan alternatif secara objektif. Namun, mayoritas studi tersebut masih terbatas pada proses perankingan secara statis atau mandiri, belum terintegrasi langsung ke alur operasional pengelolaan inventory obat harian yang pada praktiknya masih mengandalkan Buku Defecta dan spreadsheet terpisah, sehingga rawan memicu *stockout* maupun *overstock* obat kedaluwarsa. Oleh karena itu, penelitian ini mengisi kesenjangan (*research gap*) tersebut dengan merancang Sistem Pendukung Keputusan (SPK) berbasis web yang mengintegrasikan SAW secara otomatis, menarik data stok riil, akumulasi permintaan, dan sisa masa kedaluwarsa langsung dari basis data, untuk menghasilkan prioritas restock yang lebih efektif, terstruktur, dan optimal.

## 2.2 Landasan Teori

### 2.2.1 Sistem Inventory

Sistem merupakan sekumpulan elemen yang saling berhubungan dan bekerja sama untuk mencapai tujuan tertentu, yang secara umum terdiri atas masukan, proses, dan keluaran (Navia Rani, 2022). Dalam konteks apotek, sistem tersebut diwujudkan dalam bentuk sistem inventory. Inventory diartikan sebagai persediaan barang atau bahan yang disimpan untuk memenuhi permintaan konsumen (Adam et al., 2023). Sistem inventory pada apotek digunakan untuk mengatur dan mengawasi stok obat secara fisik maupun administratif, meliputi pencatatan obat masuk berupa pembelian dari pemasok, obat keluar berupa penjualan atau distribusi, serta penetapan batas stok minimum untuk mencegah kehabisan stok. Dengan demikian, sistem inventory berperan penting dalam menjaga ketersediaan obat sekaligus mendukung pengambilan keputusan pengadaan.

### 2.2.2 Apotek dan Pengelolaan Obat

> **Catatan penyusunan:** pertahankan naskah proposal (definisi apotek menurut Permenkes No. 9 Tahun 2017, fungsi apotek, dan prosedur pengelolaan obat termasuk pemantauan masa kedaluwarsa). Tidak ada perubahan.

### 2.2.3 Aplikasi Berbasis Web

> **Catatan penyusunan:** pertahankan naskah proposal (definisi aplikasi berbasis web dan keunggulannya: aksesibilitas tinggi, lintas platform, dan pengelolaan terpusat). Tidak ada perubahan.

### 2.2.4 Teknologi Pendukung

Teknologi pendukung yang digunakan dalam pengembangan sistem ini terdiri atas beberapa komponen berikut.

Hypertext Preprocessor (PHP) adalah bahasa pemrograman sisi server yang digunakan untuk mengolah data, berinteraksi dengan basis data, dan menghasilkan konten website yang dinamis (Arafat et al., 2022). PHP dipilih karena mendukung pengembangan aplikasi web berskala besar dengan biaya implementasi yang relatif rendah serta memiliki dukungan terhadap berbagai framework modern. Pada penelitian ini digunakan PHP versi 8.2.

Laravel merupakan framework web berbasis PHP yang bersifat open source dan menerapkan arsitektur Model View Controller (MVC) untuk meningkatkan efisiensi pengembangan aplikasi web (Yuniarti et al., 2022). Laravel dipilih karena menyediakan struktur kode yang rapi, keamanan bawaan, serta ekosistem pustaka yang lengkap. Sistem ini dibangun menggunakan Laravel versi 12.

Filament adalah framework panel administrasi berbasis Laravel yang digunakan untuk membangun antarmuka pengelolaan data secara cepat dan konsisten. Filament menyediakan komponen siap pakai seperti formulir, tabel, aksi, dan notifikasi sehingga mempercepat pembuatan modul pengelolaan data obat, transaksi, serta perhitungan Sistem Pendukung Keputusan tanpa perlu membangun antarmuka dari awal. Pada penelitian ini digunakan Filament versi 4 sebagai lapisan antarmuka utama sistem.

MySQL adalah sistem manajemen basis data relasional (Relational Database Management System) yang menyimpan data dalam tabel yang saling berhubungan sehingga pengelolaan data menjadi cepat, terstruktur, dan fleksibel (Hartati, 2022). MySQL dipilih karena mampu menangani volume data yang besar dengan kecepatan dan keandalan yang tinggi.

Tailwind CSS adalah framework CSS dengan pendekatan utility first yang menyediakan kumpulan class utilitas untuk membangun tampilan antarmuka secara efisien dan konsisten (Santoso, 2025). Pada penelitian ini digunakan Tailwind CSS versi 4 untuk mendukung tampilan sistem yang responsif.

### 2.2.5 UML (Unified Modeling Language)

> **Catatan penyusunan:** pertahankan naskah proposal (definisi UML sebagai alat perancangan sistem berorientasi objek beserta tabel simbol). Simpan simbol untuk diagram yang benar-benar dipakai di Bab III, yaitu Use Case Diagram (Tabel 2.2) dan Activity Diagram (Tabel 2.4). Class Diagram (Tabel 2.3) dan Sequence Diagram (Tabel 2.5) tidak dipakai di Bab III — boleh dihapus dari Bab II.

### 2.2.6 Sistem Pendukung Keputusan (SPK)

Sistem Pendukung Keputusan atau Decision Support System adalah sistem berbasis komputer yang mengolah data menjadi informasi untuk mendukung penyelesaian masalah pengambilan keputusan secara optimal (Rahayu et al., 2025). SPK banyak digunakan untuk menangani persoalan yang melibatkan banyak kriteria sekaligus, atau dikenal sebagai Multi Attribute Decision Making (MADM). Salah satu metode yang umum digunakan dalam MADM adalah Simple Additive Weighting (SAW) karena prosedur perhitungannya sederhana dan transparan. Dalam penelitian ini, SPK diterapkan sebagai model pengambilan keputusan untuk menentukan prioritas restock obat pada sistem inventory apotek.

### 2.2.7 Simple Additive Weighting (SAW)

Simple Additive Weighting (SAW) adalah metode dalam Sistem Pendukung Keputusan yang digunakan untuk menentukan alternatif terbaik berdasarkan sejumlah kriteria dengan cara menjumlahkan nilai terbobot dari setiap kriteria (Hanif et al., 2025). Metode ini sering digunakan karena proses perhitungannya mudah dan hasilnya mudah dipahami. Dalam metode SAW, kriteria dibagi menjadi dua kategori, yaitu kriteria cost yang nilainya semakin kecil semakin baik, dan kriteria benefit yang nilainya semakin besar semakin baik. Pengelompokan ini menentukan cara normalisasi yang digunakan.

Proses normalisasi bertujuan menyamakan skala nilai antar kriteria agar dapat dibandingkan secara adil. Rumus normalisasi metode SAW adalah sebagai berikut.

Untuk kriteria benefit, `Rij = Xij / (Max Xij)`.

Untuk kriteria cost, `Rij = (Min Xij) / Xij`.

Keterangan. Rij adalah nilai hasil normalisasi alternatif ke-i pada kriteria ke-j. Xij adalah nilai awal alternatif ke-i pada kriteria ke-j. Max Xij adalah nilai maksimum pada kriteria ke-j, dan Min Xij adalah nilai minimum pada kriteria ke-j.

Setelah normalisasi, nilai preferensi setiap alternatif dihitung dengan rumus berikut.

`Vi = Σ (Wj × Rij)`

Keterangan. Vi adalah nilai preferensi alternatif ke-i. Wj adalah bobot kriteria ke-j. Rij adalah nilai normalisasi alternatif ke-i pada kriteria ke-j. Nilai preferensi tertinggi menunjukkan alternatif yang paling diprioritaskan.

Penerapan metode SAW secara rinci pada studi kasus Apotek Anugrah Husada, meliputi langkah perhitungan, penentuan kriteria, pembobotan, hingga contoh perangkingan, dibahas pada Bab III.

### 2.2.8 Weighted Product (WP)

> **Catatan penyusunan:** pertahankan naskah proposal (definisi WP dan rumus `Si = Π xij^wj`). Dipertahankan sebagai dasar perbandingan metode pada 2.2.9.

### 2.2.9 Technique for Order Preference by Similarity to Ideal Solution (TOPSIS)

> **Catatan penyusunan:** pertahankan naskah proposal (definisi TOPSIS dan langkah umumnya). Dipertahankan sebagai dasar perbandingan metode pada 2.2.9.

### 2.2.10 Perbandingan SAW, WP, dan TOPSIS

> **Catatan penyusunan:** pertahankan naskah proposal (Tabel 2.6 Perbandingan Metode beserta kesimpulan bahwa SAW paling sesuai karena sederhana, transparan, dan mudah dipahami). Tidak ada perubahan.

## 2.3 Tinjauan Obyek Penelitian

> **Catatan penyusunan:** bagian bertanda `【...】` diisi dari hasil observasi dan wawancara (lihat `instrumen_pengumpulan_data.md` bagian A). Fokus sub-bab ini adalah mendeskripsikan objek, bukan mengulang permasalahan (sudah di Bab I) maupun solusi (dibahas di Bab III).

Objek penelitian ini adalah Apotek Anugrah Husada, sebuah sarana pelayanan kefarmasian yang berlokasi di 【alamat atau wilayah, Demak】 dan telah beroperasi sejak 【tahun berdiri】. Apotek ini berperan penting dalam menyediakan kebutuhan obat bagi masyarakat sekitar, meliputi 【cakupan layanan, misalnya obat bebas, obat bebas terbatas, obat keras dengan resep, serta alat kesehatan】, dengan jam operasional 【jam buka】. Dalam kegiatan sehari-hari, apotek mengelola sekitar 【jumlah jenis obat】 jenis obat yang berasal dari 【jumlah atau sebutan pemasok, misalnya beberapa pedagang besar farmasi】. Kegiatan operasional apotek dijalankan oleh 【jumlah dan peran pengelola, misalnya seorang apoteker penanggung jawab, petugas apotek, dan pemilik】, yang masing-masing menangani proses penerimaan obat dari pemasok, penyimpanan dan penataan obat pada rak, pelayanan penjualan kepada pelanggan, serta pemantauan ketersediaan stok. Ruang lingkup pengelolaan yang cukup luas inilah yang menuntut pencatatan inventory secara akurat dan terstruktur agar pelayanan tetap berjalan optimal.

Namun, hingga saat penelitian ini dilakukan, pengelolaan inventory di Apotek Anugrah Husada masih dilaksanakan secara manual dengan menggunakan buku catatan dan lembar kerja Microsoft Excel. Buku catatan umumnya digunakan untuk mencatat transaksi harian obat masuk dan obat keluar, sedangkan Microsoft Excel digunakan untuk merekap data stok. Kedua media tersebut belum saling terhubung, sehingga informasi stok, harga, dan masa kedaluwarsa tersimpan secara terpisah dan tidak terintegrasi dalam satu sistem. Pemeriksaan ketersediaan stok maupun masa kedaluwarsa obat masih dilakukan dengan menelusuri catatan secara manual sehingga membutuhkan waktu dan rentan tidak akurat. Kondisi pengelolaan seperti inilah yang menjadi dasar dilakukannya penelitian ini. Adapun uraian permasalahan secara lengkap telah dibahas pada Bab I, sedangkan analisis kebutuhan beserta perancangan solusinya dibahas pada Bab III.

## 2.4 Kerangka Pemikiran

> **Catatan penyusunan:** pertahankan naskah dan Gambar 2.8 dari proposal (alur Masalah → Analisis → Metode SAW → Sistem → Output → Manfaat). Tidak ada perubahan.

---

# BAB III — METODOLOGI PENELITIAN

## 3.1 Tahapan Penelitian

Penelitian ini menggunakan metode pengembangan perangkat lunak *Prototyping*. Metode *Prototyping* dipilih karena berfokus pada pembuatan model kerja awal sistem (*prototype*) secara cepat sehingga kebutuhan pengguna dapat diidentifikasi dan disempurnakan secara bertahap. Tujuan utamanya adalah mengurangi risiko kesalahan pengembangan dan memastikan sistem yang dibangun sesuai dengan kebutuhan pemangku kepentingan (Kustanto et al., 2024). Metode ini sesuai dengan karakteristik penelitian di Apotek Anugrah Husada, karena kebutuhan sistem inventory dan Sistem Pendukung Keputusan perlu diselaraskan langsung dengan proses bisnis apotek melalui komunikasi berkelanjutan dengan pihak apotek.

Pelaksanaan penelitian dibagi menjadi dua tahap utama, yaitu tahap awal dan tahap pengembangan. Tahap awal berfokus pada proses *communication* untuk mengidentifikasi proses bisnis, alur kerja, dan permasalahan, sedangkan tahap pengembangan mencakup perencanaan, pemodelan, hingga pembangunan sistem. Secara keseluruhan tahapan penelitian mengikuti fase-fase metode *Prototyping* sebagaimana ditunjukkan pada Gambar 3.1.

> **Catatan penyusunan:** pertahankan Gambar 3.1 (Tahapan Penelitian) dari proposal.

Adapun tahapan penelitian yang dilakukan diuraikan sebagai berikut.

1. *Communication*. Tahap komunikasi dilakukan untuk mengumpulkan informasi dan data awal melalui studi literatur, observasi, wawancara, dan studi dokumentasi di Apotek Anugrah Husada. Tahap ini menghasilkan pemahaman terhadap proses bisnis dan identifikasi permasalahan. Rincian tahap ini dibahas pada subbab 3.2.
2. *Planning*. Tahap perencanaan dilakukan untuk menentukan kebutuhan sistem secara menyeluruh, meliputi kebutuhan pengguna, kebutuhan fungsional, kebutuhan non-fungsional, serta kebutuhan perangkat lunak dan perangkat keras. Rincian tahap ini dibahas pada subbab 3.3.
3. *Modeling*. Tahap pemodelan dilakukan untuk merancang alur sistem, struktur data, dan model Sistem Pendukung Keputusan metode SAW, meliputi perancangan sistem, penentuan kriteria, perumusan model, serta perhitungan dan penentuan prioritas. Rincian tahap ini dibahas pada subbab 3.4.
4. *Construction*. Tahap konstruksi dilakukan untuk membangun sistem berdasarkan hasil pemodelan, yang mencakup penulisan kode program dan pengujian sistem. Rincian tahap ini dibahas pada subbab 3.5 dan 3.6.

## 3.2 Tahapan Awal

> **Catatan penyusunan:** hasil pada 3.2.2 Observasi, 3.2.3 Wawancara, dan 3.2.4 Studi Dokumentasi (bagian bertanda `【...】`) wajib diisi dari kunjungan nyata ke Apotek Anugrah Husada dengan `instrumen_pengumpulan_data.md`. Jangan difinalkan sebelum data lapangan diperoleh.

Tahap awal merupakan bagian dari fase *communication* yang bertujuan mengumpulkan informasi dan data yang dibutuhkan sebagai fondasi perancangan sistem. Pada tahap ini peneliti berkomunikasi dengan pihak Apotek Anugrah Husada untuk memperoleh gambaran mengenai proses bisnis, alur kerja, dan permasalahan pengelolaan inventory obat. Tahap awal dilaksanakan melalui empat metode berikut.

### 3.2.1 Studi Literatur

Studi literatur dilakukan dengan mengkaji berbagai referensi yang relevan, seperti buku, jurnal ilmiah, artikel penelitian, dan sumber akademik lainnya yang berkaitan dengan sistem inventory, Sistem Pendukung Keputusan, serta metode *Simple Additive Weighting* (SAW). Studi literatur ini menjadi dasar teori dalam menentukan metode yang digunakan serta memperkuat konsep perancangan sistem.

### 3.2.2 Observasi

Observasi dilakukan untuk memperoleh gambaran langsung mengenai sistem yang sedang berjalan di Apotek Anugrah Husada. Pada tahap ini peneliti mengamati cara pencatatan obat masuk dan obat keluar, jumlah stok yang tersedia, cara pemantauan masa kedaluwarsa, serta cara penentuan kebutuhan restock obat. Hasil observasi menunjukkan bahwa 【temuan observasi: media pencatatan yang digunakan, cara dan pelaku pencatatan, serta kendala yang terlihat di lapangan】. Hasil ini memperlihatkan bahwa pengelolaan inventory masih dilakukan secara manual sehingga pemantauan stok secara real time menjadi sulit.

### 3.2.3 Wawancara

Wawancara dilakukan dengan pihak yang terlibat dalam pengelolaan inventory obat, dalam hal ini apoteker dan pemilik apotek. Tujuannya untuk memperoleh informasi yang lebih rinci mengenai permasalahan dan kebutuhan sistem. Dari hasil wawancara diperoleh informasi mengenai 【temuan wawancara: kendala pengelolaan stok, kebijakan dan pertimbangan dalam menentukan restock, serta kriteria yang dianggap penting】. Wawancara ini juga digunakan untuk menentukan kriteria dan bobot pada metode SAW berdasarkan tingkat kepentingan menurut pihak apotek, yang penerapannya dibahas pada subbab 3.4.2.

### 3.2.4 Studi Dokumentasi

Studi dokumentasi dilakukan untuk mengumpulkan dan mempelajari dokumen serta data yang berkaitan dengan pengelolaan inventory obat di Apotek Anugrah Husada. Dokumen yang digunakan meliputi data obat, data obat masuk, data obat keluar, dan data masa kedaluwarsa obat. Data yang diperoleh melalui studi dokumentasi digunakan sebagai data utama maupun data pendukung dalam proses analisis serta sebagai masukan bagi perhitungan metode SAW.

### 3.2.5 Identifikasi Masalah

Berdasarkan hasil observasi dan wawancara yang telah dilakukan, diperoleh identifikasi masalah yang menjadi dasar pengembangan sistem sebagaimana disajikan pada Tabel 3.1.

**Tabel 3.1. Identifikasi Masalah**

| Permasalahan | Dampak | Solusi yang Diharapkan |
|---|---|---|
| Pengelolaan stok obat masih manual menggunakan buku dan Microsoft Excel. | Terjadi ketidaksesuaian antara data stok dan kondisi fisik di gudang. | Dibutuhkan sistem inventory yang mampu mencatat dan menyimpan data stok secara otomatis. |
| Belum memiliki sistem yang dapat memantau stok minimum dan masa kedaluwarsa obat secara otomatis. | Keterlambatan melakukan restock obat dan meningkatnya risiko obat kedaluwarsa yang tidak terpantau tepat waktu. | Perlu fitur notifikasi otomatis untuk stok menipis dan obat mendekati masa kedaluwarsa sebagai peringatan dini bagi petugas. |
| Penentuan prioritas restock obat masih mengandalkan pengalaman dan perkiraan tanpa analisis data yang terstruktur. | Berpotensi menyebabkan kekurangan stok obat yang sering digunakan atau penumpukan obat yang jarang terpakai. | Diperlukan Sistem Pendukung Keputusan untuk memberikan rekomendasi prioritas restock obat secara objektif dan berbasis data. |

## 3.3 Perencanaan

Tahap perencanaan merupakan bagian dari fase *planning* yang bertujuan menentukan kebutuhan sistem secara menyeluruh sebagai dasar perancangan dan pengembangan. Tahap ini berfokus pada identifikasi kebutuhan pengguna dan kebutuhan sistem, baik dari sisi fungsional maupun non-fungsional, agar sistem yang dibangun dapat mendukung pengelolaan inventory obat secara efisien dan efektif.

### 3.3.1 Analisis Kebutuhan Pengguna

Analisis kebutuhan pengguna dilakukan untuk menentukan kebutuhan setiap pengguna sistem berdasarkan peran dan tanggung jawabnya dalam pengelolaan inventory obat. Rancangan hak akses pengguna disajikan pada Tabel 3.2.

**Tabel 3.2. Analisis Kebutuhan Pengguna**

| No | Jenis Pengguna | Hak Akses | Kebutuhan Fitur dan Fungsi |
|---|---|---|---|
| 1 | Admin | Akses penuh terhadap sistem | Mengelola data pengguna, data obat, kriteria dan bobot SAW, serta seluruh data sistem. |
| 2 | Petugas Apotek | Akses terbatas sesuai operasional | Mengelola data obat masuk dan obat keluar, melihat stok dan masa kedaluwarsa, serta menjalankan proses perhitungan SPK. |
| 3 | Pemilik atau Manajer | Akses monitoring dan keputusan | Melihat laporan inventory, hasil rekomendasi SPK, dan informasi pendukung keputusan restock. |

Pembagian peran pada Tabel 3.2 merupakan rancangan hak akses. Infrastruktur pengaturan hak akses berbasis peran telah disediakan pada sistem melalui Spatie Permission dan Filament Shield, sehingga pemisahan hak akses tersebut dapat diaktifkan pada tahap konfigurasi lanjutan sesuai kebutuhan apotek.

### 3.3.2 Analisis Kebutuhan Fungsional

Analisis kebutuhan fungsional dilakukan untuk mengidentifikasi fungsi utama yang harus dimiliki sistem agar dapat mendukung pengelolaan inventory obat dan pengambilan keputusan restock secara optimal. Kebutuhan fungsional disajikan pada Tabel 3.3.

**Tabel 3.3. Kebutuhan Fungsional**

| No | Kode | Kebutuhan Fungsional |
|---|---|---|
| 1 | F-01 | Sistem menyediakan fitur login dan autentikasi pengguna. |
| 2 | F-02 | Sistem dapat mengelola data obat meliputi tambah, ubah, hapus, dan lihat. |
| 3 | F-03 | Sistem dapat mencatat transaksi obat masuk dan obat keluar. |
| 4 | F-04 | Sistem memberikan notifikasi stok minimum dan obat mendekati kedaluwarsa. |
| 5 | F-05 | Sistem menghasilkan laporan inventory secara otomatis. |
| 6 | F-06 | Sistem menerapkan metode SAW untuk rekomendasi prioritas restock obat. |
| 7 | F-07 | Sistem menampilkan hasil perangkingan obat sebagai dasar pengambilan keputusan. |

### 3.3.3 Analisis Kebutuhan Non-Fungsional

Analisis kebutuhan non-fungsional dilakukan untuk memastikan sistem tidak hanya berfungsi secara teknis, tetapi juga memiliki kualitas yang mendukung kenyamanan, keamanan, dan keandalan bagi penggunanya. Rincian kebutuhan non-fungsional adalah sebagai berikut.

1. *Security*. Sistem memiliki mekanisme autentikasi pengguna, pembatasan hak akses berdasarkan peran, serta perlindungan terhadap data inventory.
2. *Performance*. Sistem mampu memproses data inventory dan perhitungan SAW dengan cepat tanpa mengganggu aktivitas operasional apotek.
3. *Usability*. Antarmuka sistem dirancang sederhana dan mudah digunakan sehingga pengguna dapat mengoperasikannya tanpa memerlukan pelatihan khusus.
4. *Reliability*. Sistem dapat berjalan secara stabil, meminimalkan kesalahan, serta mampu menyimpan dan menampilkan data secara konsisten.

### 3.3.4 Analisis Kebutuhan Perangkat Lunak

Perangkat lunak yang digunakan dipilih berdasarkan kesesuaian dengan kebutuhan sistem dan kemudahan pengembangan, yang meliputi:

1. sistem operasi Windows atau Linux,
2. peramban web (*web browser*),
3. Visual Studio Code sebagai perangkat pengembangan,
4. MySQL sebagai basis data,
5. PHP dengan framework Laravel sebagai bahasa dan kerangka kerja pengembangan, serta
6. Filament sebagai kerangka kerja panel administrasi.

### 3.3.5 Analisis Kebutuhan Perangkat Keras

Perangkat keras yang digunakan harus mampu mendukung proses pengembangan, pengelolaan data, serta akses sistem secara stabil, yang meliputi:

1. laptop atau komputer (PC),
2. server,
3. perangkat jaringan, serta
4. RAM minimal 4 GB dengan rekomendasi 8 GB untuk kenyamanan penggunaan.

## 3.4 Pemodelan

Tahap pemodelan merupakan bagian dari fase *modeling* yang bertujuan merancang alur sistem, struktur data, dan model Sistem Pendukung Keputusan metode SAW berdasarkan hasil analisis kebutuhan. Tahap ini memberikan gambaran mengenai mekanisme kerja sistem, integrasi antar komponen, serta proses perhitungan perangkingan obat untuk rekomendasi restock sebelum masuk ke tahap pembangunan.

### 3.4.1 Perancangan Sistem

Perancangan sistem dilakukan untuk memberikan gambaran menyeluruh mengenai struktur dan alur kerja sistem inventory. Perancangan ini menggunakan diagram *Unified Modeling Language* (UML) untuk menunjukkan interaksi pengguna dengan sistem serta alur prosesnya, dan *Entity Relationship Diagram* (ERD) untuk menggambarkan struktur basis data.

> **Catatan penyusunan:** gambar diagram disusun berdasarkan `docs/UML-plan.md` (berisi rancangan use case, activity, dan ERD beserta diagram Mermaid yang dapat diekspor). Setiap penanda `【Sisipkan gambar di sini】` diganti dengan gambar hasil ekspor. Sequence Diagram sengaja tidak digunakan di Bab III — cukup Use Case, Activity, dan ERD.

**1. Use Case Diagram**

Use case diagram menggambarkan interaksi antara aktor dan sistem pada fungsi-fungsi utama. Terdapat tiga aktor, yaitu Admin, Petugas Apotek, dan Pemilik atau Manajer. Admin memiliki akses penuh terhadap sistem, termasuk mengelola data pengguna, data obat, serta kriteria dan bobot SAW. Petugas Apotek bertugas mengelola transaksi obat masuk dan obat keluar serta menjalankan proses perhitungan SPK. Pemilik atau Manajer bertugas memantau laporan inventory dan hasil rekomendasi prioritas restock sebagai dasar pengambilan keputusan. Rancangan use case diagram ditunjukkan pada Gambar 3.2.

【Sisipkan gambar di sini】

**Gambar 3. 2. Use Case Diagram Sistem**

**2. Activity Diagram**

Activity diagram menggambarkan alur proses pengelolaan inventory obat, mulai dari login, pencatatan obat masuk dan obat keluar, hingga proses perhitungan SAW dalam menentukan prioritas restock. Diagram ini menampilkan langkah-langkah aktivitas dari awal hingga akhir proses, termasuk titik keputusan seperti validasi ketersediaan stok pada pencatatan obat keluar dan validasi total bobot pada proses perhitungan SAW. Alur aktivitas proses login ditunjukkan pada Gambar 3.3, pencatatan obat masuk pada Gambar 3.4, pencatatan obat keluar pada Gambar 3.5, dan perhitungan prioritas restock pada Gambar 3.6.

【Sisipkan gambar di sini】

**Gambar 3. 3. Activity Diagram Login**

【Sisipkan gambar di sini】

**Gambar 3. 4. Activity Diagram Pencatatan Obat Masuk**

【Sisipkan gambar di sini】

**Gambar 3. 5. Activity Diagram Pencatatan Obat Keluar**

【Sisipkan gambar di sini】

**Gambar 3. 6. Activity Diagram Perhitungan Prioritas Restock**

**3. Entity Relationship Diagram (ERD)**

ERD digunakan untuk merancang struktur basis data dengan menggambarkan hubungan antar entitas, seperti data obat, stok, transaksi obat masuk, transaksi obat keluar, pengguna, serta entitas terkait perhitungan SAW. Diagram ini menjadi dasar dalam pembentukan tabel dan relasi pada basis data sistem. Rancangan basis data ditunjukkan pada Gambar 3.7.

【Sisipkan gambar di sini】

**Gambar 3. 7. Entity Relationship Diagram (ERD)**

### 3.4.2 Penentuan Kriteria

Kriteria yang digunakan dalam proses pengambilan keputusan ditetapkan sebagai dasar rekomendasi restock. Penentuan kriteria didasarkan pada hasil analisis kebutuhan, observasi, dan wawancara dengan pihak apotek (subbab 3.2.3) agar sesuai dengan keadaan di lapangan. Terdapat empat kriteria dengan bobot yang ditentukan berdasarkan tingkat kepentingan menurut pihak apotek, dengan total bobot sama dengan 1, sebagaimana disajikan pada Tabel 3.4.

**Tabel 3.4. Penentuan Kriteria**

| Kode | Nama Kriteria | Jenis Kriteria | Keterangan | Bobot |
|---|---|---|---|:---:|
| C1 | Stok | Cost | Semakin sedikit semakin prioritas | 0,30 |
| C2 | Permintaan | Benefit | Semakin tinggi semakin prioritas | 0,30 |
| C3 | Kedaluwarsa | Cost | Semakin dekat semakin prioritas | 0,20 |
| C4 | Harga | Cost | Semakin murah semakin prioritas | 0,20 |
| | | | **Total** | **1,00** |

Setiap nilai mentah kriteria selanjutnya dikonversi ke dalam skala penilaian 1 sampai 5. Konversi disusun dengan arah natural, yaitu nilai mentah yang makin besar memperoleh skor yang makin besar. Adapun arah prioritas untuk kriteria *cost* (stok, kedaluwarsa, dan harga) tidak dibalik pada tabel skala, melainkan diterapkan pada tahap normalisasi melalui rumus *cost* Min/X sebagaimana dibahas pada subbab 3.4.3. Ketentuan skala penilaian untuk masing-masing kriteria diuraikan sebagai berikut.

**1. Jumlah Stok Obat (C1)**

Jumlah stok obat menunjukkan banyaknya obat yang tersedia di gudang apotek pada saat perhitungan dilakukan. Kriteria ini bersifat *cost* karena obat dengan stok yang lebih sedikit lebih diprioritaskan untuk pengadaan. Apotek menggunakan batas stok minimum sebesar 10 unit sebagai acuan kewaspadaan, dengan satuan per strip atau per botol. Ketentuan skala jumlah stok disajikan pada Tabel 3.5.

**Tabel 3.5. Skor Jumlah Stok Obat**

| No | Jumlah Stok | Nilai |
|---|---|:---:|
| 1 | ≤ 10 | 1 |
| 2 | 11 sampai 30 | 2 |
| 3 | 31 sampai 60 | 3 |
| 4 | 61 sampai 100 | 4 |
| 5 | > 100 | 5 |

**2. Tingkat Permintaan (C2)**

Tingkat permintaan didasarkan pada data transaksi penjualan obat selama satu bulan dan menunjukkan seberapa sering suatu obat dibutuhkan. Kriteria ini bersifat *benefit* karena semakin tinggi tingkat permintaan, semakin besar kemungkinan obat tersebut perlu diprioritaskan untuk pengadaan. Batas tingkat permintaan yang ditentukan adalah 20 transaksi per bulan. Ketentuan skala tingkat permintaan disajikan pada Tabel 3.6.

**Tabel 3.6. Skor Tingkat Permintaan Obat**

| No | Tingkat Permintaan per Bulan | Nilai |
|---|---|:---:|
| 1 | > 80 | 5 |
| 2 | 61 sampai 80 | 4 |
| 3 | 41 sampai 60 | 3 |
| 4 | 20 sampai 40 | 2 |
| 5 | < 20 | 1 |

**3. Masa Kedaluwarsa (C3)**

Masa kedaluwarsa menunjukkan sisa waktu yang masih aman hingga tanggal kedaluwarsa obat, dihitung dari jumlah hari antara tanggal perhitungan dan tanggal kedaluwarsa. Kriteria ini bersifat *cost* karena obat dengan masa kedaluwarsa yang lebih dekat harus lebih diprioritaskan dalam pengelolaan persediaan. Batas minimal masa kedaluwarsa yang ditentukan adalah 90 hari, mengikuti aturan retur dari pedagang besar farmasi (PBF). Ketentuan skala masa kedaluwarsa disajikan pada Tabel 3.7.

**Tabel 3.7. Skor Masa Kedaluwarsa Obat**

| No | Sisa Masa Kedaluwarsa (hari) | Nilai |
|---|---|:---:|
| 1 | ≤ 90 | 1 |
| 2 | 91 sampai 180 | 2 |
| 3 | 181 sampai 365 | 3 |
| 4 | 366 sampai 730 | 4 |
| 5 | > 730 | 5 |

**4. Harga Beli Obat (C4)**

Harga beli obat merupakan biaya satuan yang dikeluarkan apotek untuk memperoleh obat dari pemasok, sebagaimana tercatat pada sistem inventory. Kriteria ini bersifat *cost* karena obat dengan harga beli yang lebih rendah lebih efisien untuk diprioritaskan dalam pengadaan. Batas harga prioritas yang ditentukan adalah kurang dari atau sama dengan Rp10.000. Ketentuan skala harga beli disajikan pada Tabel 3.8.

**Tabel 3.8. Skor Harga Beli Obat**

| No | Harga Beli (Rupiah) | Nilai |
|---|---|:---:|
| 1 | ≤ 10.000 | 1 |
| 2 | 10.001 sampai 25.000 | 2 |
| 3 | 25.001 sampai 50.000 | 3 |
| 4 | 50.001 sampai 100.000 | 4 |
| 5 | > 100.000 | 5 |

### 3.4.3 Perumusan Model SPK Metode SAW

Metode *Simple Additive Weighting* (SAW) diterapkan sebagai model Sistem Pendukung Keputusan untuk menentukan prioritas restock obat secara sistematis melalui tahapan berikut.

**1. Menentukan Alternatif**

Alternatif yang dinilai adalah obat-obat yang tersedia di Apotek Anugrah Husada. Setiap obat dianggap sebagai satu alternatif yang akan dievaluasi tingkat prioritas restock-nya. Alternatif dinotasikan sebagai berikut.

A = {A1, A2, A3, ..., Am}

**2. Menentukan Kriteria**

Kriteria yang digunakan dalam evaluasi adalah jumlah stok (C1), tingkat permintaan (C2), masa kedaluwarsa (C3), dan harga beli (C4), sebagaimana telah ditetapkan pada subbab 3.4.2. Kriteria dinotasikan sebagai berikut.

C = {C1, C2, C3, C4}

**3. Menentukan Bobot Kriteria**

Setiap kriteria diberi bobot sesuai tingkat kepentingannya berdasarkan hasil wawancara dengan pihak apotek, dengan ketentuan total bobot sama dengan 1. Bobot yang digunakan adalah C1 sebesar 0,30, C2 sebesar 0,30, C3 sebesar 0,20, dan C4 sebesar 0,20. Bobot dinotasikan sebagai berikut.

W = {0,30; 0,30; 0,20; 0,20}

**4. Menyusun Matriks Keputusan**

Nilai setiap alternatif terhadap masing-masing kriteria, setelah dikonversi ke skala penilaian 1 sampai 5, membentuk matriks keputusan X yang menjadi dasar perhitungan SAW. Nilai Xij menyatakan skor alternatif ke-i pada kriteria ke-j.

**5. Normalisasi Matriks**

Normalisasi dilakukan untuk menyamakan skala nilai antar kriteria agar dapat dibandingkan secara adil. Proses normalisasi menyesuaikan jenis kriteria, yaitu *benefit* atau *cost*, dengan rumus berikut.

Untuk kriteria *benefit*: `Rij = Xij / (Max Xij)`

Untuk kriteria *cost*: `Rij = (Min Xij) / Xij`

Pada penelitian ini, kriteria C2 bersifat *benefit* sehingga menggunakan rumus *benefit*, sedangkan kriteria C1, C3, dan C4 bersifat *cost* sehingga menggunakan rumus *cost*. Melalui rumus *cost* inilah arah prioritas untuk stok, kedaluwarsa, dan harga diterapkan, sehingga skor pada tabel skala tetap disusun dengan arah natural.

**6. Menghitung Nilai Preferensi**

Nilai preferensi setiap alternatif diperoleh dengan menjumlahkan hasil perkalian antara bobot kriteria dan nilai normalisasi, menggunakan rumus berikut.

`Vi = Σ (Wj × Rij)`

Nilai Vi menyatakan nilai preferensi alternatif ke-i. Semakin besar nilai Vi, semakin tinggi prioritas obat tersebut untuk dilakukan restock.

**7. Perangkingan**

Langkah terakhir adalah mengurutkan alternatif berdasarkan nilai preferensi Vi dari yang tertinggi ke terendah. Alternatif dengan nilai preferensi tertinggi menjadi prioritas utama untuk pengadaan ulang. Hasil perangkingan ini kemudian ditampilkan oleh sistem sebagai rekomendasi pengambilan keputusan restock. Contoh penerapan seluruh tahapan ini dengan data obat disajikan pada subbab 3.4.4.

### 3.4.4 Perhitungan dan Penentuan Prioritas

Tahap perhitungan dan penentuan prioritas bertujuan menunjukkan penerapan langkah-langkah metode SAW dalam menentukan urutan prioritas restock obat. Perhitungan pada subbab ini menggunakan contoh data ilustrasi untuk mendemonstrasikan cara kerja metode secara manual, sedangkan perhitungan dengan data aktual Apotek Anugrah Husada beserta keluaran sistem disajikan pada Bab IV. Sistem yang dibangun dapat mengolah seluruh data obat secara otomatis.

**1. Contoh Nilai Awal Alternatif (Ilustrasi)**

Sebagai ilustrasi diambil lima obat sebagai alternatif untuk mendemonstrasikan langkah perhitungan. Nilai awal setiap alternatif berdasarkan empat kriteria disajikan pada Tabel 3.9. Nilai pada tabel ini bersifat ilustratif dan tidak mewakili data aktual apotek.

**Tabel 3.9. Contoh Nilai Awal Alternatif Obat (Ilustrasi)**

| Kode | Nama Obat | Stok | Permintaan/Bulan | Sisa ED (Hari) | Harga Beli (Rp) |
|---|---|:---:|:---:|:---:|:---:|
| A1 | Paracetamol 500mg | 8 | 250 | 800 | 2.500 |
| A2 | Amoxicillin 500mg | 12 | 90 | 450 | 6.500 |
| A6 | Lansoprazole 30mg | 65 | 55 | 380 | 15.000 |
| A7 | Sangobion Cap | 95 | 85 | 900 | 18.500 |
| A9 | Lipitor 20mg | 4 | 15 | 420 | 165.000 |

**2. Konversi Nilai ke Skala Penilaian**

Nilai awal pada Tabel 3.9 kemudian dikonversi ke skala penilaian 1 sampai 5 sesuai Tabel 3.5 hingga Tabel 3.8. Konversi dilakukan dengan arah natural, yaitu nilai mentah yang makin besar memperoleh skor yang makin besar, sedangkan arah prioritas untuk kriteria *cost* diterapkan pada tahap normalisasi. Hasil konversi kelima alternatif tersebut disajikan pada Tabel 3.10.

**Tabel 3.10. Konversi Nilai (Matriks Keputusan)**

| Alternatif | C1 (Stok) | C2 (Permintaan) | C3 (Kedaluwarsa) | C4 (Harga) |
|:---:|:---:|:---:|:---:|:---:|
| A1 | 1 | 5 | 5 | 1 |
| A2 | 2 | 5 | 4 | 1 |
| A6 | 4 | 3 | 4 | 2 |
| A7 | 4 | 5 | 5 | 2 |
| A9 | 1 | 1 | 4 | 5 |

Dari data tersebut ditentukan nilai acuan normalisasi, yaitu nilai minimum C1 sebesar 1, nilai maksimum C2 sebesar 5, nilai minimum C3 sebesar 4, dan nilai minimum C4 sebesar 1.

**3. Normalisasi Matriks**

Normalisasi dilakukan menggunakan rumus SAW sesuai jenis kriteria. Kriteria C2 bersifat *benefit* sehingga memakai rumus Xij dibagi nilai maksimum, sedangkan kriteria C1, C3, dan C4 bersifat *cost* sehingga memakai rumus nilai minimum dibagi Xij. Hasil normalisasi tiap kriteria disajikan pada Tabel 3.11 hingga Tabel 3.14.

**Tabel 3.11. Hasil Normalisasi C1 (Stok, Cost)**

| Alternatif | Perhitungan | Rij |
|:---:|:---:|:---:|
| A1 | 1 / 1 | 1,00 |
| A2 | 1 / 2 | 0,50 |
| A6 | 1 / 4 | 0,25 |
| A7 | 1 / 4 | 0,25 |
| A9 | 1 / 1 | 1,00 |

**Tabel 3.12. Hasil Normalisasi C2 (Permintaan, Benefit)**

| Alternatif | Perhitungan | Rij |
|:---:|:---:|:---:|
| A1 | 5 / 5 | 1,00 |
| A2 | 5 / 5 | 1,00 |
| A6 | 3 / 5 | 0,60 |
| A7 | 5 / 5 | 1,00 |
| A9 | 1 / 5 | 0,20 |

**Tabel 3.13. Hasil Normalisasi C3 (Kedaluwarsa, Cost)**

| Alternatif | Perhitungan | Rij |
|:---:|:---:|:---:|
| A1 | 4 / 5 | 0,80 |
| A2 | 4 / 4 | 1,00 |
| A6 | 4 / 4 | 1,00 |
| A7 | 4 / 5 | 0,80 |
| A9 | 4 / 4 | 1,00 |

**Tabel 3.14. Hasil Normalisasi C4 (Harga, Cost)**

| Alternatif | Perhitungan | Rij |
|:---:|:---:|:---:|
| A1 | 1 / 1 | 1,00 |
| A2 | 1 / 1 | 1,00 |
| A6 | 1 / 2 | 0,50 |
| A7 | 1 / 2 | 0,50 |
| A9 | 1 / 5 | 0,20 |

Hasil normalisasi seluruh kriteria kemudian dikumpulkan menjadi satu matriks normalisasi sebagaimana disajikan pada Tabel 3.15.

**Tabel 3.15. Matriks Normalisasi (Rij)**

| Alternatif | C1 | C2 | C3 | C4 |
|:---:|:---:|:---:|:---:|:---:|
| A1 | 1,00 | 1,00 | 0,80 | 1,00 |
| A2 | 0,50 | 1,00 | 1,00 | 1,00 |
| A6 | 0,25 | 0,60 | 1,00 | 0,50 |
| A7 | 0,25 | 1,00 | 0,80 | 0,50 |
| A9 | 1,00 | 0,20 | 1,00 | 0,20 |

Nilai normalisasi berada pada rentang 0 sampai 1, di mana nilai yang mendekati 1 menunjukkan tingkat prioritas yang lebih tinggi pada kriteria tersebut.

**4. Perhitungan Nilai Preferensi (Vi)**

Nilai preferensi diperoleh dengan menjumlahkan hasil perkalian antara bobot kriteria dan nilai normalisasi, dengan bobot C1 sebesar 0,30, C2 sebesar 0,30, C3 sebesar 0,20, dan C4 sebesar 0,20. Hasil perhitungan tiap alternatif adalah sebagai berikut.

V1 = (0,30 × 1,00) + (0,30 × 1,00) + (0,20 × 0,80) + (0,20 × 1,00) = 0,96

V2 = (0,30 × 0,50) + (0,30 × 1,00) + (0,20 × 1,00) + (0,20 × 1,00) = 0,85

V6 = (0,30 × 0,25) + (0,30 × 0,60) + (0,20 × 1,00) + (0,20 × 0,50) = 0,56

V7 = (0,30 × 0,25) + (0,30 × 1,00) + (0,20 × 0,80) + (0,20 × 0,50) = 0,64

V9 = (0,30 × 1,00) + (0,30 × 0,20) + (0,20 × 1,00) + (0,20 × 0,20) = 0,60

**5. Perangkingan Prioritas Restock**

Berdasarkan nilai preferensi yang diperoleh, dilakukan perangkingan untuk menentukan urutan prioritas restock obat. Alternatif dengan nilai preferensi tertinggi menjadi prioritas utama untuk pengadaan ulang, sebagaimana disajikan pada Tabel 3.16.

**Tabel 3.16. Hasil Perangkingan Prioritas Restock**

| Peringkat | Alternatif | Nilai Vi |
|:---:|:---:|:---:|
| 1 | A1 (Paracetamol 500mg) | 0,96 |
| 2 | A2 (Amoxicillin 500mg) | 0,85 |
| 3 | A7 (Sangobion Cap) | 0,64 |
| 4 | A9 (Lipitor 20mg) | 0,60 |
| 5 | A6 (Lansoprazole 30mg) | 0,56 |

**6. Interpretasi Hasil**

Berdasarkan hasil perhitungan, alternatif A1 (Paracetamol 500mg) memperoleh nilai preferensi tertinggi sebesar 0,96 sehingga menjadi prioritas utama restock. Hal ini disebabkan oleh kombinasi kondisi yang paling mendesak, yaitu stok yang sangat rendah, tingkat permintaan yang sangat tinggi, serta harga beli yang murah, meskipun masa kedaluwarsanya masih panjang. Sebaliknya, alternatif A6 (Lansoprazole 30mg) memperoleh nilai preferensi terendah sebesar 0,56 karena stoknya masih memadai dengan tingkat permintaan dan atribut lain yang tergolong sedang sehingga tidak mendesak untuk dilakukan pengadaan ulang. Hasil perangkingan ini kemudian ditampilkan oleh sistem sebagai rekomendasi pengambilan keputusan restock, dan dapat dihitung ulang secara otomatis maupun manual mengikuti perubahan data stok dan penjualan.

## 3.5 Implementasi Sistem

Implementasi sistem merupakan bagian dari fase *construction*, yaitu pembangunan sistem inventory obat berbasis web yang terintegrasi dengan Sistem Pendukung Keputusan metode SAW berdasarkan hasil pemodelan. Sistem dibangun menggunakan bahasa pemrograman PHP dengan framework Laravel versi 12 dan kerangka kerja panel administrasi Filament versi 4, basis data MySQL untuk penyimpanan data, Tailwind CSS versi 4 untuk tampilan antarmuka, serta Visual Studio Code sebagai perangkat pengembangan.

Modul utama yang dibangun meliputi autentikasi pengguna, pengelolaan data obat, pencatatan transaksi obat masuk dan obat keluar, pengelolaan kriteria dan bobot SAW, proses perhitungan SAW, serta penyajian hasil perangkingan dan laporan inventory. Data inventory yang tercatat pada sistem digunakan sebagai masukan bagi perhitungan metode SAW, yang kemudian diproses secara otomatis untuk menghasilkan rekomendasi prioritas restock obat. Hasil implementasi sistem secara lengkap beserta tampilan antarmukanya disajikan pada Bab IV.

## 3.6 Pengujian Sistem

Setelah pembangunan sistem selesai, dilakukan pengujian untuk memastikan seluruh fungsi berjalan sesuai kebutuhan. Pengujian menggunakan metode *blackbox testing*, yaitu pengujian yang berfokus pada fungsi sistem tanpa melihat struktur kode program. Pengujian dilakukan terhadap fitur-fitur utama sistem sebagaimana disajikan pada Tabel 3.17.

**Tabel 3.17. Rencana Pengujian Sistem (Blackbox Testing)**

| No | Fitur | Skenario Pengujian | Hasil yang Diharapkan |
|---|---|---|---|
| 1 | Login | Memasukkan kredensial yang benar dan yang salah | Berhasil masuk ke dashboard saat benar dan menampilkan pesan gagal saat salah |
| 2 | Kelola Data Obat | Menambah, mengubah, menghapus, dan melihat data obat | Data obat tersimpan dan tampil sesuai masukan |
| 3 | Obat Masuk | Mencatat penerimaan obat beserta batch dan tanggal kedaluwarsa | Stok bertambah dan data kedaluwarsa tercatat |
| 4 | Obat Keluar | Mencatat penjualan dengan jumlah melebihi stok dan jumlah dalam batas stok | Ditolak saat melebihi stok dan tersimpan saat mencukupi sehingga stok berkurang |
| 5 | Notifikasi | Terdapat obat dengan stok minimum atau mendekati kedaluwarsa | Sistem menampilkan notifikasi peringatan |
| 6 | Perhitungan SAW | Menjalankan perhitungan dengan total bobot sama dengan 1 dan tidak sama dengan 1 | Perhitungan berjalan saat bobot valid dan ditolak dengan peringatan saat tidak valid |
| 7 | Hasil Perangkingan | Menampilkan hasil perhitungan SAW | Obat tampil terurut berdasarkan nilai preferensi dari tertinggi ke terendah |

Hasil pelaksanaan pengujian secara lengkap disajikan pada Bab IV.

---

