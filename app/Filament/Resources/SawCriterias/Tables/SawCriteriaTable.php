<?php

namespace App\Filament\Resources\SawCriterias\Tables;

use App\Models\SawCriteria;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class SawCriteriaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->badge()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->colors([
                        'danger' => 'cost',
                        'success' => 'benefit',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cost' => 'Cost',
                        'benefit' => 'Benefit',
                        default => $state,
                    }),
                TextColumn::make('weight')
                    ->label('Bobot')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->alignEnd(),
                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->tooltip('Toggle untuk aktif/non-aktif kriteria. Pastikan total bobot kriteria aktif = 1,000.')
                    ->beforeStateUpdated(function (SawCriteria $record, $state) {
                        if (! $state) {
                            return;
                        }

                        $othersTotal = (float) SawCriteria::query()
                            ->where('is_active', true)
                            ->where('id', '!=', $record->id)
                            ->sum('weight');

                        $projected = round($othersTotal + (float) $record->weight, 3);

                        if ($projected > 1.000) {
                            Notification::make()
                                ->danger()
                                ->title('Tidak bisa aktifkan kriteria')
                                ->body(sprintf(
                                    'Total bobot kriteria aktif akan jadi %.3f (maks 1,000). Bobot kriteria aktif lain: %.3f. Turunkan bobot kriteria.',
                                    $projected,
                                    $othersTotal,
                                ))
                                ->persistent()
                                ->send();

                            throw new \RuntimeException('Aktivasi dibatalkan: total bobot melebihi 1.000.');
                        }
                    }),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
