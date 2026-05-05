<?php

namespace App\Filament\Resources\MedicineStockOpnames\Schemas;

use App\Models\Medicine;
use App\Models\MedicineStockOpname;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class MedicineStockOpnameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi')
                    ->schema([
                        TextInput::make('opname_number')
                            ->label('Nomor Opname')
                            ->required()
                            ->readOnly()
                            ->dehydrated()
                            ->default(function ($record){
                                if($record){
                                    return $record->opname_number;
                                }
                                return 'OPM' . str_pad(MedicineStockOpname::withTrashed()->count() + 1, 4, '0', STR_PAD_LEFT);
                            }),
                        DatePicker::make('opname_date')
                            ->label('Tanggal Opname')
                            ->required()
                            ->default(now()),
                        Textarea::make('description')
                            ->label('Keterangan')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Detail Opname')
                    ->schema([
                        Hidden::make('created_by')
                            ->default(auth()->user()->id),
                        Repeater::make('medicineStockOpnameItems')
                            ->label('Item Obat')
                            ->schema([
                                Select::make('medicine_id')
                                    ->label('Nama Obat & Dosis')
                                    ->options(Medicine::all()->mapWithKeys(fn($m) => [
                                        $m->id => $m->dosage ? "{$m->name} - {$m->dosage}" : $m->name
                                    ]))
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, ?string $state) {
                                        $medicineItems = $get('medicine_items') ?? [];

                                        foreach ($medicineItems as $index => $item) {
                                            if (isset($item['qty']) && $item['qty'] && $state) {
                                                $hpp = self::amountHpp($state, $item['qty']);
                                                $set("medicine_items.{$index}.hpp", $hpp);
                                            }
                                        }
                                    })
                                    ->columnSpanFull(),
                                Repeater::make('medicine_items')
                                    ->label('Detail item')
                                    ->schema([
                                        TextInput::make('qty')
                                            ->label('Jumlah')
                                            ->numeric()
                                            ->reactive()
                                            ->default(1)
                                            ->minValue(1)
                                            ->afterStateUpdated(function ($set, $get, ?string $state) {
                                                $medicineId = $get('../../medicine_id');
                                                if ($medicineId && $state) {
                                                    $set('hpp', self::amountHpp($medicineId, $state));
                                                } else {
                                                    $set('hpp', 0);
                                                }
                                            })
                                            ->required(),

                                        Select::make('type_account')
                                            ->label('Jenis Akun')
                                            ->options([
                                                'D' => 'Debit (Masuk)',
                                                'C' => 'Credit (Keluar)',
                                            ])
                                            ->default('D')
                                            ->required(),
                                        TextInput::make('hpp')
                                            ->label('HPP')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->default(0)
                                            ->required(),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull()
                                    ->defaultItems(1)
                                    ->addActionLabel('Tambah Detail')
                                    ->reorderable(false)
                            ])
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->addActionLabel('Tambah Obat')
                            ->reorderable(false)
                            ->collapsible()
                            ->cloneable(),
                    ])
                    ->columnSpanFull()
            ]);
    }

    public static function amountHpp($medicine_id, $qty): float
    {
        if (!$medicine_id || !$qty) {
            return 0;
        }

        $medicine = \App\Models\Medicine::find($medicine_id);
        $purchasePrice = $medicine->purchase_price ?? 0;

        return $purchasePrice * floatval($qty);
    }
}
