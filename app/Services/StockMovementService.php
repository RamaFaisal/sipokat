<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\MedicineStockOpname;
use App\Models\Order;
use App\Models\ReceiveOrder;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat kartu stok ditulis dan dihapus.
 *
 * Aturan (rencana-revisi-2026-09 Bagian 4–5):
 * - Baris D dari RO membawa lapisan (batch, ED, jejak item RO) dan harga belinya.
 * - HPP rata-rata bergerak (hpp_avg) dihitung ulang dari awal untuk obat itu setiap kali
 *   ledgernya berubah — replay adalah satu-satunya jalur, tidak ada kasus khusus.
 * - Menghapus dokumen menghapus baris ledgernya sungguhan (B5), dijaga aturan R8:
 *   lapisan yang sudah dikonsumsi tidak boleh dihapus.
 *
 * Semua method mengembalikan daftar id obat yang terdampak supaya pemanggil bisa
 * menyegarkan stock_status tanpa menebak.
 */
class StockMovementService
{
    public function __construct(
        private StockCardService $stockCard,
    ) {}

    /** Tulis satu lapisan (baris D) per item penerimaan. */
    public function recordReceipt(ReceiveOrder $receiveOrder): array
    {
        // purchase_order_id boleh kosong — form RO tidak mewajibkannya.
        $description = $receiveOrder->purchaseOrder
            ? 'Penerimaan dari '.$receiveOrder->purchaseOrder->po_number
            : 'Penerimaan '.$receiveOrder->receive_order_number;

        $affected = [];

        foreach ($receiveOrder->items()->get() as $item) {
            MedicineStock::create([
                'medicine_id' => $item->medicine_id,
                'qty' => $item->qty,
                'type_account' => 'D',
                'batch_number' => $item->batch_number,
                'expired_date' => $item->expired_date,
                'date' => $receiveOrder->receive_date,
                'hpp' => $item->price,
                'receive_order_id' => $receiveOrder->id,
                'receive_order_item_id' => $item->id,
                'description' => $description,
                'created_by' => auth()->id(),
            ]);

            $affected[] = $item->medicine_id;
        }

        return $this->replayAll($affected);
    }

    /**
     * Hapus lapisan milik satu penerimaan. Ditolak bila ada lapisan yang sudah dikonsumsi (R8) —
     * koreksinya lewat Stok Opname.
     *
     * @throws \RuntimeException
     */
    public function reverseReceipt(ReceiveOrder $receiveOrder): array
    {
        $layers = MedicineStock::query()->where('receive_order_id', $receiveOrder->id);

        $consumed = MedicineStock::query()
            ->whereIn('layer_stock_id', (clone $layers)->select('id'))
            ->exists();

        if ($consumed) {
            throw new \RuntimeException(
                "Penerimaan {$receiveOrder->receive_order_number} tidak dapat dihapus: sebagian batch-nya sudah terjual. Koreksi lewat Stok Opname."
            );
        }

        $affected = (clone $layers)->pluck('medicine_id')->unique()->values()->all();
        (clone $layers)->delete();

        return $this->replayAll($affected);
    }

    /**
     * Tulis entri kredit (stok keluar) untuk seluruh item penjualan.
     * Alokasi ke lapisan (FEFO) menyusul di E4; sampai itu layer_stock_id kosong.
     */
    public function recordSale(Order $order): array
    {
        $affected = [];

        foreach ($order->items()->get() as $item) {
            MedicineStock::create([
                'medicine_id' => $item->medicine_id,
                'qty' => $item->qty,
                'type_account' => 'C',
                'date' => $order->order_date,
                'hpp' => $this->stockCard->currentHpp($item->medicine_id) ?? 0,
                'order_id' => $order->id,
                'description' => 'Penjualan '.$order->order_code,
                'created_by' => auth()->id(),
            ]);

            $affected[] = $item->medicine_id;
        }

        return $this->replayAll($affected);
    }

    /** Hapus entri kartu stok milik satu penjualan (sungguhan, B5), kembalikan stoknya. */
    public function reverseSale(Order $order): array
    {
        $rows = MedicineStock::query()->where('order_id', $order->id);
        $affected = (clone $rows)->pluck('medicine_id')->unique()->values()->all();
        (clone $rows)->delete();

        return $this->replayAll($affected);
    }

