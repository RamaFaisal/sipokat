<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat snapshot SAW dihapus (keputusan peneliti 2026-09-27). Peringkat kini dihitung
 * langsung saat halaman/dashboard dibuka, sehingga tidak ada yang perlu disimpan skema
 * tidak lagi menyimpan tabel yang hanya menampung hasil turunan.
 *
 * Bukti angka untuk naskah sudah dibekukan di luar database sebagai berkas
 * `storage/app/exports/verifikasi-manual-saw-2026-09-23.xlsx`.
 *
 * Maju saja: tidak ada down() yang bermakna karena isi tabelnya memang tidak dipulihkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('saw_calculation_results'); // anak lebih dulu (FK ke saw_calculations)
        Schema::dropIfExists('saw_calculations');
    }

    public function down(): void
    {
        // Tidak dapat dibalik: data hasil perhitungan bersifat turunan dan tidak disimpan lagi.
    }
};
