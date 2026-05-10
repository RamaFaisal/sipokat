<?php

namespace App\Filament\Imports;

use App\Models\Medicine;
use App\Models\MedicineCategories;
use App\Models\MedicineRack;
use App\Models\Unit;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\ValidationException;

class MedicineImporter extends Importer
{
    protected static ?string $model = Medicine::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->label('Kode Obat')
                ->exampleHeader('Kode Obat')
                ->guess(['Kode Obat', 'Kode', 'code'])
                ->example('SIP/NAMADOSIS/KAT/SAT/001')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('name')
                ->label('Nama Obat')
                ->exampleHeader('Nama Obat')
                ->guess(['Nama Obat', 'Nama', 'name'])
                ->example('Paracetamol')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('dosage')
                ->label('Dosis')
                ->exampleHeader('Dosis')
                ->guess(['Dosis', 'dosage'])
                ->example('500mg')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('category_name')
                ->label('Nama Kategori')
                ->exampleHeader('Nama Kategori')
                ->guess(['Nama Kategori', 'Kategori', 'category_name', 'category'])
                ->example('Analgesik')
                ->requiredMapping()
                ->rules(['required', 'string']),
            ImportColumn::make('unit_name')
                ->label('Nama Unit')
                ->exampleHeader('Nama Unit')
                ->guess(['Nama Unit', 'Unit', 'Satuan', 'unit_name', 'unit'])
                ->example('Tablet')
                ->requiredMapping()
                ->rules(['required', 'string']),
            ImportColumn::make('rack_name')
                ->label('Nama Rak')
                ->exampleHeader('Nama Rak')
                ->guess(['Nama Rak', 'Rak', 'rack_name', 'rack'])
                ->example('Rak A1')
                ->rules(['nullable', 'string']),
            ImportColumn::make('purchase_price')
                ->label('Harga Beli')
                ->exampleHeader('Harga Beli')
                ->guess(['Harga Beli', 'purchase_price'])
                ->example('5000')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('sale_price')
                ->label('Harga Jual')
                ->exampleHeader('Harga Jual')
                ->guess(['Harga Jual', 'sale_price'])
                ->example('7500')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('min_stock')
                ->label('Stok Minimum')
                ->exampleHeader('Stok Minimum')
                ->guess(['Stok Minimum', 'Min Stok', 'min_stock'])
                ->example('10')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:0']),
            ImportColumn::make('status')
                ->label('Status (active/inactive)')
                ->exampleHeader('Status')
                ->guess(['Status', 'status'])
                ->example('active')
                ->castStateUsing(fn ($state) => in_array($state, ['active', 'inactive'], true) ? $state : 'active')
                ->rules(['nullable', 'in:active,inactive']),
            ImportColumn::make('description')
                ->label('Keterangan')
                ->exampleHeader('Keterangan')
                ->guess(['Keterangan', 'Deskripsi', 'description'])
                ->example('Obat penurun panas dan pereda nyeri')
                ->rules(['nullable', 'string']),
        ];
    }

    public function resolveRecord(): ?Medicine
    {
        $categoryName = trim((string) ($this->data['category_name'] ?? ''));
        $unitName = trim((string) ($this->data['unit_name'] ?? ''));
        $rackName = trim((string) ($this->data['rack_name'] ?? ''));

        $category = MedicineCategories::whereRaw('LOWER(name) = ?', [strtolower($categoryName)])->first();
        if (! $category) {
            throw ValidationException::withMessages([
                'category_name' => "Kategori \"{$categoryName}\" tidak ditemukan di master Kategori Obat.",
            ]);
        }

        $unit = Unit::whereRaw('LOWER(name) = ?', [strtolower($unitName)])->first();
        if (! $unit) {
            throw ValidationException::withMessages([
                'unit_name' => "Unit \"{$unitName}\" tidak ditemukan di master Unit.",
            ]);
        }

        $rackId = null;
        if ($rackName !== '') {
            $rack = MedicineRack::whereRaw('LOWER(name) = ?', [strtolower($rackName)])->first();
            if (! $rack) {
                throw ValidationException::withMessages([
                    'rack_name' => "Rak \"{$rackName}\" tidak ditemukan di master Rak Obat.",
                ]);
            }
            $rackId = $rack->id;
        }

        $medicine = Medicine::firstOrNew([
            'code' => $this->data['code'],
        ]);

        $medicine->category_id = $category->id;
        $medicine->unit_id = $unit->id;
        $medicine->rack_id = $rackId;

        if (! $medicine->exists) {
            $medicine->stock_status = $medicine->stock_status ?? 'empty';
        }

        unset($this->data['category_name'], $this->data['unit_name'], $this->data['rack_name']);

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
