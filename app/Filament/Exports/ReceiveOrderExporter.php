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
            ExportColumn::make('invoice_number')
                ->label('Nomor Faktur'),
            ExportColumn::make('supplier.name')
                ->label('PBF'),
            ExportColumn::make('purchaseOrder.po_number')
                ->label('Dari PO'),
            ExportColumn::make('receive_date')
                ->label('Tanggal Terima'),
            ExportColumn::make('total')
                ->label('Total Faktur')
                ->state(fn (ReceiveOrder $record) => $record->total()),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Ekspor receive order selesai. '.number_format($export->successful_rows).' baris berhasil diekspor.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' baris gagal diekspor.';
        }

        return $body;
    }
}
