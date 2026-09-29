<?php

namespace App\Filament\Pages;

use App\Models\MedicineStock;
use App\Support\AmbangEd;
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
            ->recordClasses(fn (MedicineStock $record): ?string => AmbangEd::kelasBaris($this->sisaHari($record)))
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
                    ->state(fn (MedicineStock $record): int => $this->sisaHari($record))
                    ->badge()
                    ->color(fn ($state): string => AmbangEd::warna((int) $state))
                    ->formatStateUsing(fn ($state): string => (int) $state < 0 ? 'lewat '.abs((int) $state).' hari' : $state.' hari')
                    ->alignEnd(),
                TextColumn::make('sisa')
                    ->label('Sisa Stok')
                    ->state(fn (MedicineStock $record): int => $record->remaining)
                    ->suffix(fn (MedicineStock $record): string => ' '.($record->medicine?->unit?->name ?? ''))
                    ->alignEnd(),
                TextColumn::make('nilai')
                    ->label('Nilai Terancam')
                    ->state(fn (MedicineStock $record): float => $record->remaining * (float) $record->hpp)
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

    protected function query(): Builder
    {
        return MedicineStock::layers()
            ->withRemainingStock()
            ->whereNotNull('expired_date')
            ->whereDate('expired_date', '<=', today()->addDays(AmbangEd::PANTAU)->toDateString())
            ->whereHas('medicine', fn (Builder $q) => $q->where('status', 'active'))
            ->withSum('consumptions', 'qty')
            ->with(['medicine.unit']);
    }

    public function unduhExcel()
    {
        $baris = $this->query()->orderBy('expired_date')->get()->map(fn (MedicineStock $l): array => [
            $l->medicine?->code,
            $l->medicine?->name,
            $l->batch_number ?: 'tanpa nomor',
            $l->expired_date->translatedFormat(Tanggal::TAMPIL),
            $this->sisaHari($l),
            $l->remaining,
            $l->medicine?->unit?->name,
            round($l->remaining * (float) $l->hpp),
        ]);

        return LaporanExcel::unduh(
            'Laporan Obat Akan Kedaluwarsa',
            'sampai '.AmbangEd::PANTAU.' hari ke depan, per '.today()->translatedFormat(Tanggal::TAMPIL),
            ['Kode', 'Nama Obat', 'Batch', 'Kedaluwarsa', 'Sisa Hari', 'Sisa Stok', 'Satuan', 'Nilai Terancam'],
            $baris,
            'laporan-kedaluwarsa-'.now()->format('Ymd_His').'.xlsx',
        );
    }

    private function sisaHari(MedicineStock $lapisan): int
    {
        return (int) today()->diffInDays($lapisan->expired_date->startOfDay(), false);
    }
}
