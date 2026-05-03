<?php

namespace App\Filament\Resources\MedicineStocks\Tables;

use App\Models\Medicine;
use App\Services\StockCardService;
use App\Filament\Pages\MedicineStockDetail;
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
            ->query(Medicine::query())
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Obat')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('dosage')
                    ->label('Dosis')
                    ->searchable(),
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
}
