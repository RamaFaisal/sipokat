<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Harga per kemasan faktur disimpan apa adanya, bukan dihitung balik dari price.
 *
 * price (per satuan jual) tersimpan 2 desimal, jadi price x isi bisa meleset dari nomor faktur
 * asli saat isi kemasan tidak habis dibagi harga. Contoh nyata: 50 Box isi 12 @ Rp8.000 ditulis
 * price 666,67 (8.000 / 12 dibulatkan); price x isi x jumlah kemasan menghasilkan Rp405.102,
 * padahal fakturnya Rp405.100. Kolom ini menjaga harga faktur bertahan lewat pembagian itu.
 *
 * Baris lama dibackfill dengan rumus yang sama seperti bila kolom ini sudah ada sejak awal
 * (round(price x pack_size)), supaya tidak ada baris yang kehilangan jejak harga fakturnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('pack_price', 15, 2)->nullable()->after('price');
        });
        Schema::table('receive_order_items', function (Blueprint $table) {
            $table->decimal('pack_price', 15, 2)->nullable()->after('price');
        });

        $this->backfill('purchase_order_items');
        $this->backfill('receive_order_items');
    }

    private function backfill(string $table): void
    {
        DB::table($table)->whereNull('pack_price')->orderBy('id')->get()->each(function ($row) use ($table) {
            $packSize = max(1, (int) $row->pack_size);
            DB::table($table)->where('id', $row->id)->update([
                'pack_price' => round((float) $row->price * $packSize),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('pack_price');
        });
        Schema::table('receive_order_items', function (Blueprint $table) {
            $table->dropColumn('pack_price');
        });
    }
};
