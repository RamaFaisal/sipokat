<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Medicine;
use App\Models\Order;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Penjualan')
                    ->schema([
                        TextInput::make('order_number')
                            ->label('Nomor Order')
                            ->required()
                            ->readOnly()
                            ->default(self::generateOrderNumber()),
                        DatePicker::make('date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now()),
                        TextInput::make('customer_name')
                            ->label('Nama Pelanggan')
                            ->maxLength(255),
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required(),
                    ])->columns(2),
                Section::make('Detail Item')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('medicine_id')
                                    ->label('Obat')
                                    ->options(Medicine::pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $price = Medicine::find($state)?->sale_price ?? 0;
                                        $set('price', $price);
                                        $qty = $get('qty') ?? 1;
                                        $discount = $get('discount') ?? 0;
                                        $set('total', ($qty * $price) - $discount);
                                    }),
                                TextInput::make('qty')
                                    ->label('Qty')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $price = $get('price') ?? 0;
                                        $discount = $get('discount') ?? 0;
                                        $set('total', ($state * $price) - $discount);
                                    }),
                                TextInput::make('price')
                                    ->label('Harga')
                                    ->numeric()
                                    ->readOnly(),
                                TextInput::make('discount')
                                    ->label('Diskon')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $qty = $get('qty') ?? 1;
                                        $price = $get('price') ?? 0;
                                        $set('total', ($qty * $price) - $state);
                                    }),
                                TextInput::make('total')
                                    ->label('Total')
                                    ->numeric()
                                    ->readOnly(),
                            ])->columns(5),
                    ]),
                Section::make('Ringkasan')
                    ->schema([
                        TextInput::make('sub_total')
                            ->label('Sub Total')
                            ->numeric()
                            ->default(0),
                        TextInput::make('discount')
                            ->label('Diskon Global')
                            ->numeric()
                            ->default(0),
                        TextInput::make('tax')
                            ->label('Pajak')
                            ->numeric()
                            ->default(0),
                        TextInput::make('grand_total')
                            ->label('Grand Total')
                            ->numeric()
                            ->default(0),
                    ])->columns(4),
            ]);
    }

    protected static function generateOrderNumber(): string
    {
        $latest = Order::latest()->first();
        if (!$latest) {
            return 'ORD0001';
        }
        $number = (int) substr($latest->order_number, 3);
        return 'ORD' . str_pad($number + 1, 4, '0', STR_PAD_LEFT);
    }
}
