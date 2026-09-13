<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perampingan master obat (rencana-revisi-2026-09 Bagian 1).
 *
 * - dosage digabung ke name lalu dihapus (M6); photo, description, purchase_price,
 *   sale_price dihapus (M4, M5, M8, M12) — rupiah hidup di transaksi, bukan di master.
 * - pack_unit_id + pack_size (kemasan pembelian) ditambah NOT NULL (M3, B8).
 * - min_stock wajib > 0, bawaan Strip 20 / lainnya pack_size (M9, B6, B7).
 * - code dinomori ulang OBT-#### urut id, termasuk yang soft-deleted (M7).
 *
 * down() sengaja kosong: dosage, harga, dan kode lama tidak tersimpan di mana pun
 * setelah dihapus — pemulihan lewat backup DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->foreignId('pack_unit_id')->nullable()->after('unit_id')->constrained('units')->restrictOnDelete();
            $table->unsignedInteger('pack_size')->nullable()->after('pack_unit_id');
        });

        // 1. Gabungkan dosis ke nama supaya keunikan (name, dosage) lama tidak runtuh
        //    saat kolom dosage hilang. Nama sudah uppercase; dosis ikut di-uppercase.
        $rows = DB::table('medicines')->select('id', 'name', 'dosage')->get();
        foreach ($rows as $row) {
            $dosage = trim((string) $row->dosage);
            if ($dosage === '') {
                continue;
            }
            $name = strtoupper(trim(preg_replace('/\s+/', ' ', $row->name)));
            $dosageUp = strtoupper($dosage);
            if (! str_contains($name, $dosageUp)) {
                $name .= ' '.$dosageUp;
            }
            DB::table('medicines')->where('id', $row->id)->update(['name' => $name]);
        }

        // 2. Backfill kemasan: data lama dianggap dibeli dalam satuan jualnya sendiri.
        DB::table('medicines')->whereNull('pack_unit_id')->update(['pack_unit_id' => DB::raw('unit_id')]);
        DB::table('medicines')->whereNull('pack_size')->update(['pack_size' => 1]);

        // 3. Backfill min_stock = 0 → bawaan per satuan (Strip 20, lainnya pack_size).
        $stripId = DB::table('units')->whereRaw('LOWER(name) = ?', ['strip'])->value('id');
        if ($stripId) {
            DB::table('medicines')->where('min_stock', '<=', 0)->where('unit_id', $stripId)->update(['min_stock' => 20]);
        }
        DB::table('medicines')->where('min_stock', '<=', 0)->update(['min_stock' => DB::raw('pack_size')]);

        // 4. Nomori ulang kode: OBT-0001 … urut id, termasuk soft-deleted agar nomor unik.
        //    Dua fase (parkir dulu) supaya unique index code tidak terlanggar di tengah jalan.
        $ids = DB::table('medicines')->orderBy('id')->pluck('id');
        foreach ($ids as $i => $id) {
            DB::table('medicines')->where('id', $id)->update(['code' => '__TMP__'.$id]);
        }
        foreach ($ids as $i => $id) {
            DB::table('medicines')->where('id', $id)->update(['code' => sprintf('OBT-%04d', $i + 1)]);
        }

        Schema::table('medicines', function (Blueprint $table) {
            $table->foreignId('pack_unit_id')->nullable(false)->change();
            $table->unsignedInteger('pack_size')->nullable(false)->change();
            $table->dropColumn(['dosage', 'photo', 'description', 'purchase_price', 'sale_price']);
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->index('name');
        });
    }

    public function down(): void
    {
        // Sengaja kosong — lihat docblock.
    }
};
