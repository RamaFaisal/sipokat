<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom PBF disamakan dengan daftar alamat PBF Dinkes (PBF ID, Nama, Alamat, Telp, Fax):
 * tambah `fax`, buang `email` dan `pic` yang tidak ada di daftar dan tidak dipakai apotek.
 * down() mengembalikan kolom, bukan isinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('fax')->nullable()->after('phone');
            $table->dropColumn(['email', 'pic']);
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('fax');
            $table->string('email')->nullable()->after('phone');
            $table->string('pic')->nullable()->after('address');
        });
    }
};
