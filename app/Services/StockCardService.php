<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\MedicineStock;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StockCardService
{
    public function getStockCardData(
        int $medicineId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?int $supplierId = null
    ): array {
        $openingStock = $this->calculateOpeningStock($medicineId, $startDate);
        $transactions = $this->getFilteredTransactions($medicineId, $startDate, $endDate, $supplierId);
        $detailData = $this->calculateRunningStock($transactions, $openingStock);
        $closingStock = $this->calculateClosingStock($detailData, $openingStock);

        return [
            'opening_stock' => $openingStock,
            'transactions' => $detailData,
            'closing_stock' => $closingStock,
            'summary' => [
                'total_debit' => $detailData->sum('debit'),
                'total_credit' => $detailData->sum('credit'),
                'net_movement' => $closingStock - $openingStock,
            ],
        ];
    }

    public function calculateOpeningStock(int $medicineId, ?Carbon $startDate = null): float
    {
        if (! $startDate) {
            return 0;
        }

        $query = MedicineStock::where('medicine_id', $medicineId)
            ->where('date', '<', $startDate);

        $totalIn = $query->clone()
            ->where('type_account', 'D')
            ->sum('qty') ?? 0;

        $totalOut = $query->clone()
            ->where('type_account', 'C')
            ->sum('qty') ?? 0;

        return $totalIn - $totalOut;
    }

    public function getFilteredTransactions(
        int $medicineId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?int $supplierId = null
    ): Collection {
        $query = MedicineStock::where('medicine_id', $medicineId)
            ->with([
                'receiveOrder.supplier',
                'medicineStockOpname',
                'order',
            ]);

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        if ($supplierId && $supplierId !== 'all') {
            $query->whereHas('receiveOrder', function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId);
            });
        }

        return $query->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    public function calculateRunningStock(Collection $transactions, float $openingStock): Collection
    {
        $runningStock = $openingStock;

        return $transactions->map(function ($transaction) use (&$runningStock) {
            $debit = $transaction->type_account === 'D' ? $transaction->qty : 0;
            $credit = $transaction->type_account === 'C' ? $transaction->qty : 0;

            $runningStock += $debit - $credit;

            return [
                'id' => $transaction->id,
                'reference_number' => $this->getReferenceNumber($transaction),
                'supplier' => $this->getSupplierName($transaction),
                'date' => $transaction->date->locale('id')->translatedFormat('d F Y'),
                'refer_table' => $this->getReferTable($transaction),
                'debit' => $debit,
                'credit' => $credit,
                'current_stock' => $runningStock,
                'hpp' => $transaction->hpp,
                'hpp_avg' => $transaction->hpp_avg,
                'batch_number' => $transaction->batch_number,
                'expired_date' => $transaction->expired_date?->format('m-Y'),
                'record' => $transaction,
            ];
        });
    }

    public function calculateClosingStock(Collection $detailData, float $openingStock): float
    {
        if ($detailData->isEmpty()) {
            return $openingStock;
        }

        return $detailData->last()['current_stock'];
    }

    public function getReferenceNumber($record): string
    {
        if ($record->receive_order_id) {
            return str_pad($record->receiveOrder->receive_order_number, 6, '0', STR_PAD_LEFT);
        }

        if ($record->medicine_stock_opname_id) {
            return str_pad($record->medicineStockOpname->opname_number, 6, '0', STR_PAD_LEFT);
        }

        if ($record->order_id) {
            return str_pad($record->order->order_code, 6, '0', STR_PAD_LEFT);
        }

        return '-';
    }

    public function getSupplierName($record): string
    {
        if ($record->receive_order_id && $record->receiveOrder?->supplier) {
            return $record->receiveOrder->supplier->name;
        }

        if ($record->medicine_stock_opname_id) {
            return '-';
        }

        if ($record->order_id) {
            return '-';
        }

        return '-';
    }

    public function getReferTable($record): string
    {
        if ($record->receive_order_id) {
            return 'Receive Order';
        }

        if ($record->medicine_stock_opname_id) {
            return 'Stock Opname';
        }

        if ($record->order_id) {
            return 'Penjualan';
        }

        return 'Manual Entry';
    }

    public function calculateStockUpToDate(int $medicineId, Carbon $endDate): float
    {
        $query = MedicineStock::where('medicine_id', $medicineId)
            ->where('date', '<=', $endDate);

        $totalIn = (clone $query)
            ->where('type_account', 'D')
            ->sum('qty') ?? 0;

        $totalOut = (clone $query)
            ->where('type_account', 'C')
            ->sum('qty') ?? 0;

        return $totalIn - $totalOut;
    }

    public function getInitStockForPeriod(int $medicineId, int $year, int $month): float
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();

        return $this->calculateOpeningStock($medicineId, $startDate);
    }

    public function getCurrentStockForPeriod(int $medicineId, int $year, int $month): float
    {
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        return $this->calculateStockUpToDate($medicineId, $endDate);
    }

    public function prepareExportData(
        int $medicineId,
        string $medicineName,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?int $supplierId = null
    ): array {
        $stockData = $this->getStockCardData(
            $medicineId,
            $startDate,
            $endDate,
            $supplierId
        );

        return [
            'medicine_name' => $medicineName,
            'period' => [
                'start' => $startDate?->format('d/m/Y'),
                'end' => $endDate?->format('d/m/Y'),
            ],
            'opening_stock' => $stockData['opening_stock'],
            'details' => $stockData['transactions']->map(function ($item) {
                return [
                    'reference_number' => $item['reference_number'],
                    'supplier' => $item['supplier'],
                    'date' => $item['date'],
                    'refer_table' => $item['refer_table'],
                    'debit' => $item['debit'],
                    'credit' => $item['credit'],
                    'current_stock' => $item['current_stock'],
                ];
            }),
            'closing_stock' => $stockData['closing_stock'],
            'summary' => $stockData['summary'],
        ];
    }

    /**
     * Stok fisik = Σ D − Σ C seluruh lapisan (termasuk yang kedaluwarsa dan belum dimusnahkan).
     * Tidak ada saringan ke dokumen induk: baris ledger dihapus sungguhan saat dokumennya dihapus (B5).
     */
    public function physicalStock(int $medicineId): int
    {
        $base = MedicineStock::where('medicine_id', $medicineId);

        $totalIn = (clone $base)->where('type_account', 'D')->sum('qty');
        $totalOut = (clone $base)->where('type_account', 'C')->sum('qty');

        return (int) ($totalIn - $totalOut);
    }

    /**
     * Semua lapisan (baris D) satu obat beserta sisanya, urut FEFO: lapisan tanpa ED (data lama)
     * paling dulu, lalu ED terdekat, lalu id.
     *
     * @return Collection<int, MedicineStock>
     */
    public function layers(int $medicineId): Collection
    {
        return MedicineStock::layers()
            ->where('medicine_id', $medicineId)
            ->withSum('consumptions', 'qty')
            ->orderByRaw('CASE WHEN expired_date IS NULL THEN 0 ELSE 1 END')
            ->orderBy('expired_date')
            ->orderBy('id')
            ->get();
    }

    /** Lapisan yang boleh dijual: sisa > 0 dan belum kedaluwarsa (`expired_date > hari ini`, B1). */
    public function sellableLayers(int $medicineId): Collection
    {
        return $this->layers($medicineId)
            ->filter(fn (MedicineStock $l) => $l->remaining > 0 && ! $l->isExpired())
            ->values();
    }

    /**
     * Stok tersedia (B4, F2) = sisa lapisan belum kedaluwarsa. Baris C lama yang tidak
     * teratribusi ke lapisan mana pun (data tidak konsisten) tetap dikurangkan supaya angka
     * tidak melebihi stok fisik.
     */
    public function availableStock(int $medicineId): int
    {
        $fromLayers = $this->sellableLayers($medicineId)->sum('remaining');

        $unattributed = (int) MedicineStock::where('medicine_id', $medicineId)
            ->where('type_account', 'C')
            ->whereNull('layer_stock_id')
            ->sum('qty');

        return max(0, (int) $fromLayers - $unattributed);
    }

    /** @deprecated pakai availableStock(); dipertahankan untuk pemanggil lama. */
    public function getAvailableStock(int $medicineId): float
    {
        return (float) $this->availableStock($medicineId);
    }

    /** HPP rata-rata bergerak saat ini = hpp_avg baris kartu stok terakhir (urut tanggal, id). */
    public function currentHpp(int $medicineId): ?int
    {
        $avg = MedicineStock::where('medicine_id', $medicineId)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('hpp_avg');

        return $avg === null ? null : (int) $avg;
    }

    /** Status stok dari **stok tersedia** (belum kedaluwarsa, B4) dibanding batas waspada obat. */
    public function getAvailableStockLabel(int $medicineId): string
    {
        $medicine = Medicine::findOrFail($medicineId);

        $currentStock = $this->availableStock($medicineId);
        $minimumStock = $medicine->min_stock;

        if ($currentStock <= 0) {
            return 'empty';
        }

        if ($currentStock < $minimumStock) {
            return 'almost_empty';
        }

        return 'available';
    }

    public function updateMedicineStockStatus(int $medicineId): void
    {
        $label = $this->getAvailableStockLabel($medicineId);

        Medicine::where('id', $medicineId)
            ->update(['stock_status' => $label]);
    }
}
