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
            ]
        ];
    }

    public function calculateOpeningStock(int $medicineId, ?Carbon $startDate = null): float
    {
        if (!$startDate) {
            return 0;
        }

        $query = MedicineStock::where('medicine_id', $medicineId)
            ->where('date', '<', $startDate)
            ->where(function ($q) {
                $q->whereHas('receiveOrder', fn($r) => $r->whereNull('deleted_at'))
                    ->orWhereNull('receive_order_id');
            })
            ->where(function ($q) {
                $q->whereHas('medicineStockOpname', fn($r) => $r->whereNull('deleted_at'))
                    ->orWhereNull('medicine_stock_opname_id');
            })
            ->where(function ($q) {
                $q->whereHas('order', fn($r) => $r->whereNull('deleted_at'))
                    ->orWhereNull('order_id');
            });

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
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereHas('receiveOrder')
                        ->orWhereNull('receive_order_id');
                });

                $q->where(function ($sub) {
                    $sub->whereHas('medicineStockOpname')
                        ->orWhereNull('medicine_stock_opname_id');
                });

                $q->where(function ($sub) {
                    $sub->whereHas('order')
                        ->orWhereNull('order_id');
                });
            })
            ->with([
                'receiveOrder' => fn($q) => $q->whereNull('deleted_at')->with('supplier'),
                'medicineStockOpname',
                'order'
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
            return str_pad($record->order->order_number, 6, '0', STR_PAD_LEFT);
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
            ->where('date', '<=', $endDate)
            ->where(function ($q) {
                $q->whereHas('receiveOrder', fn($r) => $r->whereNull('deleted_at'))
                    ->orWhereNull('receive_order_id');
            })
            ->where(function ($q) {
                $q->whereHas('medicineStockOpname', fn($r) => $r->whereNull('deleted_at'))
                    ->orWhereNull('medicine_stock_opname_id');
            })
            ->where(function ($q) {
                $q->whereHas('order', fn($r) => $r->whereNull('deleted_at'))
                    ->orWhereNull('order_id');
            });

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

    public function getAvailableStock(int $medicineId)
    {
        $baseQuery = MedicineStock::where('medicine_id', $medicineId)
            ->where(function ($q) {
                $q->whereHas('receiveOrder')
                    ->orWhereNull('receive_order_id');
            })
            ->where(function ($q) {
                $q->whereHas('medicineStockOpname')
                    ->orWhereNull('medicine_stock_opname_id');
            })
            ->where(function ($q) {
                $q->whereHas('order')
                    ->orWhereNull('order_id');
            });

        $totalIn = (clone $baseQuery)->where('type_account', 'D')->sum('qty') ?? 0;
        $totalOut = (clone $baseQuery)->where('type_account', 'C')->sum('qty') ?? 0;

        return (float) ($totalIn - $totalOut);
    }

    public function getAvailableStockLabel(int $medicineId): string
    {
        $medicine = Medicine::findOrFail($medicineId);

        $currentStock = $this->getAvailableStock($medicineId);
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
