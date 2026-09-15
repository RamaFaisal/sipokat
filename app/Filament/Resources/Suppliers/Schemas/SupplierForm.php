<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use App\Models\Supplier;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Supplier')
                    ->afterStateUpdated(function (?string $state, callable $set, ?Supplier $record) {
                        if ($record === null && filled($state)) {
                            $set('code', Supplier::nextCode($state));
                        }
                    })
                    ->live(debounce: 1000)
                    ->required(),
                TextInput::make('code')
                    ->label('Kode Supplier')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Otomatis dari inisial nama'),
                Textarea::make('address')
                    ->label('Alamat')
                    ->required()
                    ->columnSpanFull(),
                Grid::make(2)->schema([
                    TextInput::make('phone')
                        ->label('Telp')
                        ->placeholder('(024) 8664117'),
                    TextInput::make('fax')
                        ->label('Fax')
                        ->placeholder('(024) 8664123'),
                ])
                    ->columnSpanFull(),
                Hidden::make('status')
                    ->label('Status')
                    ->default('active')
                    ->required(),
            ]);
    }
}
