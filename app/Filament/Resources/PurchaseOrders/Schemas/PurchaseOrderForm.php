<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Models\Medicine;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi PO')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('po_number')
                            ->label('Nomor Purchase Order')
                            ->required()
                            ->readOnly()
                            ->disabled()
                            ->default(self::generatePONumber())
                            ->unique(ignoreRecord: true),
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->options(fn() => Supplier::where('status', 'active')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        DatePicker::make('po_date')
                            ->label('Tanggal Transaksi')
                            ->default(now())
                            ->required(),
                        DatePicker::make('estimated_arrival')
                            ->label('Estimasi Kedatangan'),
                    ]),

                Section::make('Keterangan')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('description')
                            ->label('Keterangan')
                            ->rows(3),
                    ]),

                Section::make('Daftar Obat')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('medicine_id')
                                    ->label('Nama Obat')
                                    ->columnSpan(2)
                                    ->options(fn () => Medicine::all()->mapWithKeys(fn($medicine) => [$medicine->id => $medicine->name . ' ' . $medicine->dosage]))
                                    ->searchable()
                                    ->required()
                                    ->live(onBlur:true)
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        if($state) {
                                            $medicine = Medicine::find($state);
                                            $newPrice = $medicine?->purchase_price ?? 0;
                                            $set('price', $newPrice);
                                            $qty = (float) ($get('qty') ?? 0);
                                            $discountPercent = (float) ($get('discount') ?? 0);
                                            $itemTotal = ($qty * $newPrice) * (1 - ($discountPercent / 100));
                                            $set('total', $itemTotal);
                                            self::recalculateGrandTotalFromItem($set, $get, $newPrice);
                                        }
                                    }),
                                TextInput::make('qty')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        self::updateItemTotal($set, $get);
                                        self::recalculateGrandTotalFromItem($set, $get);
                                    }),
                                TextInput::make('price')
                                    ->label('Harga')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        self::updateItemTotal($set, $get);
                                        self::recalculateGrandTotalFromItem($set, $get);
                                    }),
                                TextInput::make('discount')
                                    ->label('Diskon (%)')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        self::updateItemTotal($set, $get);
                                        self::recalculateGrandTotalFromItem($set, $get);
                                    }),
                                TextInput::make('total')
                                    ->label('Total')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->readonly()
                                    ->dehydrated(),
                                TextInput::make('description')
                                    ->label('Keterangan')
                                    ->columnSpanFull(),
                            ])
                            ->columns(6)
                            ->collapsible()
                            ->columnSpanFull()
                            ->addActionLabel('Tambah Obat')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::updateGrandTotal($set, $get)),
                    ]),

                Section::make('Perhitungan')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('sub_total')
                            ->label('Sub Total')
                            ->numeric()
                            ->prefix('Rp')
                            ->readonly()
                            ->dehydrated()
                            ->default(0),
                        TextInput::make('discount')
                            ->label('Diskon (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->live(debounce: 500)
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::updateGrandTotal($set, $get)),
                        Grid::make(2)
                            ->schema([
                                Select::make('tax')
                                    ->label('Pajak %')
                                    ->options([
                                        '0' => '0%',
                                        '11' => '11%',
                                        '12' => '12%',
                                        '13' => '13%',
                                    ])
                                    ->default('0')
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::updateGrandTotal($set, $get)),
                                TextInput::make('total_tax')
                                    ->label('Pajak (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->live(onBlur:true)
                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::updateGrandTotal($set, $get)),
                        ]),
                        TextInput::make('shipping_cost')
                            ->label('Biaya Pengiriman')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->live(onBlur:true)
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::updateGrandTotal($set, $get)),
                        TextInput::make('other_cost')
                            ->label('Biaya Lain-lain')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->live(onBlur:true)
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::updateGrandTotal($set, $get)),
                        TextInput::make('grand_total')
                            ->label('Total')
                            ->numeric()
                            ->prefix('Rp')
                            ->readonly()
                            ->dehydrated()
                            ->default(0),
                    ]),

                Hidden::make('created_by')
                    ->default(fn() => Auth::user()->id),
            ]);
    }

    protected static function generatePONumber(): string
    {
        $lastRecord = PurchaseOrder::withTrashed()
            ->orderByDesc('po_number')
            ->first();
        $nextId = $lastRecord ? (int) str_replace('PO', '', $lastRecord->po_number) + 1 : 1;

        return 'PO' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    }

    public static function updateItemTotal(Set $set, Get $get, ?float $overridePrice = null): void
    {
        $qty = (float) ($get('qty') ?? 0);
        $price = $overridePrice ?? (float) ($get('price') ?? 0);
        $discountPercent = (float) ($get('discount') ?? 0);

        $itemTotal = ($qty * $price) * (1 - ($discountPercent / 100));
        $set('total', $itemTotal);
    }

    public static function recalculateGrandTotalFromItem(Set $set, Get $get, ?float $overridePrice = null): void
    {
        $items = $get('../../items') ?? [];
        $currentQty = (float) ($get('qty') ?? 0);
        $currentPrice = $overridePrice ?? (float) ($get('price') ?? 0);
        $currentDiscount = (float) ($get('discount') ?? 0);
        $currentMedicineId = $get('medicine_id');

        $subTotal = 0;
        $foundCurrent = false;

        foreach ($items as $item) {
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $discountPercent = (float) ($item['discount'] ?? 0);

            if (!$foundCurrent && ($item['medicine_id'] ?? null) == $currentMedicineId && $overridePrice !== null) {
                $price = $currentPrice;
                $qty = $currentQty;
                $discountPercent = $currentDiscount;
                $foundCurrent = true;
            }

            $itemTotal = ($qty * $price) * (1 - ($discountPercent / 100));
            $subTotal += $itemTotal;
        }

        $set('../../sub_total', $subTotal);

        $discountRp = (float) ($get('../../discount') ?? 0);
        $taxType = $get('../../tax');
        $shippingCost = (float) ($get('../../shipping_cost') ?? 0);
        $otherCost = (float) ($get('../../other_cost') ?? 0);

        $totalTax = 0;
        if (is_numeric($taxType) && $taxType > 0) {
            $totalTax = ($subTotal - $discountRp) * ($taxType / 100);
            $set('../../total_tax', $totalTax);
        } else {
            $totalTax = (float) ($get('../../total_tax') ?? 0);
        }

        $grandTotal = ($subTotal - $discountRp) + $totalTax + $shippingCost + $otherCost;
        $set('../../grand_total', $grandTotal);
    }

    public static function updateGrandTotal(Set $set, Get $get): void
    {
        $items = $get('items') ?? [];
        $subTotal = 0;

        foreach ($items as $item) {
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $discountPercent = (float) ($item['discount'] ?? 0);

            $itemTotal = ($qty * $price) * (1 - ($discountPercent / 100));
            $subTotal += $itemTotal;
        }

        $set('sub_total', $subTotal);

        $discountRp = (float) ($get('discount') ?? 0);
        $taxType = $get('tax');
        $shippingCost = (float) ($get('shipping_cost') ?? 0);
        $otherCost = (float) ($get('other_cost') ?? 0);

        $totalTax = 0;
        if (is_numeric($taxType) && $taxType > 0) {
            $totalTax = ($subTotal - $discountRp) * ($taxType / 100);
            $set('total_tax', $totalTax);
        } else {
            $totalTax = (float) ($get('total_tax') ?? 0);
        }

        $grandTotal = ($subTotal - $discountRp) + $totalTax + $shippingCost + $otherCost;
        $set('grand_total', $grandTotal);
    }
}
