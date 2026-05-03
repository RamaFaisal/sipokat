<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('po_number')
                    ->label('Nomor PO')
                    ->searchable(),
                TextColumn::make('po_date')
                    ->label('Tanggal PO')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->label('Total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft' => 'warning',
                        'approved' => 'info',
                        'cancelled' => 'danger',
                        'completed' => 'success',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'approved' => 'Disetujui',
                        'cancelled' => 'Dibatalkan',
                        'completed' => 'Selesai',
                    }),
                TextColumn::make('status_payment')
                    ->label('Status Pembayaran')
                    ->sortable()
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'paid' => 'success',
                        'unpaid' => 'warning',
                        'partial' => 'danger',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'paid' => 'Lunas',
                        'unpaid' => 'Belum Bayar',
                        'partial' => 'Sebagian',
                    }),
                TextColumn::make('status_receive_order')
                    ->label('Status Penerimaan')
                    ->sortable()
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'received' => 'success',
                        'pending' => 'danger',
                        'partial' => 'warning',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending' => 'Belum Diterima',
                        'partial' => 'Sebagian Diterima',
                        'received' => 'Diterima',
                    }),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('pay')
                    ->label('Bayar')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-o-credit-card')
                    ->requiresConfirmation()
                    ->disabled(fn($record) => $record->status_payment === 'paid')
                    ->modalHeading('Pembayaran Purchase Order')
                    ->form([
                        Placeholder::make('po_number')
                            ->label('Nomor PO')
                            ->content(fn($record) => $record->po_number),

                        Placeholder::make('grand_total')
                            ->label('Total Pembayaran')
                            ->content(fn($record) => 'Rp ' . number_format($record->grand_total, 0, ',', '.')),
                    ])
                    ->action(function (array $data, $record) {
                        self::payAction($data, $record);
                    }),
                ActionGroup::make([
                    EditAction::make()
                        ->label('Edit')
                        ->disabled(fn($record) => $record->status_payment === 'paid'),
                    DeleteAction::make()
                        ->label('Hapus')
                        ->disabled(fn($record) => $record->status_payment === 'paid'),
                    Action::make('preview')
                        ->label('Cetak')
                        ->icon('heroicon-o-printer')
                        ->color('secondary')
                        ->modalContent(function ($record) {
                            $pdf = (new \App\Filament\Resources\PurchaseOrders\Tables\PurchaseOrdersTable)->previewProgressReportPdf($record);

                            return view('filament.modals.pdf-view', ['pdf' => $pdf, 'downloadUrl' => ''])
                                ->with('style', 'max-height: 90vh; overflow-y: auto;');
                        })
                        ->modalCancelAction(false)
                        ->modalSubmitAction(false),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public function previewProgressReportPdf($record)
    {
        $record->load([
            'supplier',
            'items.medicine',
        ]);
        $numberPo = $record->po_number;

        $pdf = Pdf::loadView(
            'print.print-purchase-order',
            compact('record', 'numberPo')
        );
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream();
    }

    public static function payAction(array $data, $record)
    {
        if ($record->status_payment === 'paid') {
            Notification::make()
                ->title('Purchase Order sudah lunas')
                ->warning()
                ->send();
            return;
        }

        try {
            DB::transaction(function () use ($data, $record) {
                $record->update([
                    'status_payment' => 'paid',
                ]);
            });
            Notification::make()
                ->title('Pembayaran berhasil')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->title('Pembayaran gagal')
                ->danger()
                ->send();
            throw $e;
        }
    }
}
