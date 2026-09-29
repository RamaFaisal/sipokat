<?php

namespace App\Support;

use App\Filament\Pages\LaporanKedaluwarsa;
use App\Filament\Pages\LaporanMoving;
use App\Filament\Pages\LaporanOpname;
use App\Filament\Pages\LaporanPembelianPbf;
use App\Filament\Pages\LaporanRekap;
use App\Filament\Pages\LaporanStokObat;

/**
 * Daftar tab menu Laporan (K7).
 *
 * Sebelumnya tiap laporan berdiri sendiri di grup navigasi "Laporan", sehingga menambah laporan
 * berarti menambah baris di sidebar. Sekarang hanya satu entri sidebar; sisanya dicapai lewat tab.
 *
 * Tiap tab tetap halaman tersendiri, bukan satu halaman raksasa: dua laporan lama (Rekap dan
 * Fast/Slow Moving) sudah matang beserta ekspornya, dan menggabungkan isinya hanya menambah risiko
 * tanpa menambah manfaat.
 */
class MenuLaporan
{
    /** @return array<class-string, string> kelas halaman => label tab */
    public static function tabs(): array
    {
        return [
            LaporanRekap::class => 'Rekap Penjualan & Pembelian',
            LaporanMoving::class => 'Fast / Slow Moving',
            LaporanKedaluwarsa::class => 'Akan Kedaluwarsa',
            LaporanStokObat::class => 'Rekap Stok per Obat',
            LaporanPembelianPbf::class => 'Pembelian per PBF',
            LaporanOpname::class => 'Hasil Stok Opname',
        ];
    }

    /**
     * Tab yang boleh dilihat pengguna saat ini. Tiap halaman laporan memakai HasPageShield,
     * jadi tab yang tidak boleh dibuka tidak perlu ditampilkan.
     *
     * @return array<int, array{label: string, url: string, aktif: bool}>
     */
    public static function untukPengguna(string $halamanAktif): array
    {
        $tabs = [];

        foreach (self::tabs() as $kelas => $label) {
            if (! $kelas::canAccess()) {
                continue;
            }

            $tabs[] = [
                'label' => $label,
                'url' => $kelas::getUrl(),
                'aktif' => $kelas === $halamanAktif,
            ];
        }

        return $tabs;
    }
}
