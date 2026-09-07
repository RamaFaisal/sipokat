<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyelaraskan segmen kategori pada medicines.code dengan alias kategori baru.
 *
 * Kode obat berformat {APP}/{NAMA+DOSIS}/{KATEGORI}/{SATUAN}/{URUT}. Setelah
 * kategori dikunci jadi Obat Bebas/Obat Keras, segmen ketiga masih menyimpan
 * singkatan kategori lama (ANA/ANT/VIT) sehingga tidak lagi cocok dengan
 * kategori obatnya. Migration ini menulis ulang segmen itu jadi OBB/OBK.
 *
 * Hanya segmen kategori yang disentuh; nama, dosis, dan satuan dibiarkan apa
 * adanya supaya kode tetap dikenali. Nomor urut dihitung ulang per basis kode
 * karena penggabungan kategori bisa membuat dua obat bertemu di kode yang sama
 * (contoh nyata: dua CIPROFLOXACIN 1000 mg yang dulu beda kategori).
 *
 * down() tidak bisa memulihkan singkatan kategori lama - kategorinya sendiri
 * sudah dihapus oleh migration sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('medicines')
            ->join('medicine_categories', 'medicine_categories.id', '=', 'medicines.category_id')
            ->orderBy('medicines.id')
            ->get([
                'medicines.id',
                'medicines.code',
                'medicine_categories.alias',
                'medicine_categories.name as category_name',
            ]);

        // Susun kode baru, kelompokkan per basis untuk penomoran ulang.
        $byBase = [];

        foreach ($rows as $row) {
            $segments = explode('/', $row->code);

            // Format tak terduga - biarkan utuh daripada merusaknya.
            if (count($segments) !== 5) {
                continue;
            }

            $alias = $row->alias ?: strtoupper(substr($row->category_name, 0, 3));
            $base = $segments[0].'/'.$segments[1].'/'.strtoupper($alias).'/'.$segments[3];

            $byBase[$base][] = [
                'id' => $row->id,
                'seq' => (int) $segments[4],
            ];
        }

        $updates = [];

        foreach ($byBase as $base => $members) {
            // Urutkan pakai nomor lama dulu supaya kode yang tidak bertabrakan
            // mempertahankan nomornya; id sebagai pemecah imbang yang stabil.
            usort($members, function (array $a, array $b) {
                return [$a['seq'], $a['id']] <=> [$b['seq'], $b['id']];
            });

            foreach ($members as $index => $member) {
                $updates[$member['id']] = $base.'/'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            }
        }

        DB::transaction(function () use ($updates) {
            // Dua fase: parkir di nilai sementara lebih dulu supaya unique index
            // pada code tidak terlanggar di tengah proses penulisan ulang.
            foreach (array_keys($updates) as $id) {
                DB::table('medicines')->where('id', $id)->update([
                    'code' => '~MIGRASI~'.$id,
                ]);
            }

            foreach ($updates as $id => $code) {
                DB::table('medicines')->where('id', $id)->update([
                    'code' => $code,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Tidak dapat dibalik: singkatan kategori lama (ANA/ANT/VIT) tidak
        // tersimpan di mana pun setelah kategori lama dihapus.
    }
};
