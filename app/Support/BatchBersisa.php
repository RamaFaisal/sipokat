<?php

namespace App\Support;

use App\Models\MedicineStock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Batch yang masih bersisa, dikelompokkan per nomor batch.
 *
 * Kartu stok mencatat satu lapisan untuk tiap baris penerimaan, sehingga batch yang dibeli dua kali
 * muncul dua kali. Itu benar sebagai buku besar (tiap lapisan punya faktur dan harga belinya
 * sendiri, dan batas koreksi R8 dihitung per lapisan), tetapi salah sebagai jawaban atas pertanyaan
 * "batch mana yang akan kedaluwarsa": di rak hanya ada satu tumpukan.
 *
 * Kelas ini menyatukan tampilannya tanpa menyentuh ledger. Alokasi FEFO, HPP rata-rata bergerak, dan
 * kartu stok tetap bekerja per lapisan.
 *
 * Kunci pengelompokan adalah **nomor batch**, bukan tanggal kedaluwarsa. Dua batch berbeda bisa
 * kebetulan kedaluwarsa pada bulan yang sama (pada data riil 2026-09-29: RECO TM), dan menyatukannya
 * akan menghapus identitas yang dipakai saat retur ke PBF atau pemusnahan.
 */
class BatchBersisa
{
    /**
     * Query batch bersisa beserta sisa dan nilainya, satu baris per batch.
     *
     * Pengelompokannya dibungkus sebagai subquery, lalu dibaca seperti tabel biasa. Tanpa itu,
     * MySQL ber-`ONLY_FULL_GROUP_BY` menolak query Filament: tabel Filament menambahkan
     * `order by medicine_stocks.id` sebagai pemecah seri, dan kolom itu tidak ada di GROUP BY.
     * Dengan subquery, `id` menjadi kolom biasa milik tabel turunan, sehingga pengurutan,
     * penyaringan, dan penghitungan halaman bekerja apa adanya.
     *
     * SQLite tidak seketat itu, jadi kesalahan seperti ini tidak akan tertangkap suite tes yang
     * berjalan di memori; verifikasinya harus dijalankan ke MySQL.
     *
     * Kolom hasil: `id` (id lapisan terkecil, dipakai Filament sebagai kunci baris), `medicine_id`,
     * `batch_number`, `expired_date`, `sisa`, `nilai`, dan `jumlah_lapisan`.
     */
    public static function query(): Builder
    {
        return MedicineStock::query()->fromSub(self::kelompok(), 'medicine_stocks');
    }

    /** Agregat mentahnya, sebelum dibungkus jadi tabel turunan. */
    private static function kelompok(): QueryBuilder
    {
        $sisaLapisan = 'medicine_stocks.qty - coalesce((select sum(c.qty) from medicine_stocks c'
            .' where c.layer_stock_id = medicine_stocks.id), 0)';

        return DB::table('medicine_stocks')
            ->where('medicine_stocks.type_account', 'D')
            ->whereNotNull('medicine_stocks.expired_date')
            ->whereExists(fn (QueryBuilder $q) => $q->select(DB::raw(1))
                ->from('medicines')
                ->whereColumn('medicines.id', 'medicine_stocks.medicine_id')
                ->where('medicines.status', 'active')
                ->whereNull('medicines.deleted_at'))
            ->selectRaw('min(medicine_stocks.id) as id')
            ->selectRaw('medicine_stocks.medicine_id')
            ->selectRaw('medicine_stocks.batch_number')
            // Bila satu batch sempat tercatat dengan dua ED berbeda (salah ketik), yang ditampilkan
            // adalah yang paling dekat, karena itu peringatan yang lebih aman.
            ->selectRaw('min(medicine_stocks.expired_date) as expired_date')
            ->selectRaw("sum({$sisaLapisan}) as sisa")
            // Nilai memakai HPP tiap lapisan, tidak mengasumsikan harga kedua faktur sama.
            ->selectRaw("sum(({$sisaLapisan}) * medicine_stocks.hpp) as nilai")
            ->selectRaw('count(*) as jumlah_lapisan')
            ->groupBy('medicine_stocks.medicine_id', 'medicine_stocks.batch_number')
            ->havingRaw('sisa > 0');
    }

    /**
     * Satu baris per **obat**, memakai ED terdekat di antara batch yang masih bersisa.
     *
     * Dipakai widget dashboard yang kolomnya hanya nama obat dan sisa hari. Tanpa kolom batch,
     * baris per batch menjadi ambigu: dua batch berbeda milik obat yang sama bisa kedaluwarsa pada
     * bulan yang sama (pada data riil: RECO TM), sehingga muncul dua baris yang benar-benar
     * identik. Rincian per batch tetap ada di Laporan Akan Kedaluwarsa dan Kartu Stok.
     */
    public static function perObat(): Builder
    {
        $perObat = DB::query()
            ->fromSub(self::kelompok(), 'batch')
            ->selectRaw('min(batch.id) as id')
            ->selectRaw('batch.medicine_id')
            ->selectRaw('min(batch.expired_date) as expired_date')
            ->selectRaw('sum(batch.sisa) as sisa')
            ->selectRaw('sum(batch.nilai) as nilai')
            ->selectRaw('count(*) as jumlah_batch')
            ->groupBy('batch.medicine_id');

        return MedicineStock::query()->fromSub($perObat, 'medicine_stocks');
    }

    /** Sisa hari menuju ED; negatif berarti sudah kedaluwarsa. */
    public static function sisaHari(MedicineStock $batch): int
    {
        return (int) today()->diffInDays($batch->expired_date->startOfDay(), false);
    }
}
