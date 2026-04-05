<?php

namespace App\Filament\Resources\Units\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Satuan')
                    ->required(),
                TextInput::make('alias')
                    ->label('Alias Satuan')
                    ->required(),
            ]);
    }
}
