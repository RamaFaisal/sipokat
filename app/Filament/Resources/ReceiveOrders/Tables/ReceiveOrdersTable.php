<?php

namespace App\Filament\Resources\ReceiveOrders\Tables;

use App\Models\ReceiveOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReceiveOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receive_order_number')
                    ->label('Nomor RO')
                    ->searchable(),
                TextColumn::make('purchaseOrder.po_number')
                    ->label('Nomor PO')
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->sortable(),
                TextColumn::make('receive_date')
                    ->label('Tanggal Penerimaan')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        'completed' => 'success',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending' => 'Menunggu',
                        'cancelled' => 'Dibatalkan',
                        'completed' => 'Selesai',
                    }),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('print_pdf')
                        ->label('Cetak')
                        ->color('white')
                        ->icon('heroicon-s-printer')
                        ->modalContent(function ($record) {
                            $pdf = (new self)->previewProgressReportPdf($record);

                            return view('filament.modals.pdf-view', ['pdf' => $pdf, 'downloadUrl' => ''])->with('style', 'max-height: 90vh; overflow-y: auto;');
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelAction(false),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make('export_receive_order')
                        ->label('Ekspor Receive Order')
                        ->exporter(
                            \App\Filament\Exports\ReceiveOrderExporter::class,
                        ),
                ]),
                ExportAction::make('export_receive_order')
                    ->label('Ekspor Receive Order')
                    ->exporter(
                        \App\Filament\Exports\ReceiveOrderExporter::class,
                    ),
            ]);
    }

    public function previewProgressReportPdf($record)
    {
        $record->load([
            'supplier',
            'purchaseOrder',
            'items.medicine',
        ]);
        $numberPo = $record->receive_order_number;

        $pdf = Pdf::loadView(
            'print.print-receive-order',
            compact('record', 'numberPo')
        );
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream();
    }
}
