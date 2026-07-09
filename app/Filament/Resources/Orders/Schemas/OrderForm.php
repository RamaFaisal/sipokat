<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Medicine;
use App\Models\Order;
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
use Filament\Support\RawJs;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('has_stock_error')
                    ->default(false)
                    ->dehydrated(true),
                Section::make('Informasi Order')
                    ->schema([
                        TextInput::make('order_code')
                            ->label('Nomor Order')
                            ->required()
                            ->readOnly()
                            ->dehydrated()
                            ->default(self::generateOrderCode()),
                        DatePicker::make('order_date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now()),
                        TextInput::make('no_payment')
                            ->label('Nomor Pembayaran')
                            ->required()
                            ->readOnly()
                            ->dehydrated()
                            ->default(self::generatePaymentNumber()),
                    ])->columns(3)
                    ->columnSpanFull(),
                Section::make('Detail Item')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Hidden::make('medicine_name'),
                                Select::make('medicine_id')
                                    ->label('Obat')
                                    ->options(fn () => Medicine::all()->mapWithKeys(fn ($medicine) => [$medicine->id => trim($medicine->name.' '.$medicine->dosage)]))
                                    ->searchable()
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $medicine = Medicine::find($state);
                                        $price = $medicine?->sale_price ?? 0;
                                        $set('medicine_name', $medicine ? trim($medicine->name.' '.$medicine->dosage) : null);
                                        $set('price', $price);
                                        self::updateItemTotal($set, $get, $price);
                                        self::updateGrandTotalFromItem($set, $get, $price);
                                    }),
                                TextInput::make('qty')
                                    ->label('Qty')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        self::updateItemTotal($set, $get);
                                        self::updateGrandTotalFromItem($set, $get);
                                    }),
                                TextInput::make('price')
                                    ->label('Harga')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->mask(RawJs::make('$money($input)'))
                                    ->stripCharacters(','),
                                TextInput::make('total')
                                    ->label('Total')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->mask(RawJs::make('$money($input)'))
                                    ->stripCharacters(',')
                                    ->dehydrated(),
                            ])
                            ->columns(4)
                            ->collapsible()
                            ->columnSpanFull()
                            ->addActionLabel('Tambah Obat')
                            ->minItems(1)
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::updateGrandTotal($set, $get)),
                    ])->columnSpanFull(),
                Section::make('Ringkasan')
                    ->schema([
                        Textarea::make('note')
                            ->label('Catatan')
                            ->rows(3),
                        TextInput::make('grand_total')
                            ->label('Grand Total')
                            ->numeric()
                            ->prefix('Rp')
                            ->readOnly()
                            ->dehydrated()
                            ->default(0)
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(','),
                    ])->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    protected static function generateOrderCode(): string
    {
        $date = now()->format('Ymd');
        $latest = Order::whereDate('order_date', now())->latest('id')->first();
        if (! $latest) {
            return 'ORD-'.$date.'0001';
        }
        $number = (int) substr($latest->order_code, -4);
        return 'ORD-'.$date.str_pad($number + 1, 4, '0', STR_PAD_LEFT);
    }

    protected static function generatePaymentNumber(): string
    {
        $date = now()->format('Ymd');
        $latest = Order::whereDate('order_date', now())->latest('id')->first();
        if (! $latest) {
            return 'PAY-'.$date.'0001';
        }
        $number = (int) substr($latest->no_payment, -4);
        return 'PAY-'.$date.str_pad($number + 1, 4, '0', STR_PAD_LEFT);
    }

    public static function updateItemTotal(Set $set, Get $get, ?float $overridePrice = null): void
    {
        $qty = (float) ($get('qty') ?? 0);
        $price = $overridePrice ?? (float) ($get('price') ?? 0);
        $set('total', $qty * $price);
    }

    public static function updateGrandTotalFromItem(Set $set, Get $get, ?float $overridePrice = null): void
    {
        $items = $get('../../items') ?? [];
        $currentQty = (float) ($get('qty') ?? 0);
        $currentPrice = $overridePrice ?? (float) ($get('price') ?? 0);
        $currentMedicineId = $get('medicine_id');

        $grandTotal = 0;
        $foundCurrent = false;

        foreach ($items as $item) {
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? 0);

            if (! $foundCurrent && ($item['medicine_id'] ?? null) == $currentMedicineId && $overridePrice !== null) {
                $price = $currentPrice;
                $qty = $currentQty;
                $foundCurrent = true;
            }

            $grandTotal += $qty * $price;
        }

        $set('../../grand_total', $grandTotal);
    }

    public static function updateGrandTotal(Set $set, Get $get): void
    {
        $items = $get('items') ?? [];
        $grandTotal = 0;

        foreach ($items as $item) {
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $grandTotal += $qty * $price;
        }

        $set('grand_total', $grandTotal);
    }
}
