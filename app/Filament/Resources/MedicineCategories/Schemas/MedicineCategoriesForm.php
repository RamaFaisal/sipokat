<?php

namespace App\Filament\Resources\MedicineCategories\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;

class MedicineCategoriesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Kategori Obat')
                    ->required()
                    ->maxLength(255),
                RichEditor::make('description')
                    ->label('Deskripsi')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }
}