    /**
     * Tulis entri kartu stok dari item-item opname yang sudah tersimpan.
     * Baris D memakai HPP saat itu sebagai harganya; baris C menyalin HPP (S4).
     */
    public function recordOpname(MedicineStockOpname $opname): array
    {
        $affected = [];

        foreach ($opname->medicineStockOpnameItems()->get() as $item) {
            $hpp = $this->stockCard->currentHpp($item->medicine_id) ?? 0;

            MedicineStock::create([
                'medicine_id' => $item->medicine_id,
                'qty' => $item->qty,
                'type_account' => $item->type_account,
                'date' => $opname->opname_date,
                'hpp' => $hpp,
                'medicine_stock_opname_id' => $opname->id,
                'description' => 'opname dari '.$opname->opname_number,
                'created_by' => auth()->id(),
            ]);

            $affected[] = $item->medicine_id;
        }

        return $this->replayAll($affected);
    }

    /**
     * Hapus entri kartu stok milik satu opname. Ditolak bila lapisan buatannya sudah dikonsumsi (R8).
     *
     * @throws \RuntimeException
     */
    public function reverseOpname(MedicineStockOpname $opname): array
    {
        $rows = MedicineStock::query()->where('medicine_stock_opname_id', $opname->id);

        $consumed = MedicineStock::query()
            ->whereIn('layer_stock_id', (clone $rows)->select('id'))
            ->exists();

        if ($consumed) {
            throw new \RuntimeException(
                "Opname {$opname->opname_number} tidak dapat dihapus: batch yang ditambahkannya sudah terjual."
            );
        }

        $affected = (clone $rows)->pluck('medicine_id')->unique()->values()->all();
        (clone $rows)->delete();

        return $this->replayAll($affected);
    }

    /**
     * Pastikan tiap item masih tertutup stok tersedia.
     *
     * @param  iterable<array{medicine_id: int|string, qty: int|float|string}>  $items
     *
     * @throws \Exception bila ada satu item pun yang melebihi stok.
     */
    public function assertAvailable(iterable $items): void
    {
        foreach ($items as $item) {
            $medicine = Medicine::findOrFail($item['medicine_id']);
            $available = $this->stockCard->getAvailableStock($medicine->id);

            if ((float) $item['qty'] > $available) {
                throw new \Exception(
                    "Stok {$medicine->name} tidak mencukupi (tersedia: {$available}, diminta: {$item['qty']})"
                );
            }
        }
    }

    /** Hitung ulang kolom stock_status untuk obat yang terdampak. */
    public function refreshStockStatus(array $medicineIds): void
    {
        foreach (array_unique($medicineIds) as $medicineId) {
            $this->stockCard->updateMedicineStockStatus($medicineId);
        }
    }

    /**
     * Hitung ulang hpp_avg (dan hpp baris keluar) seluruh ledger satu obat dari awal,
     * urut tanggal lalu id (rencana §4.3).
     *
     *   HPP_baru = (Saldo_lama × HPP_lama + Qty_masuk × Harga_masuk) / (Saldo_lama + Qty_masuk)
     *
     * Saldo = stok fisik (Q2). Saldo ≤ 0 saat ada penerimaan → HPP = harga penerimaan itu.
     * Baris C dan baris D dari opname tidak mengubah HPP; mereka menyalinnya.
     * Hasil dibulatkan ke atas ke rupiah bulat (K14).
     */
    public function replayHpp(int $medicineId): void
    {
        DB::transaction(function () use ($medicineId) {
            $rows = MedicineStock::query()
                ->where('medicine_id', $medicineId)
                ->orderBy('date')
                ->orderBy('id')
                ->get(['id', 'type_account', 'qty', 'hpp', 'hpp_avg', 'receive_order_id']);

            $saldo = 0;
            $avg = null;

            foreach ($rows as $row) {
                $qty = (int) $row->qty;
                $isPurchase = $row->type_account === 'D' && $row->receive_order_id !== null;

                if ($isPurchase) {
                    $price = (float) $row->hpp;
                    $avg = ($saldo <= 0 || $avg === null)
                        ? (int) ceil($price)
                        : (int) ceil(($saldo * $avg + $qty * $price) / ($saldo + $qty));
                    $newHpp = $row->hpp;
                } else {
                    // Opname (D/C) dan penjualan: menyalin HPP saat itu, tidak mengubahnya.
                    $newHpp = $avg ?? $row->hpp;
                }

                $saldo += $row->type_account === 'D' ? $qty : -$qty;

                $changed = [];
                if ((float) $newHpp !== (float) $row->hpp) {
                    $changed['hpp'] = $newHpp;
                }
                if ($avg !== $row->hpp_avg) {
                    $changed['hpp_avg'] = $avg;
                }
                if ($changed) {
                    MedicineStock::whereKey($row->id)->update($changed);
                }
            }
        });
    }

    /** @return array<int,int> */
    private function replayAll(array $medicineIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $medicineIds)));

        foreach ($ids as $id) {
            $this->replayHpp($id);
        }

        return $ids;
    }
}
