<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\Order;
use App\Models\ReceiveOrder;

/**
 * Satu-satunya tempat kartu stok ditulis.
 *
 * Sebelumnya logika ini tersebar di empat kelas halaman Filament, sehingga
 * alokasi FEFO nanti harus ditulis empat kali. Semua method mengembalikan
 * daftar id obat yang terdampak supaya pemanggil bisa menyegarkan
 * stock_status tanpa menebak.
 */
class StockMovementService
{
    public function __construct(
        private StockCardService $stockCard,
    ) {}

    /** Tulis entri debit (stok masuk) untuk seluruh item penerimaan. */
    public function recordReceipt(ReceiveOrder $receiveOrder): array
    {
        // purchase_order_id boleh kosong — form RO tidak mewajibkannya.
        $description = $receiveOrder->purchaseOrder
            ? 'Penerimaan dari ' . $receiveOrder->purchaseOrder->po_number
            : 'Penerimaan ' . $receiveOrder->receive_order_number;

        $affected = [];

        foreach ($receiveOrder->items()->get() as $item) {
            MedicineStock::create([
                'medicine_id' => $item->medicine_id,
                'qty' => $item->qty,
                'type_account' => 'D',
                'date' => $receiveOrder->receive_date,
                'hpp' => $item->price,
                'receive_order_id' => $receiveOrder->id,
                'description' => $description,
                'created_by' => auth()->id(),
            ]);

            $affected[] = $item->medicine_id;
        }

        return array_values(array_unique($affected));
    }

    /** Tulis entri kredit (stok keluar) untuk seluruh item penjualan. */
    public function recordSale(Order $order): array
    {
        $affected = [];

        foreach ($order->items()->get() as $item) {
            MedicineStock::create([
                'medicine_id' => $item->medicine_id,
                'qty' => $item->qty,
                'type_account' => 'C',
                'date' => $order->order_date,
                'hpp' => $item->price,
                'order_id' => $order->id,
                'description' => 'Penjualan ' . $order->order_code,
                'created_by' => auth()->id(),
            ]);

            $affected[] = $item->medicine_id;
        }

        return array_values(array_unique($affected));
    }

    /** Hapus entri kartu stok milik satu penjualan, kembalikan stoknya. */
    public function reverseSale(Order $order): array
    {
        $affected = MedicineStock::where('order_id', $order->id)
            ->pluck('medicine_id')
            ->unique()
            ->values()
            ->all();

        MedicineStock::where('order_id', $order->id)->delete();

        return $affected;
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
                    "Stok {$medicine->name} {$medicine->dosage} tidak mencukupi (tersedia: {$available}, diminta: {$item['qty']})"
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
}
