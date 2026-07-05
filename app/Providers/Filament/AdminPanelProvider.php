<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use App\Filament\Widgets\ExpiringMedicinesWidget;
use App\Filament\Widgets\LowStockMedicinesWidget;
use App\Filament\Widgets\PendingPurchaseOrdersWidget;
use App\Filament\Widgets\SalesSummaryWidget;
use App\Filament\Widgets\SawTop10RestockWidget;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Resma\FilamentAwinTheme\FilamentAwinTheme;
use App\Settings\GeneralSettings;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Icons\Heroicon;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(false)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->darkMode(false)
            ->font('Poppins')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                SawTop10RestockWidget::class,
                LowStockMedicinesWidget::class,
                ExpiringMedicinesWidget::class,
                SalesSummaryWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationLabel('Role')
                    ->navigationSort(100)
                    ->navigationGroup('Manajemen Pengguna')
                    ->gridColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 4,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
                FilamentAwinTheme::make()
                    ->primaryColor(Color::Emerald),
            ])
            ->navigationGroups([
                'Inventory',
                'Penjualan',
                'Pembelian',
                'Master Data',
                'SPK Restock',
                'Laporan',
                'Manajemen Pengguna'
            ])
            ->spa()
            ->globalSearch(false)
            ->databaseNotifications()
            ->authMiddleware([
                Authenticate::class,
            ])
            ->brandName(fn(GeneralSettings $settings) => $settings->app_name ?? 'SIPOKAT')
            ->brandLogo(asset('assets/logo-sipokat.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('assets/favicon-sipokat.png'))
            ->viteTheme('resources/css/filament/admin/theme.css');
    }
}
