<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Paginator dengan barisan nomor halaman yang pendek dan lebarnya tetap.
 *
 * `onEachSide` bawaan Laravel tidak bisa menghasilkan bentuk ini. Tiga hal terkunci di dalam
 * `Illuminate\Pagination\UrlWindow`: ujung kiri dan kanan selalu dua nomor, cabang "terlalu dekat
 * awal" mencetak `onEachSide + 4 + onEachSide` nomor sekaligus, dan `onEachSide` 0 memang memendekkan
 * tapi ikut menghapus tetangga halaman yang sedang dibuka. Untuk 13 halaman, setelan terbaiknya masih
 * menghasilkan `1 2 3 4 5 6 ... 12 13`.
 *
 * Aturan di sini: halaman pertama dan terakhir selalu bisa diklik, tiga nomor di sekitar halaman yang
 * sedang dibuka, titik-titik hanya bila ada nomor yang benar-benar dilompati.
 *
 *     di halaman 1  : 1 2 3 ... 13
 *     di halaman 6  : 1 ... 5 6 7 ... 13
 *     di halaman 13 : 1 ... 11 12 13
 */
class PaginatorRingkas extends LengthAwarePaginator
{
    /** Nomor di kiri dan kanan halaman yang sedang dibuka. */
    private const TETANGGA = 1;

    /** Selama halamannya tidak lebih dari ini, seluruh nomor dicetak tanpa titik-titik. */
    private const TANPA_PERSINGKAT = 7;

    /**
     * @return array<int, array<int, string>|string>
     */
    protected function elements(): array
    {
        $terakhir = $this->lastPage();

        if ($terakhir <= self::TANPA_PERSINGKAT) {
            return [$this->getUrlRange(1, $terakhir)];
        }

        $lebar = (self::TETANGGA * 2) + 1;

        // Jendela digeser di kedua ujung supaya jumlah nomornya tetap sama di halaman mana pun.
        $mulai = max(1, min($this->currentPage() - self::TETANGGA, $terakhir - $lebar + 1));
        $akhir = min($terakhir, max($this->currentPage() + self::TETANGGA, $lebar));

        $elemen = [];

        if ($mulai > 1) {
            $elemen[] = $this->getUrlRange(1, 1);

            // Titik-titik hanya berarti bila ada yang dilompati; loncatan satu nomor ditulis apa adanya.
            if ($mulai > 2) {
                $elemen[] = '...';
            }
        }

        $elemen[] = $this->getUrlRange($mulai, $akhir);

        if ($akhir < $terakhir) {
            if ($akhir < $terakhir - 1) {
                $elemen[] = '...';
            }

            $elemen[] = $this->getUrlRange($terakhir, $terakhir);
        }

        return $elemen;
    }
}
