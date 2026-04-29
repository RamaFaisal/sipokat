<?php

namespace App\Filament\Resources\Medicines\Schemas;

use App\Models\Medicine;
use App\Models\MedicineCategories;
use App\Models\MedicineRack;
use App\Models\Supplier;
use App\Models\Unit;
use App\Settings\GeneralSettings;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class MedicineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    TextInput::make('name')
                        ->label('Nama Obat')
                        ->required()
                        ->autocapitalize('words')
                        ->placeholder('Contoh: PARACETAMOL')
                        ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                        ->dehydrateStateUsing(fn ($state) => strtoupper($state))
                        ->afterStateUpdated(function (TextInput $component, ?string $state, callable $set, $get) {
                            self::generateCode($set, $get);
                        })
                        ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule, $get) {
                            return $rule->where('dosage', $get('dosage'));
                        })
                        ->validationMessages([
                            'unique' => 'Obat dengan nama dan dosis ini sudah terdaftar di sistem.',
                        ])
                        ->live(onBlur: true),
                    TextInput::make('code')
                        ->label('Kode Obat')
                        ->required()
                        ->readOnly()
                        ->placeholder('Kode Obat akan otomatis tergenerate.'),
                    TextInput::make('dosage')
                        ->label('Dosis Obat')
                        ->afterStateUpdated(function (callable $set, $get) {
                            self::generateCode($set, $get);
                        })
                        ->live(onBlur: true)
                        ->placeholder('Contoh: 500mg, 10ml, dll'),
                ])
                ->columnSpanFull(),

                Grid::make(3)
                    ->schema([
                        Select::make('category_id')
                            ->label('Kategori Obat')
                            ->required()
                            ->afterStateUpdated(function (callable $set, $get) {
                                self::generateCode($set, $get);
                                })
                            ->live(onBlur: true)
                            ->options(MedicineCategories::all()->pluck('name', 'id')),
                        Select::make('unit_id')
                            ->label('Satuan Obat')
                            ->required()
                            ->afterStateUpdated(function (callable $set, $get) {
                                self::generateCode($set, $get);
                            })
                            ->live(onBlur: true)
                            ->options(Unit::all()->pluck('name', 'id')),
                        Select::make('rack_id')
                            ->label('Rak Obat')
                            ->required()
                            ->options(MedicineRack::all()->pluck('name', 'id')),
                    ])
                    ->columnSpanFull(),
                
                Grid::make(3)
                    ->schema([
                        TextInput::make('purchase_price')
                            ->label('Harga Beli Obat')
                            ->required()
                            ->prefix('Rp')
                            ->numeric()
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(','),
                        TextInput::make('sale_price')
                            ->label('Harga Jual Obat')
                            ->required()
                            ->prefix('Rp')
                            ->numeric()
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(','),
                        TextInput::make('min_stock')
                            ->label('Stok Minimal Obat')
                            ->required()
                            ->numeric()
                            ->default(0),
                    ])
                    ->columnSpanFull(),

                Grid::make(2)
                    ->schema([
                        FileUpload::make('photo')
                            ->label('Foto Obat')
                            ->directory('medicines')
                            ->image(),
                        Textarea::make('description')
                            ->label('Deskripsi Obat')
                            ->placeholder('Deskripsi tentang obat')
                            ->rows(2),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function generateCode(callable $set, $get)
    {
        $name = $get('name');
        $dosage = $get('dosage');
        $categoryId = $get('category_id') ?: null;
        $unitId = $get('unit_id') ?: null;
        if (! ($name && $categoryId && $unitId)) {
            return;
        }
        $appName = strtoupper(substr(app(GeneralSettings::class)->app_name, 0, 3));
        $namePrefix = strtoupper(substr($name, 0, 4));
        $conflict = Medicine::where('id', '!=', $get('id'))
            ->whereRaw('UPPER(SUBSTRING(name, 1, 4)) = ?', [$namePrefix])
            ->where('name', '!=', $name)
            ->exists();
        if ($conflict) {
            $namePrefix = strtoupper(substr($name, 0, 5));
        }
        if ($dosage) {
            $dosageNumber = preg_replace('/[^0-9]/', '', $dosage);
        } else {
            $dosageNumber = 'GEN';
        }
        $category = MedicineCategories::find($categoryId);
        $categoryCode = strtoupper(substr($category->name, 0, 3));
        $unit = Unit::find($unitId);
        $unitAlias = strtoupper($unit->alias ?? substr($unit->name, 0, 3));
        $baseCode = $appName . '/' . $namePrefix . $dosageNumber . '/' . $categoryCode . '/' . $unitAlias;
        $lastRecord = Medicine::where('code', 'like', $baseCode . '/%')
            ->orderBy('code', 'desc')
            ->first();
        if ($lastRecord) {
            $lastNumber = (int) substr($lastRecord->code, strrpos($lastRecord->code, '/') + 1);
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }
        $set('code', $baseCode . '/' . $newNumber);
    }
}
