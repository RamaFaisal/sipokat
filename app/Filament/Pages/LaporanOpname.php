<?php

namespace App\Filament\Pages;

use App\Models\MedicineStockOpnameItem;
use App\Support\LaporanExcel;
use App\Support\Tanggal;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Riwayat penyesuaian stok opname beserta nilai selisihnya (K7).
 *
 * Berguna sebagai bukti pengendalian: berapa sering fisik berbeda dari sistem, pada batch mana, dan
 * berapa nilainya. Satu baris per penyesuaian batch, bukan per dokumen opname, karena satu opname
 * bisa menyesuaikan banyak batch dengan arah yang berbeda.
 *
 * Nilai dihitung bertanda: penambahan (D) positif, pengurangan (C) negatif, memakai HPP yang
 * tersimpan pada baris itu.
 */
class LaporanOpname extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected string $view = 'filament.pages.laporan-opname';

    protected static ?string $title = 'Laporan Hasil Stok Opname';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static bool $shouldRegisterNavigation = false;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->heading('Penyesuaian Stok Opname')
            ->description('Selisih fisik terhadap sistem, per batch, beserta nilainya.')
            ->defaultSort('medicine_stock_opname_items.id', 'desc')
            ->columns([
                TextColumn::make('medicineStockOpname.opname_number')
                    ->label('Nomor Opname')
                    ->searchable(),
                TextColumn::make('medicineStockOpname.opname_date')
                    ->label('Tanggal')
                    ->date(Tanggal::TAMPIL)
                    ->sortable(),
                TextColumn::make('medicine.name')
                    ->label('Obat')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('batch')
                    ->label('Batch')
                    ->state(fn (MedicineStockOpnameItem $record): string => $record->batch_number
                        ?: ($record->layer?->batch_number ?: 'tanpa nomor')),
                TextColumn::make('type_account')
                    ->label('Arah')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'D' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state): string => $state === 'D' ? 'Lebih' : 'Kurang'),
                TextColumn::make('qty')
                    ->label('Selisih')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('nilai')
                    ->label('Nilai Selisih')
                    ->state(fn (MedicineStockOpnameItem $record): float => ($record->type_account === 'C' ? -1 : 1)
                        * $record->qty * (float) $record->hpp)
                    ->formatStateUsing(fn ($state): string => ($state < 0 ? '- ' : '+ ')
                        .'Rp '.number_format(abs((float) $state), 0, ',', '.'))
                    ->color(fn ($state): string => $state < 0 ? 'danger' : 'success')
                    ->weight('semibold')
                    ->alignEnd(),
                TextColumn::make('note')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('type_account')
                    ->label('Arah')
                    ->options(['D' => 'Lebih dari sistem', 'C' => 'Kurang dari sistem']),
            ])
            ->toolbarActions([
                Action::make('excel')
                    ->label('Ekspor Excel')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn () => $this->unduhExcel()),
            ])
            ->paginated([25, 50, 100]);
    }

    protected function query(): Builder
    {
        return MedicineStockOpnameItem::query()
            ->with(['medicine', 'layer', 'medicineStockOpname']);
    }

    public function unduhExcel()
    {
        $baris = $this->query()->get()->map(fn (MedicineStockOpnameItem $i): array => [
            $i->medicineStockOpname?->opname_number,
            $i->medicineStockOpname?->opname_date?->translatedFormat(Tanggal::TAMPIL),
            $i->medicine?->name,
            $i->batch_number ?: ($i->layer?->batch_number ?: 'tanpa nomor'),
            $i->type_account === 'D' ? 'Lebih' : 'Kurang',
            $i->qty,
            round(($i->type_account === 'C' ? -1 : 1) * $i->qty * (float) $i->hpp),
            $i->note,
        ]);

        return LaporanExcel::unduh(
            'Laporan Hasil Stok Opname',
            'seluruh penyesuaian sampai '.today()->translatedFormat(Tanggal::TAMPIL),
            ['Nomor Opname', 'Tanggal', 'Obat', 'Batch', 'Arah', 'Selisih', 'Nilai Selisih', 'Keterangan'],
            $baris,
            'laporan-opname-'.now()->format('Ymd_His').'.xlsx',
        );
    }
}
