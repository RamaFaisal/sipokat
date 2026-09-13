<?php

namespace App\Filament\Imports;

use App\Models\Medicine;
use App\Models\MedicineCategories;
use App\Models\Unit;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\ValidationException;

/**
 * Import master obat. Obat dikenali dari nama (dedup), kode dibuat otomatis.
 * Tidak ada kolom harga: harga hidup di penerimaan (RO), bukan di master.
 */
class MedicineImporter extends Importer
{
    protected static ?string $model = Medicine::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Nama Obat')
                ->exampleHeader('Nama Obat')
                ->guess(['Nama Obat', 'Nama', 'name'])
                ->example('ALLOPURINOL 100MG IFI')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('category_name')
                ->label('Kategori')
                ->exampleHeader('Kategori')
                ->guess(['Nama Kategori', 'Kategori', 'category_name', 'category'])
                ->example('Obat Keras')
                ->requiredMapping()
                ->rules(['required', 'string']),
            ImportColumn::make('unit_name')
                ->label('Satuan Jual')
                ->exampleHeader('Satuan Jual')
                ->guess(['Satuan Jual', 'Satuan', 'Unit', 'unit_name', 'unit'])
                ->example('Strip')
                ->requiredMapping()
                ->rules(['required', 'string']),
            ImportColumn::make('pack_unit_name')
                ->label('Kemasan Pembelian')
                ->exampleHeader('Kemasan Pembelian')
                ->guess(['Kemasan Pembelian', 'Kemasan', 'pack_unit_name', 'pack_unit'])
                ->example('Box')
                ->requiredMapping()
                ->rules(['required', 'string']),
            ImportColumn::make('pack_size')
                ->label('Isi per Kemasan')
                ->exampleHeader('Isi per Kemasan')
                ->guess(['Isi per Kemasan', 'Isi Kemasan', 'Isi', 'pack_size'])
                ->example('10')
                ->numeric()
                ->requiredMapping()
                ->rules(['required', 'integer', 'min:1']),
            ImportColumn::make('min_stock')
                ->label('Stok Minimum')
                ->exampleHeader('Stok Minimum')
                ->guess(['Stok Minimum', 'Min Stok', 'Batas Waspada', 'min_stock'])
                ->example('20')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:1']),
            ImportColumn::make('status')
                ->label('Status (active/inactive)')
                ->exampleHeader('Status')
                ->guess(['Status', 'status'])
                ->example('active')
                ->castStateUsing(fn ($state) => in_array($state, ['active', 'inactive'], true) ? $state : 'active')
                ->rules(['nullable', 'in:active,inactive']),
        ];
    }

    public function resolveRecord(): ?Medicine
    {
        $name = Medicine::normalizeName((string) ($this->data['name'] ?? ''));
        $categoryName = trim((string) ($this->data['category_name'] ?? ''));
        $unitName = trim((string) ($this->data['unit_name'] ?? ''));
        $packUnitName = trim((string) ($this->data['pack_unit_name'] ?? ''));

        $category = MedicineCategories::whereRaw('LOWER(name) = ?', [strtolower($categoryName)])->first();
        if (! $category) {
            throw ValidationException::withMessages([
                'category_name' => "Kategori \"{$categoryName}\" tidak ditemukan.",
            ]);
        }

        $unit = Unit::whereRaw('LOWER(name) = ?', [strtolower($unitName)])->first();
        if (! $unit) {
            throw ValidationException::withMessages([
                'unit_name' => "Satuan \"{$unitName}\" tidak ditemukan di master Satuan.",
            ]);
        }

        $packUnit = Unit::whereRaw('LOWER(name) = ?', [strtolower($packUnitName)])->first();
        if (! $packUnit) {
            throw ValidationException::withMessages([
                'pack_unit_name' => "Kemasan \"{$packUnitName}\" tidak ditemukan di master Satuan.",
            ]);
        }

        // Dedup berdasarkan nama — satu obat fisik hanya boleh punya satu baris.
        $medicine = Medicine::firstOrNew(['name' => $name]);

        $medicine->name = $name;
        $medicine->category_id = $category->id;
        $medicine->unit_id = $unit->id;
        $medicine->pack_unit_id = $packUnit->id;

        if (! $medicine->exists) {
            $medicine->stock_status = 'empty';
        }

        // min_stock kosong → biarkan model mengisi bawaan per satuan saat creating.
        if (blank($this->data['min_stock'] ?? null)) {
            unset($this->data['min_stock']);
        }

        unset($this->data['name'], $this->data['category_name'], $this->data['unit_name'], $this->data['pack_unit_name']);

        return $medicine;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import obat selesai. '.number_format($import->successful_rows).' baris berhasil diimport.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' baris gagal diimport.';
        }

        return $body;
    }
}
