<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarif PPN per RO dibalik (keputusan peneliti 2026-10-01): kolom ini ternyata tidak terpakai
 * 15 dari 16 RO menyimpan nilai kosong. Tarif kembali satu angka global di Pengaturan
 * (`app/Settings/GeneralSettings.php`), seperti sebelum migrasi 2026_09_29_000001.
 *
 * Lihat docs/analisa-tambahan-2026-09.md bagian 2 untuk riwayat keputusan sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receive_orders', function (Blueprint $table) {
            $table->dropColumn('ppn_rate');
        });
    }

    public function down(): void
    {
        Schema::table('receive_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('ppn_rate')->nullable()->after('receive_date')
                ->comment('Tarif PPN faktur (%); null = faktur tidak mencantumkan pajak');
        });
    }
};
