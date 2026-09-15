<?php

namespace App\Filament\Forms;

use App\Models\Medicine;
use App\Models\Unit;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;

/**
 * Baris pesanan/penerimaan dalam kemasan (rencana-revisi-2026-09 R1, P4, §1.5).
 *
 * Petugas mengetik apa yang tercetak di faktur — kemasan, isi, jumlah kemasan, harga per
 * kemasan — dan sistem yang mengalikan. Yang tersimpan: qty dan price dalam satuan jual,
 * plus jejak konversi (pack_unit_id, pack_size, pack_qty). Master obat tidak ditulis balik (M11).
 */
class PackLine
{
    /** Isi bawaan dari master saat obat dipilih. */
    public static function applyMedicineDefaults(Set $set, ?int $medicineId, ?int $defaultPackQty = null): void
    {
        $medicine = $medicineId ? Medicine::with(['unit', 'packUnit'])->find($medicineId) : null;

        if (! $medicine) {
            return;
        }

        $packSize = max(1, (int) $medicine->pack_size);
        $unitPrice = $medicine->latestPurchasePrice();

        $set('pack_unit_id', $medicine->pack_unit_id);
        $set('pack_size', $packSize);
        $set('pack_qty', $defaultPackQty ?? (int) ceil(max(1, (int) $medicine->min_stock) / $packSize));
        $set('pack_price', $unitPrice === null ? null : round($unitPrice * $packSize, 2));
    }

    public static function packUnitSelect(): Select
    {
        return Select::make('pack_unit_id')
            ->label('Satuan input')
            ->options(fn () => Unit::query()->orderBy('name')->pluck('name', 'id'))
            ->required()
            ->live()
            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                // Memilih satuan jual obat itu sendiri = eceran: isi 1.
                if ($state && (int) $state === self::medicineUnitId($get)) {
                    $set('pack_size', 1);
                }
            });
    }

    /** Isi per kemasan: bawaan dari master obat, boleh diubah per baris. Di bawahnya tampil total dalam satuan jual. */
    public static function packSizeInput(): TextInput
    {
        return TextInput::make('pack_size')
            ->label('Isi per kemasan')
            ->numeric()
            ->integer()
            ->minValue(1)
            ->default(1)
            ->required()
            ->live(onBlur: true)
            ->disabled(fn (Get $get) => $get('pack_unit_id') && (int) $get('pack_unit_id') === self::medicineUnitId($get))
            ->dehydrated()
            ->suffix(fn (Get $get) => self::medicineUnitName($get))
            ->helperText(function (Get $get) {
                [$qty] = self::convert($get('pack_qty'), $get('pack_size'), null);
                $unit = self::medicineUnitName($get) ?: 'satuan jual';

                return $qty === null ? null : new HtmlString('= <b>'.number_format($qty, 0, ',', '.').' '.e($unit).'</b>');
            });
    }

    public static function packQtyInput(string $label = 'Jumlah'): TextInput
    {
        return TextInput::make('pack_qty')
            ->label($label)
            ->numeric()
            ->integer()
            ->minValue(1)
            ->default(1)
            ->required()
            ->live(onBlur: true);
    }

    public static function packPriceInput(string $label = 'Harga per satuan input'): TextInput
    {
        return TextInput::make('pack_price')
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->prefix('Rp')
            ->required()
            ->live(onBlur: true);
    }

    /** Subtotal baris (jumlah kemasan × harga per kemasan) di kanan; di bawahnya harga per satuan jual. Tidak disimpan. */
    public static function subtotalPreview(): Placeholder
    {
        return Placeholder::make('conversion_preview')
            ->label('Subtotal')
            ->content(function (Get $get) {
                [$qty, $price] = self::convert($get('pack_qty'), $get('pack_size'), $get('pack_price'));
                $unit = self::medicineUnitName($get) ?: 'satuan jual';

                if ($qty === null || $price === null) {
                    return new HtmlString('<div class="text-right text-gray-400">—</div>');
                }

                return new HtmlString(sprintf(
                    '<div class="text-right"><div class="font-semibold text-lg">Rp %s</div><div class="text-xs text-gray-500">@ Rp %s / %s</div></div>',
                    number_format($qty * $price, 0, ',', '.'),
                    number_format($price, 2, ',', '.'),
                    e($unit)
                ));
            });
    }

    /** @deprecated pakai subtotalPreview(); dipertahankan untuk pemanggil lama. */
    public static function conversionPreview(): Placeholder
    {
        return self::subtotalPreview();
    }

    /**
     * Hitung qty & price dalam satuan jual dari input kemasan.
     *
     * @return array{0:?int,1:?float}
     */
    public static function convert($packQty, $packSize, $packPrice): array
    {
        $packQty = (int) $packQty;
        $packSize = max(1, (int) $packSize);

        if ($packQty <= 0) {
            return [null, null];
        }

        $qty = $packQty * $packSize;
        $price = ($packPrice === null || $packPrice === '') ? null : round((float) $packPrice / $packSize, 2);

        return [$qty, $price];
    }

    /** Sebelum simpan: isi qty & price satuan jual dari jejak kemasan. */
    public static function dehydrate(array $data): array
    {
        [$qty, $price] = self::convert($data['pack_qty'] ?? 0, $data['pack_size'] ?? 1, $data['pack_price'] ?? null);

        $data['pack_size'] = max(1, (int) ($data['pack_size'] ?? 1));
        $data['pack_qty'] = (int) ($data['pack_qty'] ?? 0);
        $data['qty'] = $qty ?? 0;
        $data['price'] = $price ?? 0;
        unset($data['pack_price'], $data['conversion_preview']);

        return $data;
    }

    /** Saat form diisi dari DB: kembalikan harga per kemasan supaya petugas melihat angka faktur. */
    public static function hydrate(array $data): array
    {
        $packSize = max(1, (int) ($data['pack_size'] ?? 1));
        $data['pack_price'] = isset($data['price']) ? round((float) $data['price'] * $packSize, 2) : null;

        return $data;
    }

    protected static function medicineUnitId(Get $get): ?int
    {
        $medicineId = $get('medicine_id');

        return $medicineId ? (int) Medicine::query()->whereKey($medicineId)->value('unit_id') : null;
    }

    protected static function medicineUnitName(Get $get): string
    {
        $unitId = self::medicineUnitId($get);

        return $unitId ? (string) Unit::query()->whereKey($unitId)->value('name') : '';
    }
}
