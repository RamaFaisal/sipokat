<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageGeneralSettings extends SettingsPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $settings = GeneralSettings::class;
    protected static ?string $title = 'Pengaturan';
    protected static ?string $navigationLabel = 'Pengaturan';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Aplikasi')
                    ->description('Pengaturan umum informasi aplikasi.')
                    ->icon('heroicon-o-building-storefront')
                    ->schema([
                        TextInput::make('app_name')
                            ->label('Nama Aplikasi')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Grid::make(3)->schema([
                            TextInput::make('contact_email')
                                ->label('Email Kontak')
                                ->email()
                                ->maxLength(255)
                                ->prefixIcon(Heroicon::Envelope),

                            TextInput::make('contact_phone')
                                ->label('Nomor Telepon')
                                ->tel()
                                ->maxLength(20)
                                ->prefixIcon(Heroicon::Phone),

                            TextInput::make('website')
                                ->label('Website')
                                ->url()
                                ->maxLength(255)
                                ->prefixIcon(Heroicon::GlobeAlt),
                        ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
