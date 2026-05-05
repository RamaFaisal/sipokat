<?php

namespace App\Filament\Resources\ReceiveOrders\Schemas;

use App\Models\Medicine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceiveOrder;
use App\Models\ReceiveOrderItem;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ReceiveOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi')
                    ->columns(2)
                    ->schema([
                        TextInput::make('receive_order_number')
                            ->label('Nomor RO')
                            ->required()
                            ->default(function ($record) {
                                return 'RO' . str_pad(ReceiveOrder::withTrashed()->count() + 1, 4, '0', STR_PAD_LEFT);
                            })
                            ->readOnly()
                            ->disabled()
                            ->dehydrated(),
                        Select::make('purchase_order_id')
                            ->label('Nomor PO')
                            ->options(fn() => PurchaseOrder::query()->where('status_receive_order', '!=', 'received')->pluck('po_number', 'id'))
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                self::getPurchaseOrder($get, $set, $state);
                            }),
                        TextInput::make('supplier_name')
                            ->label('Nama Supplier')
                            ->live(onBlur: true)
                            ->readOnly()
                            ->dehydrated(false),
                        Hidden::make('supplier_id')
                            ->required(),
                        DatePicker::make('receive_date')
                            ->label('Tanggal')
                            ->default(now())
                            ->required(),
                        Textarea::make('description')
                            ->label('Keterangan')
                            ->columnSpanFull()
                            ->rows(3),
                    ])
                    ->columnSpanFull(),

                Section::make('RO Item')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Hidden::make('medicine_id')
                                    ->afterStateHydrated(function (Get $get, Set $set, $state) {
                                        $set('medicine_id', $get('medicine_id'));
                                    })
                                    ->required(),
                                TextInput::make('medicine_name')
                                    ->label('Nama Obat')
                                    ->readOnly()
                                    ->required(),
                                TextInput::make('medicine_dosage')
                                    ->label('Dosis')
                                    ->readOnly()
                                    ->required()
                                    ->dehydrated(false),
                                TextInput::make('qty')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        self::getValidateStock($get, $set);
                                    }),
                                TextInput::make('price')
                                    ->label('Harga')
                                    ->readOnly()
                                    ->prefix('Rp')
                                    ->required(),
                            ])
                            ->columns(4)
                            ->addable(false)
                            ->deletable(false)
                            ->live(onBlur: true),
                    ]),
            ]);
    }

    public static function getPurchaseOrder(Get $get, Set $set, $state)
    {
        $purchaseOrderId = $get('purchase_order_id');
        $purchaseOrderItems = PurchaseOrderItem::where('purchase_order_id', $purchaseOrderId)->get();
        $filteredItems = [];

        foreach ($purchaseOrderItems as $poItem) {
            $receiveOrderQty = ReceiveOrderItem::query()
                ->whereHas('receiveOrder', function ($query) use ($purchaseOrderId) {
                    $query->where('purchase_order_id', $purchaseOrderId)
                        ->whereIn('status', ['pending', 'completed']);
                })
                ->where('medicine_id', $poItem->medicine_id)
                ->sum('qty');

            if ($receiveOrderQty < $poItem->qty) {
                $remainingQty = $poItem->qty - $receiveOrderQty;

                $filteredItems[] = [
                    'medicine_id' => $poItem->medicine_id,
                    'medicine_name' => $poItem->medicine->name ?? null,
                    'medicine_dosage' => $poItem->medicine->dosage ?? null,
                    'qty' => $remainingQty,
                    'price' => $poItem->price ?? ($poItem->medicine->purchase_price ?? 0),
                ];
            }
        }

        if (!empty($filteredItems)) {
            $set('items', $filteredItems);
            $purchaseOrder = PurchaseOrder::find($purchaseOrderId);
            if ($purchaseOrder) {
                $set('supplier_id', $purchaseOrder->supplier_id);
                $set('supplier_name', $purchaseOrder->supplier->name ?? '');
            }
        } else {
            $set('items', []);
            if ($purchaseOrderId) {
                Notification::make()->danger()->title('Tidak ada item yang dapat diterima')->send();
            }
        }
    }

    public static function getValidateStock(Get $get, Set $set)
    {
        $poId = $get('../../purchase_order_id');
        $items = $get('../../items') ?? [];

        if (!$poId) {
            return;
        }

        $poItems = PurchaseOrderItem::where('purchase_order_id', $poId)
            ->get()
            ->keyBy('medicine_id');

        foreach ($items as $key => $item) {
            $medicineId = $item['medicine_id'] ?? null;
            $inputQty = (int) ($item['qty'] ?? 0);

            // Validasi 1: cek apakah item ada di PO
            $poItem = $poItems[$medicineId] ?? null;
            if (!$poItem) {
                continue;
            }

            if (!$medicineId || $inputQty <= 0) {
                $set("../../items.{$key}.qty", $poItem->qty);

                Notification::make()
                    ->warning()
                    ->title('Qty tidak valid')
                    ->body('Jumlah barang harus lebih dari 0')
                    ->send();
                continue;
            }

            // Validasi 2: cek qty terhadap sisa PO
            $receivedQty = ReceiveOrderItem::whereHas('receiveOrder', function ($q) use ($poId) {
                $q->where('purchase_order_id', $poId);
            })
                ->where('medicine_id', $medicineId)
                ->sum('qty');

            $remainingQty = max(0, $poItem->qty - $receivedQty);

            if ($inputQty > $remainingQty) {
                $set("../../items.{$key}.qty", $remainingQty);

                Notification::make()
                    ->danger()
                    ->title('Qty melebihi sisa PO')
                    ->body("Sisa {$poItem->medicine->name} yang belum diterima: {$remainingQty}")
                    ->send();
                continue;
            }

            // Validasi 3: cek qty terhadap total PO
            if ($inputQty > $poItem->qty) {
                $set("../../items.{$key}.qty", $poItem->qty);

                Notification::make()
                    ->danger()
                    ->title('Qty melebihi PO')
                    ->body("Jumlah {$poItem->medicine->name} pada PO hanya {$poItem->qty}")
                    ->send();
                continue;
            }
        }
    }
}
