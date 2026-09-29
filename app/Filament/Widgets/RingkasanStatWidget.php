<?php

namespace App\Filament\Widgets;

use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\Order;
use App\Support\AmbangEd;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Empat angka ringkas dashboard, dengan markup dan kelas milik sendiri.
 *
 * Bukan `StatsOverviewWidget` bawaan. Di sana tata letaknya ditentukan variabel CSS
 * (`--cols-default`, `--cols-md`) beserta kelas `fi-grid-cols` milik paket, sehingga jumlah kolom
 * per lebar layar dan jarak antar kartu hanya bisa diubah dengan menimpa aturan paket dari luar.
 * Di sini gridnya ditulis langsung di blade sebagai kelas Tailwind biasa, jadi satu tempat saja
 * yang menentukan keduanya dan hasilnya bisa dibaca tanpa menelusuri CSS vendor.
 *
 * Nama kelas sengaja tidak diubah supaya izin Shield `View:RingkasanStatWidget` tetap berlaku.
 */
class RingkasanStatWidget extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.ringkasan-stat';

    /**
     * @return array<int, array{label: string, nilai: string, keterangan: string, ikon: string, warna: string}>
     */
    public function kartu(): array
    {
        return [
            $this->totalObat(),
            $this->stokKritis(),
            $this->mendekatiEd(),
            $this->penjualanBulanIni(),
        ];
    }

    /** @return array{label: string, nilai: string, keterangan: string, ikon: string, warna: string} */
    private function totalObat(): array
    {
        $jumlah = Medicine::where('status', 'active')->count();

        return [
            'label' => 'Total Obat Aktif',
            'nilai' => number_format($jumlah, 0, ',', '.'),
            'keterangan' => 'Jumlah obat aktif',
            'ikon' => 'heroicon-o-rectangle-stack',
            'warna' => 'primary',
        ];
    }

    /** @return array{label: string, nilai: string, keterangan: string, ikon: string, warna: string} */
    private function stokKritis(): array
    {
        $jumlah = Medicine::where('status', 'active')
            ->whereIn('stock_status', ['empty', 'almost_empty'])
            ->count();

        return [
            'label' => 'Obat Stok Kritis',
            'nilai' => number_format($jumlah, 0, ',', '.'),
            'keterangan' => 'Status stok habis atau menipis',
            'ikon' => 'heroicon-o-exclamation-triangle',
            'warna' => $jumlah > 0 ? 'danger' : 'success',
        ];
    }

    /** @return array{label: string, nilai: string, keterangan: string, ikon: string, warna: string} */
    private function mendekatiEd(): array
    {
        $hariIni = Carbon::now()->startOfDay();

        // F5: hanya lapisan (batch) yang masih bersisa, dihitung per obat.
        $jumlah = MedicineStock::layers()
            ->withRemainingStock()
            ->whereNotNull('expired_date')
            ->whereBetween('expired_date', [
                $hariIni->toDateString(),
                $hariIni->copy()->addDays(AmbangEd::PANTAU)->toDateString(),
            ])
            ->whereHas('medicine', fn ($q) => $q->where('status', 'active'))
            ->distinct('medicine_id')
            ->count('medicine_id');

        return [
            'label' => 'Obat Mendekati ED',
            'nilai' => number_format($jumlah, 0, ',', '.'),
            'keterangan' => 'Batch bersisa dengan ED ≤ '.AmbangEd::PANTAU.' hari',
            'ikon' => 'heroicon-o-clock',
            'warna' => $jumlah > 0 ? 'warning' : 'success',
        ];
    }

    /** @return array{label: string, nilai: string, keterangan: string, ikon: string, warna: string} */
    private function penjualanBulanIni(): array
    {
        $total = Order::query()
            ->whereDate('order_date', '>=', Carbon::now()->startOfMonth()->toDateString())
            ->whereDate('order_date', '<=', Carbon::now()->endOfMonth()->toDateString())
            ->sum('grand_total');

        return [
            'label' => 'Penjualan Bulan Ini',
            'nilai' => 'Rp '.number_format((float) $total, 0, ',', '.'),
            'keterangan' => Carbon::now()->translatedFormat('F Y'),
            'ikon' => 'heroicon-o-banknotes',
            'warna' => 'success',
        ];
    }

    /**
     * Kelas warna ditulis utuh, bukan dirangkai dari potongan.
     *
     * Tailwind memindai berkas ini dan hanya menghasilkan kelas yang tertulis lengkap; rangkaian
     * seperti "text-{$warna}-600" tidak akan pernah ikut terkompilasi dan warnanya hilang diam-diam.
     */
    public function kelasWarna(string $warna): string
    {
        return match ($warna) {
            'danger' => 'text-danger-600 dark:text-danger-400',
            'warning' => 'text-warning-600 dark:text-warning-400',
            'success' => 'text-success-600 dark:text-success-400',
            default => 'text-primary-600 dark:text-primary-400',
        };
    }
}
