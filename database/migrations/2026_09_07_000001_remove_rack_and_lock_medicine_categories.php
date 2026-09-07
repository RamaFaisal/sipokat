<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master data dirampingkan:
 *
 *  1. Rak Obat dihapus tuntas (kolom medicines.rack_id + tabel medicine_racks).
 *  2. Kategori Obat tidak lagi dikelola lewat CRUD. Isinya dikunci jadi dua
 *     baris saja: "Obat Bebas" (OBB) dan "Obat Keras" (OBK).
 *
 * PENTING - medicines.category_id dan medicines.rack_id keduanya memakai
 * ON DELETE CASCADE. Menghapus kategori lama tanpa me-remap obatnya lebih dulu
 * akan ikut menghapus obatnya. Urutan di up() sengaja: remap dulu, baru hapus.
 *
 * down() mengembalikan struktur, BUKAN data. Isi tabel medicine_racks dan
 * kategori lama tidak bisa dipulihkan tanpa backup database.
 */
return new class extends Migration
{
    /** Kategori final beserta aliasnya (alias dipakai segmen kode obat). */
    private const FINAL_CATEGORIES = [
        'Obat Bebas' => 'OBB',
        'Obat Keras' => 'OBK',
    ];

    /**
     * Pemetaan kategori lama (lowercase) -> kategori final.
     * Antibiotik masuk golongan obat keras karena penyerahannya harus dengan
     * resep dokter. Kategori lama di luar daftar ini jatuh ke DEFAULT_CATEGORY.
     */
    private const CATEGORY_REMAP = [
        'antibiotik' => 'Obat Keras',
        'analisik'   => 'Obat Bebas',
        'analgesik'  => 'Obat Bebas',
        'antiseptik' => 'Obat Bebas',
        'vitamin'    => 'Obat Bebas',
    ];

    private const DEFAULT_CATEGORY = 'Obat Bebas';

    public function up(): void
    {
        DB::transaction(function () {
            $finalIds = $this->ensureFinalCategories();

            // Remap setiap obat dari kategori lama ke kategori final.
            // Sengaja pakai query builder supaya baris soft-deleted ikut ter-remap;
            // kalau tertinggal, baris itu akan terhapus permanen oleh cascade.
            $obsolete = DB::table('medicine_categories')
                ->whereNotIn('id', array_values($finalIds))
                ->get(['id', 'name']);

            foreach ($obsolete as $category) {
                $targetName = self::CATEGORY_REMAP[strtolower($category->name)] ?? self::DEFAULT_CATEGORY;

                DB::table('medicines')
                    ->where('category_id', $category->id)
                    ->update([
                        'category_id' => $finalIds[$targetName],
                        'updated_at' => now(),
                    ]);
            }

            // Aman dihapus: tidak ada lagi obat yang menunjuk ke sini.
            DB::table('medicine_categories')
                ->whereNotIn('id', array_values($finalIds))
                ->delete();
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->dropForeign(['rack_id']);
            $table->dropColumn('rack_id');
        });

        Schema::dropIfExists('medicine_racks');
    }

    public function down(): void
    {
        Schema::create('medicine_racks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Nullable (aslinya NOT NULL) supaya rollback tidak gagal pada baris
        // medicines yang sudah ada - tidak ada rak yang bisa ditunjuk lagi.
        Schema::table('medicines', function (Blueprint $table) {
            $table->foreignId('rack_id')
                ->nullable()
                ->after('unit_id')
                ->constrained('medicine_racks')
                ->cascadeOnDelete();
        });
    }

    /**
     * Pastikan dua kategori final ada dan aliasnya benar. Idempotent, dan
     * memakai query builder supaya tidak bergantung pada $fillable model.
     *
     * @return array<string, int> nama kategori => id
     */
    private function ensureFinalCategories(): array
    {
        $ids = [];

        foreach (self::FINAL_CATEGORIES as $name => $alias) {
            $existing = DB::table('medicine_categories')
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->first();

            if ($existing) {
                DB::table('medicine_categories')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $name,
                        'alias' => $alias,
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);

                $ids[$name] = $existing->id;

                continue;
            }

            $ids[$name] = DB::table('medicine_categories')->insertGetId([
                'name' => $name,
                'alias' => $alias,
                'description' => $name === 'Obat Keras'
                    ? 'Obat yang penyerahannya harus dengan resep dokter'
                    : 'Obat yang dapat dibeli bebas tanpa resep dokter',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }
};
