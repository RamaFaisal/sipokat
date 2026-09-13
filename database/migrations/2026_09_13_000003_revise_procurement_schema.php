<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengadaan ramping (rencana-revisi-2026-09 Bagian 2 & 3, tahap E3).
 *
 * PO = catatan internal per PBF pasca-konfirmasi WA (P1): kolom biaya/pajak/ongkir/pembayaran/
 * status alur dihapus (P3, §3.2). RO = satu faktur PBF (R10): nomor faktur ditambah,
 * status/keterangan/late_arrival dihapus (R5). Baris PO dan RO menyimpan qty & price dalam
 * satuan jual plus jejak konversi kemasan (R6, P4). manufacture_date dihapus (R2).
 *
 * down() sengaja kosong — pemulihan lewat backup DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- purchase_orders -------------------------------------------------
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn([
                'sub_total', 'discount', 'tax', 'total_tax', 'shipping_cost', 'other_cost',
                'grand_total', 'estimated_arrival', 'status_payment', 'description', 'status',
            ]);
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('status_receive_order', ['pending', 'partial', 'received', 'closed'])
                ->default('pending')->change();
        });

        // --- purchase_order_items --------------------------------------------
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('pack_unit_id')->nullable()->after('medicine_id')->constrained('units')->restrictOnDelete();
            $table->unsignedInteger('pack_size')->nullable()->after('pack_unit_id');
            $table->unsignedInteger('pack_qty')->nullable()->after('pack_size');
        });
        $this->backfillPackTrail('purchase_order_items');
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('pack_unit_id')->nullable(false)->change();
            $table->unsignedInteger('pack_size')->nullable(false)->change();
            $table->unsignedInteger('pack_qty')->nullable(false)->change();
            $table->dropColumn(['description', 'discount', 'total']);
        });

        // --- receive_orders ---------------------------------------------------
        Schema::table('receive_orders', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->after('receive_order_number');
        });
        // Data lama tidak punya nomor faktur: pakai nomor RO sebagai placeholder yang unik.
        DB::table('receive_orders')->whereNull('invoice_number')->update(['invoice_number' => DB::raw('receive_order_number')]);
        Schema::table('receive_orders', function (Blueprint $table) {
            $table->string('invoice_number')->nullable(false)->change();
            $table->index(['supplier_id', 'invoice_number']);
            $table->dropColumn(['description', 'late_arrival', 'status']);
        });

        // --- receive_order_items ---------------------------------------------
        Schema::table('receive_order_items', function (Blueprint $table) {
            $table->foreignId('pack_unit_id')->nullable()->after('medicine_id')->constrained('units')->restrictOnDelete();
            $table->unsignedInteger('pack_size')->nullable()->after('pack_unit_id');
            $table->unsignedInteger('pack_qty')->nullable()->after('pack_size');
        });
        $this->backfillPackTrail('receive_order_items');
        Schema::table('receive_order_items', function (Blueprint $table) {
            $table->foreignId('pack_unit_id')->nullable(false)->change();
            $table->unsignedInteger('pack_size')->nullable(false)->change();
            $table->unsignedInteger('pack_qty')->nullable(false)->change();
            $table->date('expired_date')->nullable(false)->change();
            $table->dropColumn('manufacture_date');
        });
    }

    /** Baris lama dianggap dibeli dalam satuan jualnya sendiri: kemasan = satuan jual, isi 1, jumlah kemasan = qty. */
    private function backfillPackTrail(string $table): void
    {
        $rows = DB::table($table)
            ->join('medicines', 'medicines.id', '=', "{$table}.medicine_id")
            ->whereNull("{$table}.pack_unit_id")
            ->select(["{$table}.id", "{$table}.qty", 'medicines.unit_id'])
            ->get();

        foreach ($rows as $row) {
            DB::table($table)->where('id', $row->id)->update([
                'pack_unit_id' => $row->unit_id,
                'pack_size' => 1,
                'pack_qty' => $row->qty,
            ]);
        }
    }

    public function down(): void
    {
        // Sengaja kosong — lihat docblock.
    }
};
