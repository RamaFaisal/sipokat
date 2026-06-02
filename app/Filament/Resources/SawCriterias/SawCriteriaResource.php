<?php

namespace App\Filament\Resources\SawCriterias;

use App\Filament\Resources\SawCriterias\Pages\EditSawCriteria;
use App\Filament\Resources\SawCriterias\Pages\ListSawCriteria;
use App\Filament\Resources\SawCriterias\Schemas\SawCriteriaForm;
use App\Filament\Resources\SawCriterias\Tables\SawCriteriaTable;
use App\Models\SawCriteria;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SawCriteriaResource extends Resource
{
    protected static ?string $model = SawCriteria::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Kriteria SAW';

    protected static string|UnitEnum|null $navigationGroup = 'SPK Restock';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return SawCriteriaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SawCriteriaTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSawCriteria::route('/'),
            'edit' => EditSawCriteria::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
