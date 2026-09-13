<?php

namespace App\Filament\Resources\SawCalculations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SawCalculationHistoryTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('calculated_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->formatStateUsing(fn ($state) => '#'.$state)
                    ->sortable(),
                TextColumn::make('calculated_at')
                    ->label('Tanggal Hitung')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('trigger_type')
                    ->label('Trigger')
                    ->badge()
                    ->colors([
                        'info' => 'manual',
                        'success' => 'scheduled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'manual' => 'Manual',
                        'scheduled' => 'Terjadwal',
                        default => $state,
                    }),
                TextColumn::make('period_start')
                    ->label('Periode Mulai')
                    ->date('d M Y'),
                TextColumn::make('period_end')
                    ->label('Periode Akhir')
                    ->date('d M Y'),
                TextColumn::make('total_alternatives')
                    ->label('Jumlah Obat')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Dihitung Oleh')
                    ->placeholder('Sistem (scheduled)')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('trigger_type')
                    ->label('Tipe Trigger')
                    ->options([
                        'manual' => 'Manual',
                        'scheduled' => 'Terjadwal',
                    ]),
                Filter::make('calculated_at')
                    ->label('Rentang Tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari')->native(false),
                        DatePicker::make('until')->label('Sampai')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('calculated_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('calculated_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat Hasil'),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Hapus snapshot ini? Semua hasil ranking akan ikut terhapus.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalDescription('Hapus snapshot terpilih? Semua hasil ranking akan ikut terhapus.'),
                ]),
            ]);
    }
}
