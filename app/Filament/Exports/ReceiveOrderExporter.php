<?php

namespace App\Filament\Exports;

use App\Models\ReceiveOrder;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ReceiveOrderExporter extends Exporter
{
    protected static ?string $model = ReceiveOrder::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('receive_order_number')
                ->label('Nomor RO'),
            ExportColumn::make('purchaseOrder.po_number')
                ->label('Nomor PO'),
            ExportColumn::make('supplier.name')
                ->label('Supplier'),
            ExportColumn::make('receive_date')
                ->label('Tanggal Penerimaan'),
            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn(string $state): string => match ($state) {
                    'pending' => 'Menunggu',
                    'cancelled' => 'Dibatalkan',
                    'completed' => 'Selesai',
                    default => ucfirst($state),
                }),
            ExportColumn::make('description')
                ->label('Keterangan'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Ekspor receive order selesai. ' . number_format($export->successful_rows) . ' baris berhasil diekspor.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' baris gagal diekspor.';
        }

        return $body;
    }
}
