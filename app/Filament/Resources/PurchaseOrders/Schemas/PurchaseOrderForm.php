<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Filament\Forms\PackLine;
use App\Models\Medicine;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * PO ramping (rencana-revisi-2026-09 §3.3): PBF, tanggal pesan, baris dalam kemasan dengan
 * harga perkiraan. Dibuat setelah ketersediaan dikonfirmasi ke sales (P1).
 */
class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pesanan')
                    ->description('Catatan internal pesanan ke satu PBF setelah ketersediaan dikonfirmasi lewat telepon/WA. Harga di sini perkiraan; harga sesungguhnya dicatat saat penerimaan dari faktur.')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('po_number')
                            ->label('Nomor PO')
                            ->required()
                            ->readOnly()
                            ->dehydrated()
                            ->default(fn () => self::generatePONumber())
                            ->unique(ignoreRecord: true),
                        Select::make('supplier_id')
                            ->label('PBF')
                            ->options(fn () => Supplier::where('status', 'active')->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        DatePicker::make('po_date')
                            ->label('Tanggal pesan')
                            ->default(now())
                            ->required(),
                    ]),

                Section::make('Daftar Obat')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->schema([
                                Select::make('medicine_id')
                                    ->label('Obat')
                                    ->columnSpan(3)
                                    ->options(fn () => Medicine::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    // Jumlah kemasan bawaan 1; ⌈min_stock ÷ isi⌉ hanya untuk PO dari ranking SAW (P5).
                                    ->afterStateUpdated(fn (Set $set, $state) => PackLine::applyMedicineDefaults($set, $state ? (int) $state : null, defaultPackQty: 1)),
                                // Urutan isian mengikuti cara petugas berpikir: satuan → jumlah kemasan → isi (sistem
                                // menampilkan totalnya dalam satuan jual) → harga per kemasan → subtotal di kanan.
                                PackLine::packUnitSelect()->columnSpan(2),
                                PackLine::packQtyInput('Jumlah kemasan')->columnSpan(2),
                                PackLine::packSizeInput()->columnSpan(2),
                                PackLine::packPriceInput('Harga per kemasan')->columnSpan(3),
                                PackLine::subtotalInput()->columnSpan(4),
                            ])
                            ->columns(16)
                            ->columnSpanFull()
                            ->addActionLabel('Tambah obat')
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => PackLine::dehydrate($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => PackLine::dehydrate($data))
                            ->mutateRelationshipDataBeforeFillUsing(fn (array $data) => PackLine::hydrate($data))
                            ->minItems(1),
                    ]),

                Hidden::make('created_by')
                    ->default(fn () => Auth::id()),
            ]);
    }

    /** PO{YYYYMMDD}-XXXX (D4). */
    public static function generatePONumber(?\DateTimeInterface $date = null): string
    {
        $prefix = 'PO'.($date ? $date->format('Ymd') : now()->format('Ymd')).'-';

        $last = PurchaseOrder::withTrashed()
            ->where('po_number', 'like', $prefix.'%')
            ->orderByDesc('po_number')
            ->value('po_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return sprintf('%s%04d', $prefix, $next);
    }
}
