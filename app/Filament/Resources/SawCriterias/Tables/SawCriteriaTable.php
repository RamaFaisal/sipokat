<?php

namespace App\Filament\Resources\SawCriterias\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
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
