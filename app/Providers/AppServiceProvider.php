<?php

namespace App\Providers;

use Filament\Forms\Components\Field;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Filament memakai lcfirst(label) sebagai nama atribut validasi, sehingga singkatan
        // seperti "PBF" tampil "pBF" di pesan error. Pakai label apa adanya.
        Field::configureUsing(function (Field $field): void {
            $field->validationAttribute(fn (Field $component): string => (string) $component->getLabel());
        });
    }
}
