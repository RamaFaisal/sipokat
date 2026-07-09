<?php

namespace App\Filament\Widgets;

use App\Models\Medicine;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatCriticalStockWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $criticalStockCount = Medicine::where('status', 'active')
            ->whereIn('stock_status', ['empty', 'almost_empty'])
            ->count();

        return [
            Stat::make('Obat Stok Kritis', number_format($criticalStockCount, 0, ',', '.'))
                ->description('Status stok habis / menipis')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color($criticalStockCount > 0 ? 'danger' : 'success'),
        ];
    }
}
