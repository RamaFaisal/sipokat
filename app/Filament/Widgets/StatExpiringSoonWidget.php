<?php

namespace App\Filament\Widgets;

use App\Models\MedicineStock;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class StatExpiringSoonWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $today = Carbon::now()->startOfDay();
        $expiryThreshold = $today->copy()->addDays(90);

        // F5: hanya lapisan (batch) yang masih bersisa.
        $expiringSoonCount = MedicineStock::layers()
            ->withRemainingStock()
            ->whereNotNull('expired_date')
            ->whereBetween('expired_date', [$today->toDateString(), $expiryThreshold->toDateString()])
            ->whereHas('medicine', fn ($q) => $q->where('status', 'active'))
            ->distinct('medicine_id')
            ->count('medicine_id');

        return [
            Stat::make('Obat Mendekati ED', number_format($expiringSoonCount, 0, ',', '.'))
                ->description('Sisa kedaluwarsa ≤ 90 hari')
                ->icon(Heroicon::OutlinedClock)
                ->color($expiringSoonCount > 0 ? 'warning' : 'success'),
        ];
    }
}
