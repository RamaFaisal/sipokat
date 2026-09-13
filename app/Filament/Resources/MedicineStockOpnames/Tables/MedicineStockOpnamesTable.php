<?php

namespace App\Filament\Resources\MedicineStockOpnames\Tables;

use App\Models\MedicineStockOpname;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MedicineStockOpnamesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(MedicineStockOpname::whereNull('deleted_at'))
            ->columns([
                TextColumn::make('opname_number')
                    ->label('Nomor Opname')
                    ->searchable(),
                TextColumn::make('opname_date')
                    ->label('Tanggal Opname')
                    ->dateTime('d F Y')
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
