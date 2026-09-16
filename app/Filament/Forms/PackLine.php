<?php

namespace App\Filament\Forms;

use App\Models\Medicine;
use App\Models\Unit;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\RawJs;
use Illuminate\Support\HtmlString;

/**
 * Baris pesanan/penerimaan dalam kemasan (rencana-revisi-2026-09 R1, P4, §1.5).
 *
 * Petugas mengetik apa yang tercetak di faktur — kemasan, jumlah kemasan, isi, harga per
 * kemasan — dan sistem yang mengalikan. Yang tersimpan: qty dan price dalam satuan jual,
 * plus jejak konversi (pack_unit_id, pack_size, pack_qty). Master obat tidak ditulis balik (M11).
 * Subtotal hanya tampilan (TextInput baca-saja), diperbarui lewat $set setiap isian berubah.
 */
class PackLine
{
    /** Isi bawaan dari master saat obat dipilih. */
    public static function applyMedicineDefaults(Set $set, ?int $medicineId, ?int $defaultPackQty = null, ?Get $get = null): void
    {
        $medicine = $medicineId ? Medicine::with(['unit', 'packUnit'])->find($medicineId) : null;

        if (! $medicine) {
            return;
        }

        $packSize = max(1, (int) $medicine->pack_size);
        $unitPrice = $medicine->latestPurchasePrice();
        $packQty = $defaultPackQty ?? (int) ceil(max(1, (int) $medicine->min_stock) / $packSize);
        $packPrice = $unitPrice === null ? null : round($unitPrice * $packSize, 2);

        $set('pack_unit_id', $medicine->pack_unit_id);
        $set('pack_size', $packSize);
        $set('pack_qty', $packQty);
        $set('pack_price', $packPrice);

        if ($get) {
            self::syncSubtotal($get, $set);
        } else {
            $set('subtotal', self::subtotal($packQty, $packPrice));
        }
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
                self::syncSubtotal($get, $set);
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
            ->live(onBlur: true)
            ->afterStateUpdated(fn (Get $get, Set $set) => self::syncSubtotal($get, $set));
    }

    /**
     * Isi per kemasan dalam satuan jual: angka + nama satuan jual obat sebagai akhiran
     * ("100 | Tablet"). Bawaan dari master obat, boleh diubah per baris; di bawahnya total baris
     * dalam satuan jual.
     */
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
            ->suffix(fn (Get $get) => self::medicineUnitName($get) ?: 'satuan jual')
            ->helperText(function (Get $get) {
                [$qty] = self::convert($get('pack_qty'), $get('pack_size'), null);
                $unit = self::medicineUnitName($get) ?: 'satuan jual';

                return $qty === null ? null : new HtmlString('= <b>'.number_format($qty, 0, ',', '.').' '.e($unit).'</b>');
            })
            ->afterStateUpdated(fn (Get $get, Set $set) => self::syncSubtotal($get, $set));
    }

    public static function packPriceInput(string $label = 'Harga per kemasan'): TextInput
    {
        // Masking rupiah: tampil 41.000 (pemisah ribuan titik), tersimpan 41000. Tanpa numeric():
        // cast angkanya membaca "41.000" sebagai 41 dan menulis balik ke kotak; validasi lewat rules
        // pada nilai yang sudah dibuang titiknya (mutateStateForValidation).
        return TextInput::make('pack_price')
            ->label($label)
            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
            ->stripCharacters('.')
            ->inputMode('numeric')
            ->rules(['numeric', 'min:0'])
            ->prefix('Rp')
            ->required()
            ->live(onBlur: true)
            ->afterStateUpdated(fn (Get $get, Set $set) => self::syncSubtotal($get, $set));
    }

    /** Subtotal = jumlah kemasan × harga per kemasan; kotak baca-saja, tidak disimpan. */
    public static function subtotalInput(): TextInput
    {
        return TextInput::make('subtotal')
            ->label('Subtotal')
            ->prefix('Rp')
            ->readOnly()
            ->dehydrated(false)
            ->extraInputAttributes(['class' => 'text-right font-semibold'])
            ->helperText(function (Get $get) {
                [, $price] = self::convert($get('pack_qty'), $get('pack_size'), $get('pack_price'));
                $unit = self::medicineUnitName($get) ?: 'satuan jual';

                return $price === null ? null : '@ Rp '.number_format($price, 2, ',', '.').' / '.$unit;
            });
    }

    protected static function syncSubtotal(Get $get, Set $set): void
    {
        $set('subtotal', self::subtotal($get('pack_qty'), $get('pack_price')));

        // Form induk yang punya kotak perkiraan total (PO) ikut diperbarui; form tanpa kotak itu (RO) dilewati.
        if ($get('../../estimated_total') !== null) {
            $set('../../estimated_total', self::estimatedTotal($get('../../items') ?? []));
        }
    }

    /** Σ (jumlah kemasan × harga per kemasan) semua baris, berformat rupiah bulat. */
    public static function estimatedTotal(array $rows): string
    {
        $total = 0.0;
        foreach ($rows as $row) {
            $qty = (int) ($row['pack_qty'] ?? 0);
            $price = self::toNumber($row['pack_price'] ?? null);
            if ($qty > 0 && $price !== null) {
                $total += $qty * $price;
            }
        }

        return number_format($total, 0, ',', '.');
    }

    /** Kotak baca-saja Σ subtotal baris untuk form PO (harga perkiraan, tidak disimpan). */
    public static function estimatedTotalInput(string $itemsField = 'items'): TextInput
    {
        return TextInput::make('estimated_total')
            ->label('Perkiraan total')
            ->prefix('Rp')
            ->readOnly()
            ->dehydrated(false)
            ->default('0')
            ->extraInputAttributes(['class' => 'text-right font-semibold'])
            ->helperText('Dari harga perkiraan; harga sebenarnya mengikuti faktur saat penerimaan.')
            ->afterStateHydrated(fn (Get $get, Set $set) => $set('estimated_total', self::estimatedTotal($get($itemsField) ?? [])));
    }

    /** Subtotal rupiah bulat berformat (jumlah kemasan × harga per kemasan); null bila belum lengkap. */
    public static function subtotal($packQty, $packPrice): ?string
    {
        $packQty = (int) $packQty;
        $packPrice = self::toNumber($packPrice);
        if ($packQty <= 0 || $packPrice === null) {
            return null;
        }

        return number_format($packQty * $packPrice, 0, ',', '.');
    }

    /**
     * Harga dari kotak bertopeng: "41.000" → 41000, "7.053,57" → 7053.57, 41000 → 41000.
     * Sebelum dehydrate, state masih berbentuk teks bertopeng (Alpine mengirim apa adanya).
     */
    public static function toNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $s = trim((string) $value);
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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
        $packPrice = self::toNumber($packPrice);
        $price = $packPrice === null ? null : round($packPrice / $packSize, 2);

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
        unset($data['pack_price'], $data['subtotal'], $data['conversion_preview']);

        return $data;
    }

    /** Saat form diisi dari DB: kembalikan harga per kemasan & subtotal supaya petugas melihat angka faktur. */
    public static function hydrate(array $data): array
    {
        $packSize = max(1, (int) ($data['pack_size'] ?? 1));
        $data['pack_price'] = isset($data['price']) ? round((float) $data['price'] * $packSize, 2) : null;
        $data['subtotal'] = self::subtotal($data['pack_qty'] ?? 0, $data['pack_price']);

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
