<?php

namespace App\Filament\Resources\MedicineStockOpnames;

use App\Filament\Resources\MedicineStockOpnames\Pages\CreateMedicineStockOpname;
use App\Filament\Resources\MedicineStockOpnames\Pages\EditMedicineStockOpname;
use App\Filament\Resources\MedicineStockOpnames\Pages\ListMedicineStockOpnames;
use App\Filament\Resources\MedicineStockOpnames\Pages\ViewMedicineStockOpname;
use App\Filament\Resources\MedicineStockOpnames\Schemas\MedicineStockOpnameForm;
use App\Filament\Resources\MedicineStockOpnames\Tables\MedicineStockOpnamesTable;
use App\Models\MedicineStockOpname;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MedicineStockOpnameResource extends Resource
{
    protected static ?string $model = MedicineStockOpname::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentPlus;

    protected static ?string $navigationLabel = 'Stok Opname';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return MedicineStockOpnameForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicineStockOpnamesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicineStockOpnames::route('/'),
            'create' => CreateMedicineStockOpname::route('/create'),
            'view' => ViewMedicineStockOpname::route('/{record}')
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
