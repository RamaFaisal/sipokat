<?php

namespace App\Console\Commands;

use App\Services\RealDataImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RealDataImportCommand extends Command
{
    protected $signature = 'sipokat:data-riil:import
                            {file : Berkas .xlsx hasil template}
                            {--period-start= : Tanggal awal periode (YYYY-MM-DD) untuk saldo awal; bawaan 30 hari lalu}
                            {--dry-run : Validasi saja, tidak menyimpan}';

    protected $description = 'Muat data riil apotek: Obat → SaldoAwal (RO "SALDO AWAL") → Faktur → Penjualan, lewat jalur kartu stok yang sama dengan UI.';

    public function handle(RealDataImporter $importer): int
    {
        $periodStart = $this->option('period-start')
            ? Carbon::parse($this->option('period-start'))->startOfDay()
            : today()->subDays(29);

        $result = $importer->import($this->argument('file'), $periodStart, (bool) $this->option('dry-run'));

        foreach ($result['summary'] as $k => $v) {
            $this->line(sprintf('  %-16s %d', $k, $v));
        }

        if ($result['errors']) {
            $this->error(count($result['errors']).' masalah — tidak ada yang disimpan:');
            foreach ($result['errors'] as $e) {
                $this->line('  - '.$e);
            }

            return self::FAILURE;
        }

        $this->info($this->option('dry-run') ? 'Validasi lolos (dry-run, tidak disimpan).' : 'Impor selesai. Jalankan: php artisan sipokat:recalculate-saw');

        return self::SUCCESS;
    }
}
