<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Nomor dokumen transaksi: PREFIKS + YYYYMMDD + urutan empat digit, tanpa pemisah.
 * Contoh: PO202609280001, RO202609280001, ORD202609280001, OPM202609280001.
 *
 * Sebelum 2026-09-28 tiap dokumen punya generatornya sendiri dan formatnya menyimpang satu sama
 * lain (PO dan RO memakai dash sebelum urutan, penjualan memakai dash sesudah prefiks, opname tidak
 * memuat tanggal sama sekali). Semua memanggil helper ini sekarang supaya tidak menyimpang lagi.
 *
 * Baca langsung ke tabel lewat query builder, bukan model, supaya baris yang sudah soft-deleted ikut
 * terhitung: nomor yang pernah dipakai tidak boleh terpakai dua kali.
 */
class DocumentNumber
{
    /** Panjang urutan harian. 9999 dokumen sehari jauh di atas kebutuhan apotek. */
    public const SEQUENCE_LENGTH = 4;

    public static function next(
        string $prefix,
        string $table,
        string $column,
        DateTimeInterface|string|null $date = null,
    ): string {
        $head = self::head($prefix, $date);

        $last = DB::table($table)
            ->where($column, 'like', $head.'%')
            ->orderByDesc($column)
            ->value($column);

        return $head.self::pad(self::sequenceOf($last, $head) + 1);
    }

    /** Prefiks + tanggal, yaitu bagian nomor yang sama untuk semua dokumen sejenis di hari itu. */
    public static function head(string $prefix, DateTimeInterface|string|null $date = null): string
    {
        $carbon = match (true) {
            $date instanceof DateTimeInterface => Carbon::instance($date),
            is_string($date) => Carbon::parse($date),
            default => Carbon::now(),
        };

        return $prefix.$carbon->format('Ymd');
    }

    public static function pad(int $sequence): string
    {
        return str_pad((string) $sequence, self::SEQUENCE_LENGTH, '0', STR_PAD_LEFT);
    }

    /** Urutan dari sebuah nomor; 0 bila nomornya kosong atau tidak berpola (mis. data demo). */
    private static function sequenceOf(?string $number, string $head): int
    {
        if ($number === null || ! str_starts_with($number, $head)) {
            return 0;
        }

        return (int) substr($number, strlen($head));
    }
}
