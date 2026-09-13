<?php

namespace App\Filament\Resources\Medicines\Schemas;

use App\Models\Medicine;
use App\Models\MedicineCategories;
use App\Models\Unit;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Master obat versi ramping (rencana-revisi-2026-09 §1.3): lima field diketik,
 * tidak ada rupiah. Kode dibuat model saat simpan; harga hidup di transaksi.
 */
class MedicineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Obat')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('name')
                                ->label('Nama Obat')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Contoh: ALLOPURINOL 100MG IFI')
                                ->helperText('Tulis persis seperti tercetak di faktur PBF, termasuk kekuatan dan merek.')
                                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                ->dehydrateStateUsing(fn ($state) => Medicine::normalizeName($state))
                                ->unique(
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn ($rule) => $rule->whereNull('deleted_at'),
                                )
                                ->validationMessages([
                                    'unique' => 'Obat dengan nama ini sudah terdaftar.',
                                ])
                                ->columnSpan(2),
                            Select::make('category_id')
                                ->label('Kategori')
                                ->required()
                                ->options(MedicineCategories::query()->orderBy('name')->pluck('name', 'id')),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Satuan dan Kemasan')
                    ->description('Satuan jual dipakai saat menjual dan menghitung stok. Kemasan pembelian adalah satuan yang tertulis di faktur PBF; sistem mengonversinya ke satuan jual saat penerimaan.')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('unit_id')
                                ->label('Satuan jual')
                                ->required()
                                ->live()
                                ->options(Unit::query()->orderBy('name')->pluck('name', 'id'))
                                ->afterStateUpdated(function (callable $set, callable $get) {
                                    self::refreshDefaultMinStock($set, $get);
                                }),
                            Select::make('pack_unit_id')
                                ->label('Kemasan pembelian')
                                ->required()
                                ->options(Unit::query()->orderBy('name')->pluck('name', 'id'))
                                ->helperText('Mis. Box, Karton, Kaleng'),
                            TextInput::make('pack_size')
                                ->label('Isi per kemasan')
                                ->required()
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->default(1)
                                ->live(onBlur: true)
                                ->suffix(fn (callable $get) => self::unitName($get('unit_id')))
                                ->helperText('1 kemasan pembelian = berapa satuan jual. Isi 1 bila kemasan sama dengan satuan jual.')
                                ->afterStateUpdated(function (callable $set, callable $get) {
                                    self::refreshDefaultMinStock($set, $get);
                                }),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Pengendalian Stok')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('min_stock')
                                ->label('Stok minimum (batas waspada)')
                                ->required()
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->suffix(fn (callable $get) => self::unitName($get('unit_id')))
                                ->helperText('Bawaan: Strip 20, satuan lain = isi satu kemasan. Dipakai notifikasi dan sebagai pembanding stok pada perhitungan prioritas.'),
                            Select::make('status')
                                ->label('Status')
                                ->required()
                                ->options([
                                    'active' => 'Aktif',
                                    'inactive' => 'Tidak Aktif',
                                ])
                                ->default('active'),
                            TextInput::make('code')
                                ->label('Kode')
                                ->disabled()
                                ->dehydrated(false)
                                ->placeholder('Otomatis saat disimpan'),
                        ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function unitName($unitId): string
    {
        return $unitId ? (string) Unit::query()->whereKey($unitId)->value('name') : '';
    }

    /**
     * Isi min_stock dengan bawaan per satuan hanya bila user belum mengetiknya sendiri
     * (masih kosong, atau masih sama dengan bawaan sebelumnya).
     */
    protected static function refreshDefaultMinStock(callable $set, callable $get): void
    {
        $unitId = $get('unit_id') ? (int) $get('unit_id') : null;
        $packSize = (int) ($get('pack_size') ?: 1);
        $current = $get('min_stock');

        $default = Medicine::defaultMinStock($unitId, $packSize);

        if (blank($current) || (int) $current === Medicine::DEFAULT_MIN_STOCK_STRIP || (int) $current === max(1, $packSize)) {
            $set('min_stock', $default);
        }
    }
}
