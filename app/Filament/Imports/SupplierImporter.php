<?php

namespace App\Filament\Imports;

use App\Models\Supplier;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

/**
 * Import master PBF. Tanpa kolom kode: kode dibuat model dari inisial nama saat
 * creating. PBF dikenali dari nama (dedup, tidak peka huruf besar).
 */
class SupplierImporter extends Importer
{
    protected static ?string $model = Supplier::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Nama Supplier')
                ->exampleHeader('Nama Supplier')
                ->guess(['Nama Supplier', 'Nama', 'name'])
                ->example('PT Kimia Farma')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('address')
                ->label('Alamat')
                ->exampleHeader('Alamat')
                ->guess(['Alamat', 'address'])
                ->example('Jl. Tambak Aji No. 1A, Ngaliyan, Semarang')
                ->rules(['nullable', 'string']),
            ImportColumn::make('phone')
                ->label('Telp')
                ->exampleHeader('Telp')
                ->guess(['Telp', 'Telepon', 'phone', 'No HP'])
                ->example('(024) 8664117')
                ->rules(['nullable', 'string', 'max:50']),
            ImportColumn::make('fax')
                ->label('Fax')
                ->exampleHeader('Fax')
                ->guess(['Fax', 'fax'])
                ->example('(024) 8664123')
                ->rules(['nullable', 'string', 'max:50']),
            ImportColumn::make('status')
                ->label('Status (active/inactive)')
                ->exampleHeader('Status')
                ->guess(['Status', 'status'])
                ->example('active')
                ->castStateUsing(fn ($state) => in_array($state, ['active', 'inactive'], true) ? $state : 'active')
                ->rules(['nullable', 'in:active,inactive']),
        ];
    }

    public function resolveRecord(): ?Supplier
    {
        $name = trim((string) ($this->data['name'] ?? ''));

        return Supplier::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first()
            ?? new Supplier(['name' => $name]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import supplier selesai. '.number_format($import->successful_rows).' baris berhasil diimport.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' baris gagal diimport.';
        }

        return $body;
    }
}
