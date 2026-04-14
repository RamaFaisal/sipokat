<?php

namespace App\Filament\Resources\Medicines\Schemas;

use App\Models\MedicineCategories;
use App\Models\MedicineRack;
use App\Models\Supplier;
use App\Models\Unit;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class MedicineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    TextInput::make('name')
                        ->label('Nama Obat')
                        ->required()
                        ->afterStateUpdated(function (TextInput $component, ?string $state, callable $set) {
                            $set('code', strtoupper(substr($state, 0, 3)));
                        })
                        ->live(debounce: 1000),
                    TextInput::make('code')
                        ->label('Kode Obat')
                        ->required()
                        ->readOnly(),
                    TextInput::make('dosage')
                        ->label('Dosis Obat')
                        ->nullable()
                        ->placeholder('Contoh: 500mg, 10ml, dll'),
                ])
                ->columnSpanFull(),
                Select::make('category_id')
                    ->label('Kategori Obat')
                    ->required()
                    ->options(MedicineCategories::all()->pluck('name', 'id')),
                Select::make('unit_id')
                    ->label('Satuan Obat')
                    ->required()
                    ->options(Unit::all()->pluck('name', 'id')),
                Select::make('rack_id')
                    ->label('Rak Obat')
                    ->required()
                    ->options(MedicineRack::all()->pluck('name', 'id')),
                Select::make('supplier_id')
                    ->label('Supplier Obat')
                    ->required()
                    ->options(Supplier::all()->where('status', 'active')->pluck('name', 'id')),

                Grid::make(3)
                    ->schema([
                        TextInput::make('purchase_price')
                            ->label('Harga Beli Obat')
                            ->required()
                            ->numeric()
                            ->default(0.0),
                        TextInput::make('sale_price')
                            ->label('Harga Jual Obat')
                            ->required()
                            ->numeric()
                            ->default(0.0),
                        TextInput::make('min_stock')
                            ->label('Stok Minimal Obat')
                            ->required()
                            ->numeric()
                            ->default(0),
                    ])
                    ->columnSpanFull(),

                Grid::make(2)
                    ->schema([
                        FileUpload::make('photo')
                            ->label('Foto Obat')
                            ->directory('medicines')
                            ->image(),
                        Textarea::make('description')
                            ->label('Deskripsi Obat')
                            ->rows(2),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
