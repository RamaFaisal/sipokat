<?php

namespace App\Filament\Resources\MedicineStocks\Tables;

use App\Filament\Pages\MedicineStockDetail;
use App\Models\Medicine;
use App\Models\MedicineStock;
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
            ->deferFilters(false)
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
            // Stok awal dan stok akhir periode juga lewat subquery. Sebelumnya tiap baris
            // memanggil StockCardService dua kali, jadi satu halaman 25 baris = 50 query.
            ->modifyQueryUsing(function (Builder $query, $livewire): Builder {
                [$awalPeriode, $akhirPeriode] = self::periode($livewire);

                return $query->addSelect([
                    'init_stock' => self::saldoSampai($awalPeriode->copy()->subDay()),
                    'current_stock' => self::saldoSampai($akhirPeriode),
                ]);
            })
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
                    ->numeric()
                    ->default(0)
                    ->alignEnd(),
                TextColumn::make('current_stock')
                    ->label('Stok Akhir Periode')
                    ->sortable()
                    ->numeric()
                    ->default(0)
                    ->alignEnd(),
                // ED **terdekat** yang masih bersisa, berbeda dari C3 SAW yang memakai batch
                // terjauh (CLAUDE.md §6). Yang ini menjawab "batch mana yang harus dihabiskan
                // duluan", bukan "sampai kapan stok ini bertahan".
                TextColumn::make('ed_terdekat')
                    ->label('Expired Date terdekat')
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
                                    ->mapWithKeys(fn ($year) => [$year => $year])
                            )
                            ->default(now()->year)
                            ->required()
                            ->live()
                            ->disablePlaceholderSelection(),

                        Select::make('month')
                            ->label('Bulan')
                            ->options([
                                1 => 'Januari',
                                2 => 'Februari',
                                3 => 'Maret',
                                4 => 'April',
                                5 => 'Mei',
                                6 => 'Juni',
                                7 => 'Juli',
                                8 => 'Agustus',
                                9 => 'September',
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
                    // Sengaja tidak menyaring baris: pilihan bulan hanya menentukan periode yang
                    // dipakai kolom Stok Awal dan Stok Akhir, dibaca di modifyQueryUsing().
                    ->query(fn (Builder $query): Builder => $query),
            ], FiltersLayout::AboveContent)
            ->recordActions([
                Action::make('detail')
                    ->url(fn ($record): string => MedicineStockDetail::getUrl(['record' => $record]))
                    ->label('Lihat Kartu Stok'),
            ])
            ->toolbarActions([
                //
            ]);
    }

    /** Periode yang sedang dipilih pada filter; bawaannya bulan berjalan.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function periode($livewire): array
    {
        $filter = $livewire->tableFilters['period'] ?? [];
        $tahun = is_numeric($filter['year'] ?? null) ? (int) $filter['year'] : now()->year;
        $bulan = is_numeric($filter['month'] ?? null) ? (int) $filter['month'] : now()->month;

        $awal = Carbon::create($tahun, $bulan, 1)->startOfMonth();

        return [$awal, $awal->copy()->endOfMonth()];
    }

    /** Saldo kartu stok (Σ D dikurangi Σ C) sampai tanggal tertentu, sebagai subquery. */
    private static function saldoSampai(Carbon $sampai): \Illuminate\Database\Query\Builder
    {
        return MedicineStock::query()
            ->selectRaw("coalesce(sum(case when type_account = 'D' then qty else -qty end), 0)")
            ->whereColumn('medicine_stocks.medicine_id', 'medicines.id')
            ->whereDate('date', '<=', $sampai->toDateString())
            ->toBase();
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
