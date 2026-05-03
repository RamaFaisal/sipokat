<?php

namespace App\Filament\Resources\ReceiveOrders;

use App\Filament\Resources\ReceiveOrders\Pages\CreateReceiveOrder;
use App\Filament\Resources\ReceiveOrders\Pages\ListReceiveOrders;
use App\Filament\Resources\ReceiveOrders\Schemas\ReceiveOrderForm;
use App\Filament\Resources\ReceiveOrders\Tables\ReceiveOrdersTable;
use App\Models\ReceiveOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ReceiveOrderResource extends Resource
{
    protected static ?string $model = ReceiveOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static ?string $navigationLabel = 'Receive Order';

    protected static string|UnitEnum|null $navigationGroup = 'Pembelian';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ReceiveOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReceiveOrdersTable::configure($table);
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
            'index' => ListReceiveOrders::route('/'),
            'create' => CreateReceiveOrder::route('/create'),
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
