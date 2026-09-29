<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\MedicineStockDetail;
use App\Filament\Pages\SawCalculation;
use App\Services\SawCalculationService;
use App\Support\Tanggal;
use App\Support\TautanWidget;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Collection;

/**
 * Peringkat prioritas restock, seluruh obat yang masuk perhitungan (K5).
 *
 * Dihitung langsung saat dashboard dibuka (2026-09-27), memakai `calculateCached()` sehingga
 * pembukaan berulang tidak mengulang 4 + 5N query.
 *
 * Tabelnya berbasis array (`records()`), bukan query Eloquent: seluruh peringkat dimuat sekaligus
 * dan digulir di dalam kartu, tanpa halaman maupun kotak pencarian.
 */
class SawTop10RestockWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    /** Baris yang ditampilkan. Peringkat selengkapnya di halaman SPK, dicapai dari judul widget. */
    private const BARIS = 6;

    /** @var array<string, mixed>|null */
    protected ?array $hasil = null;

    public function table(Table $table): Table
    {
        return $table
            ->heading(TautanWidget::judul('Prioritas Restock (SAW)', SawCalculation::canAccess() ? SawCalculation::getUrl() : null))
            ->description(fn (): string => $this->keterangan())
            // Baris diambil di dalam closure, bukan sebelum tabel dibangun: objek tabel di-cache
            // Filament, sehingga baris yang dihitung di luar closure akan membeku pada render pertama.
            ->records(fn (): Collection => collect(array_slice($this->hasil()['rows'], 0, self::BARIS)))
            ->recordUrl(fn ($record): ?string => MedicineStockDetail::canAccess()
                ? MedicineStockDetail::getUrl(['record' => $record['medicine_id']])
                : null)
            ->paginated(false)
            ->extraAttributes(['class' => 'sipokat-widget-gulir'])
            ->columns([
                TextColumn::make('rank')
                    ->label('Tingkat')
                    ->tooltip('Nilai prioritas sama = tingkat sama')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        (int) $state <= 3 => 'danger',
                        (int) $state <= 7 => 'warning',
                        default => 'gray',
                    })
                    ->width(1),
                TextColumn::make('name')
                    ->label('Nama Obat')
                    ->wrap(),
            ]);
    }

    /** Hasil SAW untuk render ini; dihitung sekali lalu dipakai ulang dalam satu permintaan. */
    protected function hasil(): array
    {
        try {
            return $this->hasil ??= app(SawCalculationService::class)
                ->calculateCached(today()->subDays(29), today());
        } catch (\Throwable $e) {
            return $this->hasil ??= ['rows' => [], 'galat' => $e->getMessage()];
        }
    }

    protected function keterangan(): string
    {
        $hasil = $this->hasil();

        if (isset($hasil['galat'])) {
            return 'Perhitungan tidak dapat dijalankan: '.$hasil['galat'];
        }

        return 'Periode '.$hasil['period_start']->translatedFormat(Tanggal::TAMPIL)
            .' sampai '.$hasil['period_end']->translatedFormat(Tanggal::TAMPIL).'.';
    }
}
