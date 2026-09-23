<?php

namespace Database\Seeders;

use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\ReceiveOrder;
use App\Models\User;
use App\Services\StockCardService;
use App\Services\StockMovementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Simulasi penjualan sederhana (rencana-sidang-2026-10 §3 A2/A3) supaya menu Penjualan
 * tidak kosong dan kriteria SAW C2 (permintaan) tidak nol untuk seluruh obat. Tiap obat
 * dijual 15–45% dari stok tersedianya, dipecah 1–3 transaksi tersebar antara penerimaan
 * pertama dan hari ini, memakai `StockMovementService::recordSale()` (bukan tulis ledger
 * langsung) supaya FEFO dan HPP tetap konsisten dengan alur form. Harga = HPP × 1,25
 * dibulatkan ke atas (memenuhi aturan harga ≥ HPP, S1). Ditandai `[SIMULASI]` pada
 * catatan WAJIB dinyatakan sebagai data simulasi, bukan penjualan riil, di Bab 1.4/IV.
 *
 * Idempoten-sederhana: hanya jalan bila belum ada penjualan bertanda simulasi (bukan
 * idempoten per hari cukup untuk mengisi data awal, bukan simulasi berkelanjutan).
 */
class SimulasiPenjualanSeeder extends Seeder
{
    private const MARKUP = 1.25;

    public const NOTE = '[SIMULASI] penjualan otomatis untuk demo & data C2 SAW bukan transaksi riil';

    private const SEED = 20260921;

    public function run(StockMovementService $movement, StockCardService $stockCard): void
    {
        if (Order::withTrashed()->where('note', self::NOTE)->exists()) {
            $this->command?->info('SimulasiPenjualanSeeder: sudah ada penjualan simulasi, dilewati.');

            return;
        }

        $userId = User::query()->value('id');
        $start = ($min = ReceiveOrder::min('receive_date')) ? Carbon::parse($min) : Carbon::today()->subDays(14);
        $end = Carbon::today();
        $days = max(1, (int) $start->diffInDays($end) + 1);

        mt_srand(self::SEED);

        $created = 0;
        foreach (Medicine::where('status', 'active')->orderBy('id')->get() as $medicine) {
            $available = $stockCard->availableStock($medicine->id);
            $hpp = $stockCard->currentHpp($medicine->id);
            if ($available <= 0 || $hpp === null) {
                continue;
            }

            $totalToSell = (int) round($available * (mt_rand(15, 45) / 100));
            if ($totalToSell <= 0) {
                continue;
            }

            $price = (int) ceil($hpp * self::MARKUP);
            $transactions = mt_rand(1, min(3, $totalToSell));
            $remaining = $totalToSell;

            for ($t = 0; $t < $transactions; $t++) {
                if ($remaining <= 0) {
                    break;
                }
                $left = $transactions - $t;
                $isLast = $t === $transactions - 1;
                $qty = $isLast
                    ? $remaining
                    : max(1, min($remaining, (int) round($remaining / $left * (mt_rand(60, 140) / 100))));

                $orderDate = $start->copy()->addDays(mt_rand(0, $days - 1));

                $order = Order::create([
                    'order_code' => OrderForm::generateOrderCode(),
                    'order_date' => $orderDate->toDateString(),
                    'grand_total' => $qty * $price,
                    'note' => self::NOTE,
                    'created_by' => $userId,
                ]);
                $order->items()->create([
                    'medicine_id' => $medicine->id,
                    'medicine_name' => $medicine->name,
                    'qty' => $qty,
                    'price' => $price,
                ]);

                try {
                    $movement->refreshStockStatus($movement->recordSale($order));
                    $created++;
                } catch (\RuntimeException) {
                    $order->items()->delete();
                    $order->delete();

                    break;
                }

                $remaining -= $qty;
            }
        }

        $this->command?->info("SimulasiPenjualanSeeder: {$created} transaksi penjualan simulasi dibuat.");
    }
}
