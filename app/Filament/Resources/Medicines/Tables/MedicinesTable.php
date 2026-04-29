<?php

namespace App\Filament\Resources\Medicines\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MedicinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->sortable(),
                TextColumn::make('rack.name')
                    ->label('Rak')
                    ->sortable(),
                TextColumn::make('sale_price')
                    ->label('Harga Jual')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('stock_status')
                    ->label('Status Stok')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'available' => 'success',
                        'almost_empty' => 'warning',
                        'empty' => 'danger',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                    }),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
