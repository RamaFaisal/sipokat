<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dua perapian master obat sesuai permintaan apotek:
 *
 *  1. Kode obat tanpa strip: OBT-0001 → OBT0001. Nomor urut tidak berubah, hanya
 *     formatnya; Medicine::CODE_PREFIX ikut menjadi 'OBT'.
 *  2. Golongan "Obat Bebas Terbatas" dilebur ke "Obat Bebas". Obat (termasuk yang
 *     soft-deleted) di-remap dulu, baru kategorinya dihapus permanen — category_id
 *     memakai ON DELETE CASCADE, jadi urutan ini penting.
 *
 * down() mengembalikan format kode; kategori yang sudah dilebur tidak dipisah lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('medicines')
                ->where('code', 'like', 'OBT-%')
                ->orderBy('id')
                ->get(['id', 'code'])
                ->each(fn ($m) => DB::table('medicines')
                    ->where('id', $m->id)
                    ->update(['code' => 'OBT'.substr($m->code, 4)]));

            $target = DB::table('medicine_categories')->whereNull('deleted_at')->where('name', 'Obat Bebas')->value('id');
            $obsolete = DB::table('medicine_categories')->where('name', 'Obat Bebas Terbatas')->pluck('id');

            if ($target && $obsolete->isNotEmpty()) {
                DB::table('medicines')->whereIn('category_id', $obsolete)->update(['category_id' => $target]);
                DB::table('medicine_categories')->whereIn('id', $obsolete)->delete();
            }
        });
    }

    public function down(): void
    {
        DB::table('medicines')
            ->where('code', 'like', 'OBT%')
            ->where('code', 'not like', 'OBT-%')
            ->orderBy('id')
            ->get(['id', 'code'])
            ->each(fn ($m) => DB::table('medicines')
                ->where('id', $m->id)
                ->update(['code' => 'OBT-'.substr($m->code, 3)]));
    }
};
