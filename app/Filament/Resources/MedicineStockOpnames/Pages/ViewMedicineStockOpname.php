<?php

namespace App\Filament\Resources\MedicineStockOpnames\Pages;

use App\Filament\Resources\MedicineStockOpnames\MedicineStockOpnameResource;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;

class ViewMedicineStockOpname extends ViewRecord implements HasForms, HasTable
{
    use InteractsWithTable;
    protected static string $resource = MedicineStockOpnameResource::class;

    protected static ?string $title = 'Detail Stock Opname';

    protected  string $view = 'filament.pages.medicine-stock-opname-detail';

    public function mount(string|int $record): void
    {
        parent::mount($record);

        if (!$this->record->medicineStockOpnameItems()->exists()) {
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
                \Filament\Schemas\Components\Section::make('Informasi Stock Opname')
                    ->schema([
                        TextEntry::make('opname_number')
                            ->label('Nomor Opname'),

                        TextEntry::make('opname_date')
                            ->label('Tanggal Opname')
                            ->dateTime('d M Y H:i'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'draft' => 'gray',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'warning',
                            }),

                        TextEntry::make('description')
                            ->label('Keterangan')
                            ->columnSpanFull(),

                        TextEntry::make('total_items')
                            ->label('Total Item')
                            ->state(fn($record) => $record->medicineStockOpnameItems()->count()),

                        TextEntry::make('total_hpp')
                            ->label('Total HPP')
                            ->state(fn($record) => 'Rp ' . number_format($record->medicineStockOpnameItems()->sum('hpp'), 0, ',', '.')),

                        TextEntry::make('creator.name')
                            ->label('Dibuat Oleh'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->heading('Detail Item medicine')
            ->columns([
                TextColumn::make('medicine.name')
                    ->label('Nama medicine')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('medicine.code')
                    ->label('Kode medicine')
                    ->searchable(),

                TextColumn::make('qty')
                    ->label('Jumlah')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('type_account')
                    ->label('Jenis Akun')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'D' => 'success',
                        'C' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'D' => 'Debit (Masuk)',
                        'C' => 'Kredit (Keluar)',
                        default => $state,
                    }),

                TextColumn::make('medicine.purchase_price')
                    ->label('Harga Beli')
                    ->money('IDR')
                    ->alignEnd(),

                TextColumn::make('hpp')
                    ->label('HPP')
                    ->money('IDR')
                    ->alignEnd()
                    ->summarize([
                        \Filament\Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
                            ->label('Total HPP'),
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

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
