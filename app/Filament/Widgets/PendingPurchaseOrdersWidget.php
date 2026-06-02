<?php

namespace App\Filament\Widgets;

use App\Models\PurchaseOrder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingPurchaseOrdersWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Purchase Order Tertunda')
            ->description('PO yang sudah approved tapi belum diterima penuh dari supplier.')
            ->query(fn (): Builder => PurchaseOrder::query()
                ->where('status', 'approved')
                ->whereIn('status_receive_order', ['pending', 'partial'])
                ->with('supplier:id,name')
                ->orderBy('estimated_arrival'))
            ->columns([
                TextColumn::make('po_number')
                    ->label('No. PO')
                    ->searchable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(),
                TextColumn::make('po_date')
                    ->label('Tgl PO')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('estimated_arrival')
                    ->label('Estimasi Sampai')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->label('Total')
                    ->money('IDR')
                    ->alignEnd(),
                TextColumn::make('status_receive_order')
                    ->label('Status RO')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'partial',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Belum diterima',
                        'partial' => 'Diterima sebagian',
                        default => $state,
                    }),
            ])
            ->paginated([5, 10, 25]);
    }
}
