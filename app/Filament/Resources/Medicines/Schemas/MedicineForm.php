<?php

namespace App\Filament\Resources\Medicines\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MedicineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                TextInput::make('category_id')
                    ->label('Kategori Obat')
                    ->required()
                    ->numeric(),
                TextInput::make('unit_id')
                    ->label('Satuan Obat')
                    ->required()
                    ->numeric(),
                TextInput::make('rack_id')
                    ->label('Rak Obat')
                    ->required()
                    ->numeric(),
                TextInput::make('supplier_id')
                    ->label('Supplier Obat')
                    ->required()
                    ->numeric(),
                FileUpload::make('photo')
                    ->label('Foto Obat')
                    ->directory('medicines')
                    ->image(),
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
                RichEditor::make('description')
                    ->label('Deskripsi Obat')
                    ->columnSpanFull(),
            ]);
    }
}
