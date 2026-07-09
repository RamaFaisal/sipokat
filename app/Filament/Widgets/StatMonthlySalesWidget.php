<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class StatMonthlySalesWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $monthlySales = Order::where('status', '!=', 'cancelled')
            ->whereBetween('order_date', [
                Carbon::now()->startOfMonth()->toDateString(),
                Carbon::now()->endOfMonth()->toDateString(),
            ])
            ->sum('grand_total');

        return [
            Stat::make('Penjualan Bulan Ini', 'Rp ' . number_format((float) $monthlySales, 0, ',', '.'))
                ->description(Carbon::now()->locale('id')->translatedFormat('F Y'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success'),
        ];
    }
}
