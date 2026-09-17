<?php

namespace App\Filament\Resources\MedicineStockOpnames\Pages;

use App\Filament\Resources\MedicineStockOpnames\MedicineStockOpnameResource;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ViewMedicineStockOpname extends ViewRecord implements HasForms, HasTable
{
    use InteractsWithTable;

    protected static string $resource = MedicineStockOpnameResource::class;

    protected static ?string $title = 'Detail Stock Opname';

    protected string $view = 'filament.pages.medicine-stock-opname-detail';

    public function mount(string|int $record): void
    {
        parent::mount($record);

        if (! $this->record->medicineStockOpnameItems()->exists()) {
            Notification::make()
                ->title('Belum ada item obat di opname ini')
                ->warning()
                ->send();
        }
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Baris 1: empat isian ringkas; baris 2: keterangan selebar section.
                \Filament\Schemas\Components\Section::make('Informasi Stock Opname')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('opname_number')
                            ->label('Nomor Opname'),

                        TextEntry::make('opname_date')
                            ->label('Tanggal Opname')
                            ->dateTime('d M Y'),

                        TextEntry::make('total_items')
                            ->label('Total Item')
                            ->state(fn ($record) => $record->medicineStockOpnameItems()->count()),

                        TextEntry::make('creator.name')
                            ->label('Dibuat Oleh'),

                        TextEntry::make('description')
                            ->label('Keterangan')
                            ->state(fn ($record) => $record->description ?: '-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->heading('Penyesuaian per Batch')
            ->columns([
                TextColumn::make('medicine.name')
                    ->label('Obat')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('batch')
                    ->label('Batch · ED')
                    ->state(function ($record) {
                        $batch = $record->batch_number ?? $record->layer?->batch_number ?? '-';
                        $ed = $record->expired_date ?? $record->layer?->expired_date;

                        return $batch.($ed ? ' · '.$ed->format('m-Y') : '');
                    }),

                TextColumn::make('qty')
                    ->label('Jumlah')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('type_account')
                    ->label('Jenis Akun')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'D' => 'success',
                        'C' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'D' => 'Debit (Masuk)',
                        'C' => 'Kredit (Keluar)',
                        default => $state,
                    }),
                TextColumn::make('note')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->wrap()
                    ->width('35%'),
                // HPP obat per satuan jual saat opname; Σ-nya tidak bermakna, jadi tanpa baris rangkuman.
                TextColumn::make('hpp')
                    ->label('HPP / satuan jual')
                    ->money('IDR')
                    ->alignEnd()
                    ->width('1%'),
                // Nilai penyesuaian = jumlah × HPP, bertanda: masuk (+), keluar (−). Rangkumannya = selisih bersih nilai persediaan.
                TextColumn::make('value')
                    ->label('Nilai')
                    ->width('1%')
                    ->state(fn ($record) => ($record->type_account === 'C' ? -1 : 1) * $record->qty * (float) $record->hpp)
                    ->formatStateUsing(fn ($state) => ($state < 0 ? '−' : '+').' Rp '.number_format(abs($state), 0, ',', '.'))
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->weight('semibold')
                    ->alignEnd()
                    ->summarize([
                        \Filament\Tables\Columns\Summarizers\Summarizer::make()
                            ->label('Nilai selisih bersih')
                            ->using(function (\Illuminate\Database\Query\Builder $query) {
                                $in = (float) (clone $query)->where('type_account', 'D')->selectRaw('COALESCE(SUM(qty * hpp), 0) AS v')->value('v');
                                $out = (float) (clone $query)->where('type_account', 'C')->selectRaw('COALESCE(SUM(qty * hpp), 0) AS v')->value('v');
                                $net = $in - $out;

                                // Dua baris pendek: teks satu baris yang panjang membuat kolom ini merebut lebar dari Keterangan.
                                return new \Illuminate\Support\HtmlString(sprintf(
                                    '<div class="text-right"><div class="font-semibold %s">%s Rp %s</div><div class="text-xs text-gray-500">masuk Rp %s · keluar Rp %s</div></div>',
                                    $net < 0 ? 'text-danger-600' : 'text-success-600',
                                    $net < 0 ? '−' : '+',
                                    number_format(abs($net), 0, ',', '.'),
                                    number_format($in, 0, ',', '.'),
                                    number_format($out, 0, ',', '.'),
                                ));
                            }),
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->contentGrid([
                'md' => 1,
            ])
            ->paginated([10, 25, 50, 100]);
    }

    protected function getTableQuery(): Builder
    {
        return $this->record->medicineStockOpnameItems()
            ->with(['medicine'])
            ->getQuery();
    }
}
