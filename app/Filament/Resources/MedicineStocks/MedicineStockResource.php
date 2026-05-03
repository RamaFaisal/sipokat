<?php

namespace App\Filament\Resources\MedicineStocks;

use App\Filament\Resources\MedicineStocks\Pages\ListMedicineStocks;
use App\Filament\Resources\MedicineStocks\Tables\MedicineStocksTable;
use App\Models\MedicineStock;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MedicineStockResource extends Resource
{
    protected static ?string $model = MedicineStock::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?string $navigationLabel = 'Kartu Stok Obat';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    public static function table(Table $table): Table
    {
        return MedicineStocksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicineStocks::route('/'),
        ];
    }
}
