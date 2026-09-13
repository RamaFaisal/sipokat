<?php

namespace App\Console\Commands;

use App\Support\RealDataTemplate;
use Illuminate\Console\Command;

class RealDataTemplateCommand extends Command
{
    protected $signature = 'sipokat:data-riil:template {path? : Lokasi berkas .xlsx (bawaan storage/app/import/template-data-riil.xlsx)}';

    protected $description = 'Buat template Excel untuk memuat data riil apotek (Obat, SaldoAwal, Faktur, Penjualan).';

    public function handle(): int
    {
        $path = $this->argument('path') ?: storage_path('app/import/template-data-riil.xlsx');

        RealDataTemplate::write($path);

        $this->info("Template tersimpan: {$path}");
        $this->line('Sheet: '.implode(', ', array_keys(RealDataTemplate::SHEETS)).'. Baris 2 = petunjuk, baris 3+ = contoh (hapus sebelum diisi).');

        return self::SUCCESS;
    }
}
