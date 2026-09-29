<?php

namespace App\Support;

/**
 * Satu format tanggal untuk seluruh aplikasi (K3, K9).
 *
 * Sebelum 2026-09-29 ada delapan bentuk dipakai bersamaan (`d M Y`, `d-m-Y`, `d/m/Y`, `d F Y`,
 * `m-Y`, dan beberapa varian berjam), sehingga dua kolom di layar yang sama bisa menampilkan
 * tanggal dengan gaya berbeda.
 *
 * Nama bulan mengikuti locale Carbon yang diset ke `id` di `AppServiceProvider`, jadi
 * `d M Y` menghasilkan "07 Sep 2026", bukan "07 Sep 2026" versi Inggris yang berbeda pada
 * Agu, Okt, dan Des. Filament memformat tanggal lewat `translatedFormat()` sehingga ikut sendiri.
 *
 * Bukan untuk nama berkas ekspor (`Ymd_His`) maupun nomor dokumen (`Ymd`): keduanya bukan tanggal
 * yang dibaca manusia dan formatnya tidak boleh berubah.
 */
class Tanggal
{
    /** Tanggal biasa: 07 Sep 2026. */
    public const TAMPIL = 'd M Y';

    /** Tanggal beserta jam: 07 Sep 2026 14:30. */
    public const TAMPIL_JAM = 'd M Y H:i';

    /** Bulan dan tahun saja, dipakai untuk kedaluwarsa: Sep 2026. */
    public const BULAN_TAHUN = 'M Y';
}
