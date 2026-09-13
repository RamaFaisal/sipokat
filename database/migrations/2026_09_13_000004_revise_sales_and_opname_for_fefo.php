<?php

use App\Services\StockMovementService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penjualan ramping + stok per lapisan (rencana-revisi-2026-09 Bagian 4 form & Bagian 5, tahap E4).
 *
 * - orders: status & no_payment dihapus (S1, S2); order_items: discount & total dihapus (S3, D3).
 * - medicine_stock_opname_items: menyebut lapisan yang dikoreksi (layer_stock_id) atau batch+ED
 *   baru (F3, Q3).
 * - F6: seluruh baris C lama diatribusikan ke lapisan secara FEFO (urut ED naik, lapisan tanpa ED
 *   paling dulu), dipecah bila melintasi dua lapisan.
 *
 * down() sengaja kosong — pemulihan lewat backup DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_no_payment_unique');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['status', 'no_payment']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('total'); // discount sudah dihapus migration 2026_05_10
        });

        Schema::table('medicine_stock_opname_items', function (Blueprint $table) {
            $table->foreignId('layer_stock_id')->nullable()->after('medicine_id')
                ->constrained('medicine_stocks')->nullOnDelete();
            $table->string('batch_number')->nullable()->after('layer_stock_id');
            $table->date('expired_date')->nullable()->after('batch_number');
        });

        // F6: atribusi FEFO untuk baris C lama, lalu segarkan status stok (kini berbasis stok tersedia, B4).
        $service = app(StockMovementService::class);
        $medicineIds = DB::table('medicine_stocks')->distinct()->pluck('medicine_id');
        foreach ($medicineIds as $medicineId) {
            $service->backfillLayers((int) $medicineId);
        }
        $service->refreshStockStatus($medicineIds->map(fn ($id) => (int) $id)->all());
    }

    public function down(): void
    {
        // Sengaja kosong — lihat docblock.
    }
};
