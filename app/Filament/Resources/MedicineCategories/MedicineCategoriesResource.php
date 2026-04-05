<?php

namespace App\Filament\Resources\MedicineCategories;

use App\Filament\Resources\MedicineCategories\Pages\CreateMedicineCategories;
use App\Filament\Resources\MedicineCategories\Pages\EditMedicineCategories;
use App\Filament\Resources\MedicineCategories\Pages\ListMedicineCategories;
use App\Filament\Resources\MedicineCategories\Schemas\MedicineCategoriesForm;
use App\Filament\Resources\MedicineCategories\Tables\MedicineCategoriesTable;
use App\Models\MedicineCategories;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MedicineCategoriesResource extends Resource
{
    protected static ?string $model = MedicineCategories::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Kategori Obat';

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return MedicineCategoriesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicineCategoriesTable::configure($table);
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
            'index' => ListMedicineCategories::route('/'),
            'create' => CreateMedicineCategories::route('/create'),
            'edit' => EditMedicineCategories::route('/{record}/edit'),
        ];
    }
}
