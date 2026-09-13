<?php

namespace App\Console\Commands;

use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\User;
use App\Services\StockMovementService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckStockAndExpiryCommand extends Command
{
    protected $signature = 'sipokat:check-stock-and-expiry
                            {--expiry-days=90 : Threshold sisa hari ED untuk peringatan}';

    protected $description = 'Scan obat stok menipis/habis dan batch mendekati kedaluwarsa, kirim notifikasi ke semua user via database notification Filament.';

    public function handle(): int
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $this->warn('Tidak ada user — notifikasi tidak dikirim.');

            return self::SUCCESS;
        }

        // Q1: batch bisa kedaluwarsa tanpa ada mutasi, jadi status stok (berbasis stok tersedia, B4)
        // dihitung ulang dulu untuk semua obat aktif sebelum dipindai.
        $this->refreshStockStatuses();

        $this->checkLowStock($users);
        $this->checkExpiring($users, (int) $this->option('expiry-days'));

        return self::SUCCESS;
    }

    protected function refreshStockStatuses(): void
    {
        $ids = Medicine::query()->where('status', 'active')->pluck('id')->all();
        app(StockMovementService::class)->refreshStockStatus($ids);
        $this->info('Status stok dihitung ulang untuk '.count($ids).' obat.');
    }

    protected function checkLowStock($users): void
    {
        $lowStock = Medicine::query()
            ->whereIn('stock_status', ['empty', 'almost_empty'])
            ->where('status', 'active')
            ->get();

        if ($lowStock->isEmpty()) {
            $this->info('Low-stock: tidak ada obat menipis/habis.');

            return;
        }

        $emptyCount = $lowStock->where('stock_status', 'empty')->count();
        $almostCount = $lowStock->where('stock_status', 'almost_empty')->count();

        Notification::make()
            ->title('Peringatan stok obat')
            ->icon('heroicon-o-exclamation-triangle')
            ->iconColor('warning')
            ->body("{$lowStock->count()} obat butuh perhatian: {$emptyCount} habis, {$almostCount} menipis.")
            ->actions([
                Action::make('view')
                    ->label('Lihat di Dashboard')
                    ->url('/admin')
                    ->markAsRead(),
            ])
            ->sendToDatabase($users);

        $this->info("Low-stock notification terkirim: {$lowStock->count()} obat ke {$users->count()} user.");
    }

    protected function checkExpiring($users, int $days): void
    {
        $today = Carbon::now()->startOfDay()->toDateString();
        $threshold = Carbon::now()->addDays($days)->toDateString();

        // F5: hanya lapisan (batch) yang masih bersisa — batch yang sudah habis terjual tidak diperingatkan.
        $expiring = MedicineStock::layers()
            ->whereNotNull('expired_date')
            ->whereBetween('expired_date', [$today, $threshold])
            ->whereHas('medicine', fn ($q) => $q->where('status', 'active'))
            ->withSum('consumptions', 'qty')
            ->with('medicine:id,name')
            ->orderBy('expired_date')
            ->get()
            ->filter(fn (MedicineStock $layer) => $layer->remaining > 0)
            ->values();

        if ($expiring->isEmpty()) {
            $this->info("Expiring: tidak ada batch mendekati ED (≤ {$days} hari).");

            return;
        }

        $critical = $expiring->filter(
            fn ($item) => now()->startOfDay()->diffInDays($item->expired_date->startOfDay(), false) <= 30
        )->count();

        Notification::make()
            ->title('Obat mendekati kedaluwarsa')
            ->icon('heroicon-o-clock')
            ->iconColor('danger')
            ->body("{$expiring->count()} batch akan kedaluwarsa dalam {$days} hari ({$critical} kritis ≤ 30 hari).")
            ->actions([
                Action::make('view')
                    ->label('Lihat di Dashboard')
                    ->url('/admin')
                    ->markAsRead(),
            ])
            ->sendToDatabase($users);

        $this->info("Expiring notification terkirim: {$expiring->count()} batch ke {$users->count()} user.");
    }
}
