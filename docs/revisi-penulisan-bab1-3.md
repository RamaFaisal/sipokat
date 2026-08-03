# Revisi Penulisan Bab I-III

Checklist aktif. Basis: `draft TA 2 (Bab 1 - 3).pdf` vs `CLAUDE.md`, [[saw-normalization-decision]], `docs/UML-plan.md`.

## Selesai ✅
1. Diagram Use Case — aktor Admin/Petugas/Pemilik + 7 use case sudah cocok Tabel 3.2/3.3.
2. Urutan Tujuan Penelitian — sudah SAW → Inventory → Monitoring, sejalan Rumusan Masalah.

## Belum — formatting

3. **Nomor gambar hilang** (Bab III). 6 gambar (Use Case, 4× Activity, ERD) tanpa nomor caption, tidak ada di Daftar Gambar, padahal teks 3.4.1 sudah menyebut "Gambar 3.3"–"3.6". → Tambahkan caption "Gambar 3.2"–"3.7" sesuai `UML-plan.md`, update Daftar Gambar.

4. **Daftar Isi Bab III belum sinkron dengan body**. Body 3.2 sudah benar (3.2.1–3.2.5), body 3.3 sudah benar (3.3.1–3.3.5), tapi Daftar Isi masih pakai nomor lama (3.3.1–3.3.5 untuk 3.2, angka polos "1."–"5." untuk 3.3). → Update Daftar Isi ikut body.

5. **Penomoran sub-bab 2.2 geser satu nomor**. Daftar Isi: 2.2.1 Sistem, 2.2.2 Sistem Inventory, ..., 2.2.7 Decision Support System (DSS). Body: langsung "2.2.1 Sistem Inventory" (gabungan), lalu geser jadi "2.2.6 Sistem Pendukung Keputusan (SPK)" — judul beda juga (SPK vs DSS). → Pilih: tambah sub-bab "Sistem" terpisah di body, atau update Daftar Isi + samakan judul SPK/DSS.

6. **Typo "4. 4. Construction."** (Bab 3.1) — dobel angka. → Jadi "4. Construction."

## Belum — butuh verifikasi/keputusan konten

7. **Tahun sitasi Lubis & Rosnelly tidak konsisten**: 2 in-text Bab I = 2026, Tabel 2.1 = 2024, Daftar Pustaka = 2024. → Cek tahun asli di jurnal, samakan ke satu tahun di 4 lokasi. (Ejaan "Oktavia" sudah konsisten, tidak perlu diubah.)

8. **(Opsional) Sitasi Navia Rani (2022)** dipakai untuk definisi umum "sistem", tapi sumbernya tentang TOPSIS pemilihan kedelai. → Ganti rujukan yang lebih relevan, atau biarkan kalau dianggap cukup.

## Sudah sesuai, tidak perlu diubah
- Rumus normalisasi SAW (2.2.7, 3.4.3) — cost `Min/X`, benefit `X/max`, konsisten kode.
- Tabel skala 3.5/3.7/3.8 — arah natural, cocok `SawCriteriaSeeder.php`.
- Worked example 3.4.4 (Tabel 3.9–3.16) — ranking A1=0,96 > A2=0,85 > A7=0,64 > A9=0,60 > A6=0,56, cocok kode.
- Teori Filament + versi stack (PHP 8.2, Laravel 12, Filament 4, Tailwind 4) di 2.2.5 & 3.5.
- Tabel 3.2 — reworded "rancangan hak akses", sesuai keputusan skip role di `CLAUDE.md`.
- Ejaan "Anugrah Husada" konsisten.
- Placeholder data lapangan (`【...】`) dipertahankan, belum mengarang data primer.
- Batasan Masalah poin 2 (penjualan) konsisten dengan cakupan sistem.

## Catatan
`docs/perbaikan_skripsi.md` = arsip keputusan lama (sudah dieksekusi semua). File ini = checklist aktif.
