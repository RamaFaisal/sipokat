<?php

namespace App\Support;

use App\Models\MedicineStock;
use Illuminate\Database\Eloquent\Builder;

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
     * Kolom hasil: `id` (id lapisan terkecil, dipakai Filament sebagai kunci baris), `medicine_id`,
     * `batch_number`, `expired_date`, `sisa`, `nilai`, dan `jumlah_lapisan`.
     */
    public static function query(): Builder
    {
        $sisaLapisan = 'medicine_stocks.qty - coalesce((select sum(c.qty) from medicine_stocks c'
            .' where c.layer_stock_id = medicine_stocks.id), 0)';

        return MedicineStock::layers()
            ->whereNotNull('expired_date')
            ->whereHas('medicine', fn (Builder $q) => $q->where('status', 'active'))
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

    /** Sisa hari menuju ED; negatif berarti sudah kedaluwarsa. */
    public static function sisaHari(MedicineStock $batch): int
    {
        return (int) today()->diffInDays($batch->expired_date->startOfDay(), false);
    }
}
