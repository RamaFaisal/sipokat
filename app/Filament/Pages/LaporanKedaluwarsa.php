<?php

namespace App\Filament\Pages;

use App\Models\MedicineStock;
use App\Support\AmbangEd;
use App\Support\BatchBersisa;
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
 * Batch yang mendekati kedaluwarsa beserta nilai rupiah yang terancam (K7).
 *
 * Per **batch**, bukan per obat: satu obat bisa punya batch aman dan batch mepet sekaligus, dan yang
 * perlu ditindak adalah batch-nya. Nilai terancam memakai HPP baris lapisan itu sendiri, yaitu harga
 * yang benar-benar tertanam di stok tersebut.
 */
class LaporanKedaluwarsa extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected string $view = 'filament.pages.laporan-kedaluwarsa';

    protected static ?string $title = 'Laporan Obat Akan Kedaluwarsa';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static bool $shouldRegisterNavigation = false;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->heading('Batch Mendekati Kedaluwarsa')
            ->description('Batch yang masih bersisa dengan ED sampai '.AmbangEd::PANTAU.' hari ke depan, termasuk yang sudah lewat.')
            ->defaultSort('expired_date')
            ->recordClasses(fn (MedicineStock $record): ?string => AmbangEd::kelasBaris(BatchBersisa::sisaHari($record)))
            ->columns([
                TextColumn::make('medicine.code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('medicine.name')
                    ->label('Nama Obat')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('batch_number')
                    ->label('Batch')
                    ->placeholder('tanpa nomor'),
                TextColumn::make('expired_date')
                    ->label('Kedaluwarsa')
                    ->date(Tanggal::TAMPIL)
                    ->sortable(),
                TextColumn::make('sisa_hari')
                    ->label('Sisa Hari')
                    ->state(fn (MedicineStock $record): int => BatchBersisa::sisaHari($record))
                    ->badge()
                    ->color(fn ($state): string => AmbangEd::warna((int) $state))
                    ->formatStateUsing(fn ($state): string => (int) $state < 0 ? 'lewat '.abs((int) $state).' hari' : $state.' hari')
                    ->alignEnd(),
                TextColumn::make('sisa')
                    ->label('Sisa Stok')
                    ->numeric()
                    ->suffix(fn (MedicineStock $record): string => ' '.($record->medicine?->unit?->name ?? ''))
                    ->alignEnd(),
                TextColumn::make('nilai')
                    ->label('Nilai Terancam')
                    ->money('IDR')
                    ->weight('semibold')
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('tingkat')
                    ->label('Tingkat')
                    ->options([
                        'lewat' => 'Sudah kedaluwarsa',
                        'mendesak' => 'Mendesak (≤ '.AmbangEd::MENDESAK.' hari)',
                        'waspada' => 'Waspada ('.(AmbangEd::MENDESAK + 1).' sampai '.AmbangEd::WASPADA.' hari)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $hariIni = today();

                        return match ($data['value'] ?? null) {
                            'lewat' => $query->whereDate('expired_date', '<=', $hariIni->toDateString()),
                            'mendesak' => $query->whereDate('expired_date', '<=', $hariIni->copy()->addDays(AmbangEd::MENDESAK)->toDateString()),
                            'waspada' => $query
                                ->whereDate('expired_date', '>', $hariIni->copy()->addDays(AmbangEd::MENDESAK)->toDateString())
                                ->whereDate('expired_date', '<=', $hariIni->copy()->addDays(AmbangEd::WASPADA)->toDateString()),
                            default => $query,
                        };
                    }),
            ])
            ->toolbarActions([
                Action::make('excel')
                    ->label('Ekspor Excel')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn () => $this->unduhExcel()),
            ])
            ->paginated([25, 50, 100]);
    }

    /**
     * Satu baris per batch, bukan per lapisan. Batch yang dibeli dua kali tetap satu tumpukan di
     * rak, dan laporan ini menjawab "apa yang perlu ditindak", bukan "dari faktur mana asalnya".
     * Rincian per faktur tetap ada di Kartu Stok.
     */
    protected function query(): Builder
    {
        return BatchBersisa::query()
            ->whereDate('expired_date', '<=', today()->addDays(AmbangEd::PANTAU)->toDateString())
            ->with(['medicine.unit']);
    }

    public function unduhExcel()
    {
        $baris = $this->query()->orderBy('expired_date')->get()->map(fn (MedicineStock $b): array => [
            $b->medicine?->code,
            $b->medicine?->name,
            $b->batch_number ?: 'tanpa nomor',
            $b->expired_date->translatedFormat(Tanggal::TAMPIL),
            BatchBersisa::sisaHari($b),
            (int) $b->sisa,
            $b->medicine?->unit?->name,
            round((float) $b->nilai),
        ]);

        return LaporanExcel::unduh(
            'Laporan Obat Akan Kedaluwarsa',
            'sampai '.AmbangEd::PANTAU.' hari ke depan, per '.today()->translatedFormat(Tanggal::TAMPIL),
            ['Kode', 'Nama Obat', 'Batch', 'Kedaluwarsa', 'Sisa Hari', 'Sisa Stok', 'Satuan', 'Nilai Terancam'],
            $baris,
            'laporan-kedaluwarsa-'.now()->format('Ymd_His').'.xlsx',
        );
    }
}
