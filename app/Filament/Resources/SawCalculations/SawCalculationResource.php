<?php

namespace App\Filament\Resources\SawCalculations;

use App\Filament\Resources\SawCalculations\Pages\ListSawCalculationHistory;
use App\Filament\Resources\SawCalculations\Pages\ViewSawCalculationHistory;
use App\Filament\Resources\SawCalculations\Tables\SawCalculationHistoryTable;
use App\Models\SawCalculation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SawCalculationResource extends Resource
{
    protected static ?string $model = SawCalculation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Riwayat Perhitungan';

    protected static string|UnitEnum|null $navigationGroup = 'SPK Restock';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return SawCalculationHistoryTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSawCalculationHistory::route('/'),
            'view' => ViewSawCalculationHistory::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}
