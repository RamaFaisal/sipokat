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
                    ->label('Kode Obat')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nama Obat')
                    ->searchable(),
                TextColumn::make('category_id')
                    ->label('Kategori Obat')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit_id')
                    ->label('Satuan Obat')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rack_id')
                    ->label('Rak Obat')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('purchase_price')
                    ->label('Harga Beli Obat')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sale_price')
                    ->label('Harga Jual Obat')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('min_stock')
                    ->label('Stok Minimal Obat')
                    ->numeric()
                    ->sortable(),
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
