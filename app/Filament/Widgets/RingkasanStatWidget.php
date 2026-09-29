<?php

namespace App\Filament\Widgets;

use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\Order;
use App\Support\AmbangEd;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Empat angka ringkas dashboard dalam satu widget.
 *
 * Sebelum 2026-09-29 keempatnya adalah widget terpisah dengan `columnSpan` 1. Begitu grid dashboard
 * dijadikan tiga kolom supaya tiga widget tabel muat sebaris (K2, K5), empat kartu berukuran satu
 * kolom menyisakan baris timpang 3 + 1. `StatsOverviewWidget` memang menyusun beberapa stat
 * berdampingan, jadi menyatukannya menyelesaikan tata letak sekaligus memangkas empat izin Shield
 * menjadi satu.
 */
class RingkasanStatWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 'full';

    /**
     * Bawaan Filament untuk empat stat adalah empat kolom pada hampir semua lebar, sehingga di layar
     * sempit kartunya terhimpit dan angkanya terpotong. Di sini dibuat bertahap: satu kolom di ponsel,
     * dua di tablet, empat di layar lebar.
     *
     * @var array<string, int>
     */
    protected int|array|null $columns = [
        'default' => 1,
        'sm' => 2,
        'xl' => 4,
    ];

    protected function getStats(): array
    {
        return [
            $this->totalObat(),
            $this->stokKritis(),
            $this->mendekatiEd(),
            $this->penjualanBulanIni(),
        ];
    }

    private function totalObat(): Stat
    {
        $jumlah = Medicine::where('status', 'active')->count();

        return Stat::make('Total Obat Aktif', number_format($jumlah, 0, ',', '.'))
            ->description('Jumlah obat aktif')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->color('primary');
    }

    private function stokKritis(): Stat
    {
        $jumlah = Medicine::where('status', 'active')
            ->whereIn('stock_status', ['empty', 'almost_empty'])
            ->count();

        return Stat::make('Obat Stok Kritis', number_format($jumlah, 0, ',', '.'))
            ->description('Status stok habis atau menipis')
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color($jumlah > 0 ? 'danger' : 'success');
    }

    private function mendekatiEd(): Stat
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

        return Stat::make('Obat Mendekati ED', number_format($jumlah, 0, ',', '.'))
            ->description('Batch bersisa dengan ED ≤ '.AmbangEd::PANTAU.' hari')
            ->icon(Heroicon::OutlinedClock)
            ->color($jumlah > 0 ? 'warning' : 'success');
    }

    private function penjualanBulanIni(): Stat
    {
        $total = Order::query()
            ->whereDate('order_date', '>=', Carbon::now()->startOfMonth()->toDateString())
            ->whereDate('order_date', '<=', Carbon::now()->endOfMonth()->toDateString())
            ->sum('grand_total');

        return Stat::make('Penjualan Bulan Ini', 'Rp '.number_format((float) $total, 0, ',', '.'))
            ->description(Carbon::now()->translatedFormat('F Y'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success');
    }
}
