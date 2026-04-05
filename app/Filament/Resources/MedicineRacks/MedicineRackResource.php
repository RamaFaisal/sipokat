<?php

namespace App\Filament\Resources\MedicineRacks;

use App\Filament\Resources\MedicineRacks\Pages\CreateMedicineRack;
use App\Filament\Resources\MedicineRacks\Pages\EditMedicineRack;
use App\Filament\Resources\MedicineRacks\Pages\ListMedicineRacks;
use App\Filament\Resources\MedicineRacks\Schemas\MedicineRackForm;
use App\Filament\Resources\MedicineRacks\Tables\MedicineRacksTable;
use App\Models\MedicineRack;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MedicineRackResource extends Resource
{
    protected static ?string $model = MedicineRack::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Rak Obat';

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return MedicineRackForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicineRacksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicineRacks::route('/'),
            'create' => CreateMedicineRack::route('/create'),
            'edit' => EditMedicineRack::route('/{record}/edit'),
        ];
    }
}
