<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarif PPN pindah dari pengaturan global ke tiap RO (K1).
 *
 * Tarif melekat pada faktur, bukan pada aplikasi. Selama masih satu angka global, mengubahnya dari
 * 11 ke 12 ikut mengubah cetakan faktur lama yang aslinya 11 persen. Kolom ini nullable dengan arti
 * yang tegas: **null berarti faktur tidak mencantumkan pajak**, sehingga cetakan tidak memecah DPP
 * dan PPN sama sekali. Nilai bawaannya diambil dari pengaturan saat RO dibuat.
 *
 * Data lama dibiarkan null: tidak ada yang tahu faktur mana yang dulu mencantumkan pajak, dan
 * menebaknya dengan tarif hari ini justru mengarang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receive_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('ppn_rate')->nullable()->after('receive_date')
                ->comment('Tarif PPN faktur (%); null = faktur tidak mencantumkan pajak');
        });
    }

    public function down(): void
    {
        Schema::table('receive_orders', function (Blueprint $table) {
            $table->dropColumn('ppn_rate');
        });
    }
};
