<?php

namespace App\Console\Commands;

use App\Services\SawCalculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RecalculateSawCommand extends Command
{
    protected $signature = 'sipokat:recalculate-saw
                            {--days=30 : Lebar periode (hari) ke belakang untuk agregasi permintaan C2}';

    protected $description = 'Jalankan SAW snapshot terjadwal dengan trigger_type=scheduled (dipakai dashboard widget).';

    public function handle(SawCalculationService $service): int
    {
        $days = max(1, (int) $this->option('days'));
        $start = Carbon::now()->subDays($days - 1)->startOfDay();
        $end = Carbon::today(); // K2: tanggal saja, bukan endOfDay — pembagi hari harus bulat (T5)

        try {
            $calc = $service->execute($start, $end, 'scheduled', null);

            $this->info(sprintf(
                'SAW snapshot #%d tersimpan — %d alternatif diranking, periode %s s/d %s.',
                $calc->id,
                $calc->total_alternatives,
                $calc->period_start->format('Y-m-d'),
                $calc->period_end->format('Y-m-d'),
            ));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Gagal: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }
    }
}
