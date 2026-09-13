<?php

namespace App\Filament\Resources\ReceiveOrders\Tables;

use App\Models\ReceiveOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReceiveOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['supplier', 'purchaseOrder', 'items']))
            ->defaultSort('receive_date', 'desc')
            ->columns([
                TextColumn::make('receive_order_number')
                    ->label('Nomor RO')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_number')
                    ->label('Nomor faktur')
                    ->searchable(),
                TextColumn::make('supplier.name')
                    ->label('PBF')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('purchaseOrder.po_number')
                    ->label('Dari PO')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('receive_date')
                    ->label('Tanggal terima')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total faktur')
                    ->state(fn (ReceiveOrder $record) => $record->total())
                    ->money('IDR')
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('PBF')
                    ->relationship('supplier', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->label('Edit'),
                    Action::make('print_pdf')
                        ->label('Cetak')
                        ->icon('heroicon-s-printer')
                        ->modalContent(function ($record) {
                            $pdf = (new self)->previewProgressReportPdf($record);

                            return view('filament.modals.pdf-view', ['pdf' => $pdf, 'downloadUrl' => ''])->with('style', 'max-height: 90vh; overflow-y: auto;');
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelAction(false),
                    DeleteAction::make()
                        ->label('Hapus')
                        ->using(function (ReceiveOrder $record) {
                            try {
                                return $record->delete();
                            } catch (\RuntimeException $e) {
                                Notification::make()->danger()->title('Tidak dapat dihapus')->body($e->getMessage())->persistent()->send();

                                return false;
                            }
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make('export_receive_order')
                        ->label('Ekspor Penerimaan')
                        ->exporter(\App\Filament\Exports\ReceiveOrderExporter::class),
                ]),
                ExportAction::make('export_receive_order')
                    ->label('Ekspor Penerimaan')
                    ->exporter(\App\Filament\Exports\ReceiveOrderExporter::class),
            ]);
    }

    public function previewProgressReportPdf($record)
    {
        $record->load([
            'supplier',
            'purchaseOrder',
            'items.medicine.unit',
            'items.packUnit',
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
