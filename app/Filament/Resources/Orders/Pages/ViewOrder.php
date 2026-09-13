<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

/** Penjualan tidak bisa diedit (S6): halaman ini hanya menampilkan, dengan aksi hapus. */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Stok yang terjual akan dikembalikan ke batch asalnya. Untuk mengoreksi, buat penjualan baru setelah menghapus.'),
        ];
    }
}
