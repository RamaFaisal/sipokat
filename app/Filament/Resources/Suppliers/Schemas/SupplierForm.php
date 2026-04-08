<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->afterStateUpdated(function (TextInput $component, ?string $state, callable $set) {
                        $excludedWords = ['PT', 'CV', 'UD', 'PD', 'FIRMA', 'KOPERASI', 'YAYASAN'];
                        if (!empty($state)) {
                            $words = preg_split("/\s+/", trim($state));
                            $filtered = array_values(array_filter($words, function ($word) use ($excludedWords) {
                                return !in_array(strtoupper($word), $excludedWords);
                            }));
                            if (empty($filtered)) {
                                $filtered = $words;
                            }
                            $filtered = array_slice($filtered, 0, 3);
                            $kode = strtoupper(implode('', array_map(function ($word) {
                                return substr($word, 0, 1);
                            }, $filtered)));
                            if (count($filtered) === 1) {
                                $kode = strtoupper(substr($filtered[0], 0, 3));
                            }
                            $set('code', substr($kode, 0, 3));
                        }
                    })
                    ->live(debounce: 1000)
                    ->required(),
                TextInput::make('code')
                    ->label('Kode Supplier')
                    ->reactive()
                    ->required()
                    ->readOnly(),
                Textarea::make('address')
                    ->label('Alamat')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('email')
                    ->label('Email')
                    ->email(),
                TextInput::make('phone')
                    ->label('Nomor Telepon')
                    ->tel(),
                TextInput::make('pic')
                    ->label('Nama PIC')
                    ->required(),
                Hidden::make('status')
                    ->label('Status')
                    ->default('active')
                    ->required(),
            ]);
    }
}
