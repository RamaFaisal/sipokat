<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Supplier')
                    ->required(),
                TextInput::make('address')
                    ->label('Alamat')
                    ->required(),
                TextInput::make('phone')
                    ->label('Nomor Telepon')
                    ->required(),
                TextInput::make('email')
                    ->label('Email')
                    ->required(),
            ]);
    }
}
