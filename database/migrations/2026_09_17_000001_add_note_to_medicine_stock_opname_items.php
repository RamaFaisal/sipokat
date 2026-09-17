<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Keterangan per batch pada opname: alasan selisih (rusak, kedaluwarsa dimusnahkan, retur PBF, salah hitung). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicine_stock_opname_items', function (Blueprint $table) {
            $table->string('note', 200)->nullable()->after('hpp');
        });
    }

    public function down(): void
    {
        Schema::table('medicine_stock_opname_items', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
