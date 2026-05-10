<?php

namespace App\Filament\Imports;

use App\Models\Supplier;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class SupplierImporter extends Importer
{
    protected static ?string $model = Supplier::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->label('Kode')
                ->exampleHeader('Kode')
                ->guess(['Kode', 'code'])
                ->example('SUP001')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
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
                ->example('Jl. Veteran No. 9, Jakarta Pusat')
                ->rules(['nullable', 'string']),
            ImportColumn::make('phone')
                ->label('Telepon')
                ->exampleHeader('Telepon')
                ->guess(['Telepon', 'phone', 'No HP'])
                ->example('021-3441234')
                ->rules(['nullable', 'string', 'max:50']),
            ImportColumn::make('email')
                ->label('Email')
                ->exampleHeader('Email')
                ->guess(['Email', 'email'])
                ->example('cs@kimiafarma.co.id')
                ->rules(['nullable', 'email', 'max:255']),
            ImportColumn::make('pic')
                ->label('PIC')
                ->exampleHeader('PIC')
                ->guess(['PIC', 'pic', 'Penanggung Jawab'])
                ->example('Budi Santoso')
                ->rules(['nullable', 'string', 'max:255']),
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
        return Supplier::firstOrNew([
            'code' => $this->data['code'],
        ]);
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
