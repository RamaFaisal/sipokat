<?php

namespace App\Filament\Widgets;

use App\Models\Medicine;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatTotalMedicinesWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $totalActiveMedicines = Medicine::where('status', 'active')->count();

        return [
            Stat::make('Total Obat Aktif', number_format($totalActiveMedicines, 0, ',', '.'))
                ->description('Jumlah Obat Aktif')
                ->icon(Heroicon::OutlinedRectangleStack)
                ->color('primary'),
        ];
    }
}
