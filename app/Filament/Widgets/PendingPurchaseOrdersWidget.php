<?php

namespace App\Filament\Widgets;

use App\Models\PurchaseOrder;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingPurchaseOrdersWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pesanan Belum Lengkap')
            ->description('PO yang belum diterima penuh dari PBF (belum diterima / sebagian).')
            ->query(fn (): Builder => PurchaseOrder::query()
                ->whereIn('status_receive_order', [PurchaseOrder::STATUS_PENDING, PurchaseOrder::STATUS_PARTIAL])
                ->with(['supplier:id,name', 'items'])
                ->withCount('items')
                ->orderBy('po_date'))
            ->columns([
                TextColumn::make('po_number')
                    ->label('No. PO')
                    ->searchable(),
                TextColumn::make('supplier.name')
                    ->label('PBF')
                    ->searchable(),
                TextColumn::make('po_date')
                    ->label('Tgl pesan')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Item')
                    ->alignCenter(),
                TextColumn::make('remaining')
                    ->label('Sisa (satuan jual)')
                    ->state(fn (PurchaseOrder $record) => array_sum($record->remainingByMedicine()))
                    ->alignEnd(),
                TextColumn::make('status_receive_order')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => $state === PurchaseOrder::STATUS_PARTIAL ? 'warning' : 'danger')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        PurchaseOrder::STATUS_PENDING => 'Belum diterima',
                        PurchaseOrder::STATUS_PARTIAL => 'Sebagian diterima',
                        default => $state,
                    }),
            ])
            ->paginated([5, 10, 25]);
    }
}
