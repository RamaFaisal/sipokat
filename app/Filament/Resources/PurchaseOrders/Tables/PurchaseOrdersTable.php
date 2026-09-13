<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Models\PurchaseOrder;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['supplier', 'items'])->withCount('items'))
            ->defaultSort('po_date', 'desc')
            ->columns([
                TextColumn::make('po_number')
                    ->label('Nomor PO')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('po_date')
                    ->label('Tanggal pesan')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('PBF')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('items_count')
                    ->label('Item')
                    ->alignCenter(),
                TextColumn::make('estimated_total')
                    ->label('Perkiraan total')
                    ->state(fn (PurchaseOrder $record) => $record->estimatedTotal())
                    ->money('IDR')
                    ->alignEnd(),
                TextColumn::make('status_receive_order')
                    ->label('Penerimaan')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PurchaseOrder::STATUS_RECEIVED => 'success',
                        PurchaseOrder::STATUS_PARTIAL => 'warning',
                        PurchaseOrder::STATUS_CLOSED => 'gray',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => self::statusLabel($state)),
            ])
            ->filters([
                SelectFilter::make('status_receive_order')
                    ->label('Penerimaan')
                    ->options([
                        PurchaseOrder::STATUS_PENDING => 'Belum diterima',
                        PurchaseOrder::STATUS_PARTIAL => 'Sebagian diterima',
                        PurchaseOrder::STATUS_RECEIVED => 'Diterima lengkap',
                        PurchaseOrder::STATUS_CLOSED => 'Ditutup',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Edit')
                    // Setelah ada penerimaan, jumlah pesanan tidak diubah lagi — sisa PO harus tetap bermakna.
                    ->disabled(fn (PurchaseOrder $record) => $record->status_receive_order !== PurchaseOrder::STATUS_PENDING),
                DeleteAction::make()
                    ->label('Hapus')
                    ->disabled(fn (PurchaseOrder $record) => $record->status_receive_order !== PurchaseOrder::STATUS_PENDING),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('close')
                        ->label('Tutup PO')
                        ->icon('heroicon-o-lock-closed')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Tutup PO')
                        ->modalDescription('Sisa pesanan yang belum diterima dicatat sebagai tidak terpenuhi. PO yang sudah ditutup tidak muncul lagi di daftar sisa saat membuat penerimaan.')
                        ->action(function (Collection $records) {
                            $closed = 0;
                            foreach ($records as $record) {
                                if ($record->isOpen()) {
                                    $record->forceFill(['status_receive_order' => PurchaseOrder::STATUS_CLOSED])->save();
                                    $closed++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title("{$closed} PO ditutup")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function statusLabel(string $state): string
    {
        return match ($state) {
            PurchaseOrder::STATUS_PENDING => 'Belum diterima',
            PurchaseOrder::STATUS_PARTIAL => 'Sebagian diterima',
            PurchaseOrder::STATUS_RECEIVED => 'Diterima lengkap',
            PurchaseOrder::STATUS_CLOSED => 'Ditutup',
            default => $state,
        };
    }
}
