<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Satuan obat diganti dari bentuk sediaan (Tablet/Kapsul/Sirup/Botol/Salep)
 * menjadi satuan kemasan yang dipakai apotek: Pcs, Strip, Flask, Sachet, Box,
 * Tube.
 *
 *  1. Pastikan satuan final ada.
 *  2. Remap setiap obat dari satuan lama ke satuan final.
 *  3. Hapus satuan lama.
 *  4. Tulis ulang segmen satuan pada medicines.code supaya cocok dengan alias
 *     satuan baru (format {APP}/{NAMA+DOSIS}/{KATEGORI}/{SATUAN}/{URUT}).
 *
 * PENTING - medicines.unit_id memakai ON DELETE CASCADE. Menghapus satuan lama
 * tanpa me-remap obatnya lebih dulu akan ikut menghapus obatnya. Urutan di up()
 * sengaja: remap dulu, baru hapus.
 *
 * down() tidak bisa memulihkan satuan lama maupun segmen kode lamanya - keduanya
 * tidak tersimpan di mana pun setelah satuan lama dihapus.
 */
return new class extends Migration
{
    /** Satuan final beserta aliasnya (alias dipakai segmen kode obat). */
    private const FINAL_UNITS = [
        'Pcs' => 'PCS',
        'Strip' => 'STR',
        'Flask' => 'FLS',
        'Sachet' => 'SCH',
        'Box' => 'BOX',
        'Tube' => 'TUB',
    ];

    /**
     * Pemetaan satuan lama (lowercase) -> satuan final.
     * Sediaan padat dijual per strip, cairan per flask, semisolid per tube.
     * Satuan lama di luar daftar ini jatuh ke DEFAULT_UNIT.
     */
    private const UNIT_REMAP = [
        'tablet' => 'Strip',
        'kapsul' => 'Strip',
        'sirup' => 'Flask',
        'botol' => 'Flask',
        'salep' => 'Tube',
    ];

    private const DEFAULT_UNIT = 'Pcs';

    public function up(): void
    {
        DB::transaction(function () {
            $finalIds = $this->ensureFinalUnits();

            // Sengaja pakai query builder supaya baris soft-deleted ikut ter-remap;
            // kalau tertinggal, baris itu akan terhapus permanen oleh cascade.
            $obsolete = DB::table('units')
                ->whereNotIn('id', array_values($finalIds))
                ->get(['id', 'name']);

            foreach ($obsolete as $unit) {
                $targetName = self::UNIT_REMAP[strtolower($unit->name)] ?? self::DEFAULT_UNIT;

                DB::table('medicines')
                    ->where('unit_id', $unit->id)
                    ->update([
                        'unit_id' => $finalIds[$targetName],
                        'updated_at' => now(),
                    ]);
            }

            // Aman dihapus: tidak ada lagi obat yang menunjuk ke sini.
            DB::table('units')
                ->whereNotIn('id', array_values($finalIds))
                ->delete();

            $this->resyncMedicineCodes();
        });
    }

    public function down(): void
    {
        // Tidak dapat dibalik: satuan lama dan segmen kode lamanya tidak
        // tersimpan di mana pun setelah satuan lama dihapus.
    }

    /**
     * @return array<string,int> nama satuan final -> id
     */
    private function ensureFinalUnits(): array
    {
        $ids = [];

        foreach (self::FINAL_UNITS as $name => $alias) {
            $existing = DB::table('units')->where('name', $name)->first(['id']);

            if ($existing) {
                DB::table('units')->where('id', $existing->id)->update([
                    'alias' => $alias,
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
                $ids[$name] = $existing->id;

                continue;
            }

            $ids[$name] = DB::table('units')->insertGetId([
                'name' => $name,
                'alias' => $alias,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    /**
     * Tulis ulang segmen satuan pada kode obat. Nomor urut dihitung ulang per
     * basis kode karena penggabungan satuan (Tablet+Kapsul -> Strip,
     * Sirup+Botol -> Flask) bisa membuat dua obat bertemu di kode yang sama.
     */
    private function resyncMedicineCodes(): void
    {
        $rows = DB::table('medicines')
            ->join('units', 'units.id', '=', 'medicines.unit_id')
            ->orderBy('medicines.id')
            ->get([
                'medicines.id',
                'medicines.code',
                'units.alias',
                'units.name as unit_name',
            ]);

        $byBase = [];

        foreach ($rows as $row) {
            $segments = explode('/', $row->code);

            // Format tak terduga - biarkan utuh daripada merusaknya.
            if (count($segments) !== 5) {
                continue;
            }

            $alias = $row->alias ?: strtoupper(substr($row->unit_name, 0, 3));
            $base = $segments[0].'/'.$segments[1].'/'.$segments[2].'/'.strtoupper($alias);

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
    }
};
