<?php

namespace App\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Judul widget dashboard yang bisa diklik menuju menu terkait.
 *
 * Filament merender judul tabel apa adanya bila bertipe `Htmlable`, jadi judul cukup dibungkus
 * tautan. Kalau pengguna tidak berhak membuka menu tujuannya, judulnya tetap teks biasa: tautan
 * yang berujung ke halaman 403 lebih membingungkan daripada tidak ada tautan sama sekali.
 */
class TautanWidget
{
    public static function judul(string $teks, ?string $url): string|Htmlable
    {
        if ($url === null) {
            return $teks;
        }

        return new HtmlString(sprintf(
            '<a href="%s" class="hover:underline" title="Buka menu terkait">%s</a>',
            e($url),
            e($teks),
        ));
    }
}
