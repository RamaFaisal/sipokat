<?php

use App\Services\StockMovementService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kartu stok menjadi sumber lapisan batch dan HPP (rencana-revisi-2026-09 Bagian 4, D1, C5, B5).
 *
 * - Baris D = lapisan: batch_number, expired_date, receive_order_item_id (jejak asal RO).
 * - Baris C menunjuk lapisan yang dikurangi lewat layer_stock_id (diisi mulai E4/FEFO).
 * - hpp_avg = HPP rata-rata bergerak sesudah baris itu (bilangan bulat rupiah, dibulatkan ke atas).
 * - B5: baris ledger milik dokumen yang sudah soft-deleted dihapus sungguhan; kolom deleted_at
 *   medicine_stocks dibuang — ledger tidak lagi disaring lewat whereHas ke dokumen induk.
 * - Q5: expired_date item RO dinormalkan ke tanggal 1 bulan ED (kedaluwarsa sejak awal bulan).
 *
 * down() sengaja kosong — pemulihan lewat backup DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicine_stocks', function (Blueprint $table) {
            $table->string('batch_number')->nullable()->after('type_account');
            $table->date('expired_date')->nullable()->after('batch_number');
            $table->foreignId('receive_order_item_id')->nullable()->after('receive_order_id')
                ->constrained('receive_order_items')->nullOnDelete();
            $table->foreignId('layer_stock_id')->nullable()->after('receive_order_item_id')
                ->constrained('medicine_stocks')->restrictOnDelete();
            $table->unsignedInteger('hpp_avg')->nullable()->after('hpp')
                ->comment('HPP rata-rata bergerak sesudah baris ini');
            $table->index(['medicine_id', 'expired_date']);
        });

        // B5: ledger yang dokumennya sudah dihapus tidak lagi disaring — hapus sungguhan.
        DB::table('medicine_stocks')->whereNotNull('deleted_at')->delete();
        DB::table('medicine_stocks')
            ->whereIn('receive_order_id', DB::table('receive_orders')->whereNotNull('deleted_at')->pluck('id'))
            ->delete();
        DB::table('medicine_stocks')
            ->whereIn('order_id', DB::table('orders')->whereNotNull('deleted_at')->pluck('id'))
            ->delete();
        DB::table('medicine_stocks')
            ->whereIn('medicine_stock_opname_id', DB::table('medicine_stock_opnames')->whereNotNull('deleted_at')->pluck('id'))
            ->delete();

        Schema::table('medicine_stocks', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Q5: ED disimpan sebagai tanggal 1 bulan ED.
        $items = DB::table('receive_order_items')->whereNotNull('expired_date')->select('id', 'expired_date')->get();
        foreach ($items as $item) {
            $firstOfMonth = substr((string) $item->expired_date, 0, 7).'-01';
            if ((string) $item->expired_date !== $firstOfMonth) {
                DB::table('receive_order_items')->where('id', $item->id)->update(['expired_date' => $firstOfMonth]);
            }
        }

        // Backfill lapisan pada baris D dari RO: cocokkan receive_order_id + medicine_id
        // (data lama: satu item per obat per RO). Batch/ED disalin (C5).
        $rows = DB::table('medicine_stocks')
            ->where('type_account', 'D')
            ->whereNotNull('receive_order_id')
            ->select('id', 'receive_order_id', 'medicine_id')
            ->get();
        foreach ($rows as $row) {
            $item = DB::table('receive_order_items')
                ->where('receive_order_id', $row->receive_order_id)
                ->where('medicine_id', $row->medicine_id)
                ->orderBy('id')
                ->first(['id', 'batch_number', 'expired_date']);
            if (! $item) {
                continue;
            }
            DB::table('medicine_stocks')->where('id', $row->id)->update([
                'receive_order_item_id' => $item->id,
                'batch_number' => $item->batch_number,
                'expired_date' => $item->expired_date,
            ]);
        }

        // Replay HPP rata-rata bergerak untuk semua obat yang punya ledger.
        $service = app(StockMovementService::class);
        foreach (DB::table('medicine_stocks')->distinct()->pluck('medicine_id') as $medicineId) {
            $service->replayHpp((int) $medicineId);
        }
    }

    public function down(): void
    {
        // Sengaja kosong — lihat docblock.
    }
};
