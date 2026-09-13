<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAW (rencana-revisi-2026-09 Bagian 7, tahap E5).
 *
 * - c1_raw kini rasio stok ÷ min_stock (K1); stok tersedia dan min_stock disimpan terpisah supaya
 *   tabel tetap menampilkan angka yang dikenal apoteker ("Stok 18 (min 20)").
 * - rank = tingkat padat (K9: nilai seri → nomor sama); sort_order = urutan tampil (tie-breaker).
 * - excluded_count = obat aktif tanpa riwayat kartu stok yang dikecualikan (K0).
 *
 * Snapshot lama tetap terbaca: kolom baru nullable/0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saw_calculation_results', function (Blueprint $table) {
            $table->integer('c1_stock')->nullable()->after('c1_raw');
            $table->unsignedInteger('c1_min_stock')->nullable()->after('c1_stock');
            $table->unsignedInteger('sort_order')->default(0)->after('rank');
            $table->index(['saw_calculation_id', 'sort_order']);
        });

        Schema::table('saw_calculations', function (Blueprint $table) {
            $table->unsignedInteger('excluded_count')->default(0)->after('total_alternatives');
        });
    }

    public function down(): void
    {
        Schema::table('saw_calculation_results', function (Blueprint $table) {
            $table->dropIndex(['saw_calculation_id', 'sort_order']);
            $table->dropColumn(['c1_stock', 'c1_min_stock', 'sort_order']);
        });
        Schema::table('saw_calculations', function (Blueprint $table) {
            $table->dropColumn('excluded_count');
        });
    }
};
