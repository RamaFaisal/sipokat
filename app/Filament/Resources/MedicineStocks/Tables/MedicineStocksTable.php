<?php

namespace App\Filament\Resources\MedicineStocks\Tables;

use App\Filament\Pages\MedicineStockDetail;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Services\StockCardService;
use App\Support\AmbangEd;
use App\Support\Tanggal;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MedicineStocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ED terdekat diambil lewat subquery, bukan panggilan service per baris: satu query
            // untuk seluruh halaman, dan kolomnya bisa diurutkan di SQL.
            ->query(Medicine::query()->addSelect([
                'ed_terdekat' => MedicineStock::query()
                    ->selectRaw('min(expired_date)')
                    ->whereColumn('medicine_stocks.medicine_id', 'medicines.id')
                    ->layers()
                    ->whereNotNull('expired_date')
                    ->withRemainingStock(),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Obat')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Kode Obat')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('init_stock')
                    ->label('Stok Awal')
                    ->sortable()
                    ->default(0)
                    ->formatStateUsing(function ($state, $record, $livewire) {
                        $filters = $livewire->tableFilters['period'] ?? [];
                        $year = $filters['year'] ?? now()->year;
                        $month = $filters['month'] ?? now()->month;

                        $stockService = app(StockCardService::class);
                        return $stockService->getInitStockForPeriod(
                            $record->id,
                            $year,
                            $month
                        );
                    }),
                TextColumn::make('current_stock')
                    ->label('Stok Saat Ini')
                    ->default(0)
                    ->sortable()
                    ->formatStateUsing(function ($state, $record, $livewire) {
                        $filters = $livewire->tableFilters['period'] ?? [];
                        $year = $filters['year'] ?? now()->year;
                        $month = $filters['month'] ?? now()->month;

                        $stockService = app(StockCardService::class);
                        return $stockService->getCurrentStockForPeriod(
                            $record->id,
                            $year,
                            $month
                        );
                    }),
                // ED **terdekat** yang masih bersisa, berbeda dari C3 SAW yang memakai batch
                // terjauh (CLAUDE.md §6). Yang ini menjawab "batch mana yang harus dihabiskan
                // duluan", bukan "sampai kapan stok ini bertahan".
                TextColumn::make('ed_terdekat')
                    ->label('ED terdekat')
                    ->sortable()
                    ->badge()
                    ->placeholder('-')
                    ->color(fn ($state) => AmbangEd::warna(self::sisaHari($state)))
                    ->formatStateUsing(function ($state): string {
                        $sisa = self::sisaHari($state);
                        $tanggal = Carbon::parse($state)->translatedFormat(Tanggal::BULAN_TAHUN);

                        return $sisa !== null && $sisa < 0
                            ? $tanggal.' (lewat)'
                            : $tanggal.' ('.$sisa.' hari)';
                    }),
            ])
            ->filters([
                Filter::make('period')
                    ->form([
                        Select::make('year')
                            ->label('Tahun')
                            ->options(
                                collect(range(now()->year, now()->year - 10))
                                    ->mapWithKeys(fn($year) => [$year => $year])
                            )
                            ->default(now()->year)
                            ->required()
                            ->live()
                            ->disablePlaceholderSelection(),

                        Select::make('month')
                            ->label('Bulan')
                            ->options([
                                1  => 'Januari',
                                2  => 'Februari',
                                3  => 'Maret',
                                4  => 'April',
                                5  => 'Mei',
                                6  => 'Juni',
                                7  => 'Juli',
                                8  => 'Agustus',
                                9  => 'September',
                                10 => 'Oktober',
                                11 => 'November',
                                12 => 'Desember',
                            ])
                            ->default(now()->month)
                            ->required()
                            ->live()
                            ->disablePlaceholderSelection(),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
                    ->query(function (Builder $query, array $data): Builder {
                        if (
                            empty($data['year']) ||
                            empty($data['month']) ||
                            ! is_numeric($data['year']) ||
                            ! is_numeric($data['month'])
                        ) {
                            return $query;
                        }
                        $year  = $data['year']  ?? now()->year;
                        $month = $data['month'] ?? now()->month;
                        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                        $endDate   = Carbon::create($year, $month, 1)->endOfMonth();
                        return $query;
                    }),
            ], FiltersLayout::AboveContent)
            ->recordActions([
                Action::make('detail')
                    ->url(fn($record): string => MedicineStockDetail::getUrl(['record' => $record]))
                    ->label('Lihat Kartu Stok'),
            ])
            ->toolbarActions([
                //
            ]);
    }

    /** Sisa hari menuju ED; negatif berarti sudah kedaluwarsa. */
    private static function sisaHari($state): ?int
    {
        if (blank($state)) {
            return null;
        }

        return (int) Carbon::today()->diffInDays(Carbon::parse($state)->startOfDay(), false);
    }
}
