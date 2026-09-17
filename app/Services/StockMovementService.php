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
     * Sinkronkan lapisan dengan item RO setelah RO diedit (R8): batch/ED/harga selalu boleh
     * berubah; jumlah hanya boleh turun sampai sisa lapisan; item yang dihapus hanya boleh
     * bila lapisannya belum dikonsumsi; item baru menjadi lapisan baru.
     *
     * @throws \RuntimeException
     */
    public function syncReceipt(ReceiveOrder $receiveOrder): array
    {
        $receiveOrder->loadMissing('purchaseOrder');
        $description = $receiveOrder->purchaseOrder
            ? 'Penerimaan dari '.$receiveOrder->purchaseOrder->po_number
            : 'Penerimaan '.$receiveOrder->receive_order_number;

        $affected = [];
        $items = $receiveOrder->items()->get()->keyBy('id');

        $layers = MedicineStock::query()
            ->where('receive_order_id', $receiveOrder->id)
            ->where('type_account', 'D')
            ->withSum('consumptions', 'qty')
            ->get();

        foreach ($layers as $layer) {
            $consumed = (int) ($layer->consumptions_sum_qty ?? 0);
            $item = $layer->receive_order_item_id ? $items->get($layer->receive_order_item_id) : null;
            $affected[] = $layer->medicine_id;

            if (! $item) {
                if ($consumed > 0) {
                    throw new \RuntimeException("Batch {$layer->batch_number} tidak dapat dihapus: sudah terjual {$consumed}. Koreksi lewat Stok Opname.");
                }
                $layer->delete();

                continue;
            }

            if ((int) $item->qty < $consumed) {
                throw new \RuntimeException("Jumlah batch {$item->batch_number} tidak boleh kurang dari yang sudah terjual ({$consumed}). Koreksi lewat Stok Opname.");
            }

            $layer->update([
                'qty' => $item->qty,
                'hpp' => $item->price,
                'batch_number' => $item->batch_number,
                'expired_date' => $item->expired_date,
                'date' => $receiveOrder->receive_date,
                'description' => $description,
            ]);
            $affected[] = $item->medicine_id;
            $items->forget($item->id);
        }

        // Sisanya item baru → lapisan baru.
        foreach ($items as $item) {
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
     * Tulis entri kredit (stok keluar) untuk seluruh item penjualan, dialokasikan FEFO ke
     * lapisan yang belum kedaluwarsa (F0, F1): satu item bisa menjadi beberapa baris C.
     *
     * @throws \RuntimeException bila stok tersedia tidak mencukupi.
     */
    public function recordSale(Order $order): array
    {
        $affected = [];

        foreach ($order->items()->get() as $item) {
            $hpp = $this->stockCard->currentHpp($item->medicine_id) ?? 0;

            foreach ($this->allocateFefo((int) $item->medicine_id, (int) $item->qty) as [$layer, $take]) {
                MedicineStock::create([
                    'medicine_id' => $item->medicine_id,
                    'qty' => $take,
                    'type_account' => 'C',
                    'date' => $order->order_date,
                    'hpp' => $hpp,
                    'order_id' => $order->id,
                    'layer_stock_id' => $layer->id,
                    'description' => 'Penjualan '.$order->order_code,
                    'created_by' => auth()->id(),
                ]);
            }

            $affected[] = $item->medicine_id;
        }

        return $this->replayAll($affected);
    }

    /**
     * Alokasi FEFO (§5.2): lapisan dengan sisa > 0 yang belum kedaluwarsa (B1), lapisan tanpa ED
     * (data lama) paling dulu, lalu ED terdekat, lalu id.
     *
     * @return array<int, array{0: MedicineStock, 1: int}> pasangan [lapisan, jumlah diambil]
     *
     * @throws \RuntimeException bila total sisa lapisan yang layak < qty
     */
    public function allocateFefo(int $medicineId, int $qty): array
    {
        if ($qty <= 0) {
            return [];
        }

        $plan = [];
        $left = $qty;

        foreach ($this->stockCard->sellableLayers($medicineId) as $layer) {
            if ($left <= 0) {
                break;
            }
            $take = min($left, (int) $layer->remaining);
            if ($take <= 0) {
                continue;
            }
            $plan[] = [$layer, $take];
            $left -= $take;
        }

        if ($left > 0) {
            $medicine = Medicine::find($medicineId);
            $available = $qty - $left;
            throw new \RuntimeException(
                'Stok '.($medicine?->name ?? $medicineId)." tidak mencukupi (tersedia: {$available}, diminta: {$qty})"
            );
        }

        return $plan;
    }

    /**
     * F6: atribusikan baris C lama (tanpa lapisan) secara FEFO historis — urut ED naik tanpa
     * memandang kedaluwarsa (saat terjual dulu batch itu masih layak), lapisan tanpa ED paling
     * dulu. Baris yang melintasi dua lapisan dipecah. Deterministik; aman dijalankan ulang.
     *
     * @return int jumlah satuan yang tidak bisa diatribusikan (data lama tidak konsisten)
     */
    public function backfillLayers(int $medicineId): int
    {
        return DB::transaction(function () use ($medicineId) {
            $layers = MedicineStock::layers()
                ->where('medicine_id', $medicineId)
                ->withSum('consumptions', 'qty')
                ->orderByRaw('CASE WHEN expired_date IS NULL THEN 0 ELSE 1 END')
                ->orderBy('expired_date')
                ->orderBy('id')
                ->get();

            $remaining = $layers->mapWithKeys(fn ($l) => [$l->id => (int) $l->qty - (int) ($l->consumptions_sum_qty ?? 0)])->all();
            $unattributed = 0;

            $rows = MedicineStock::query()
                ->where('medicine_id', $medicineId)
                ->where('type_account', 'C')
                ->whereNull('layer_stock_id')
                ->orderBy('date')
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                $left = (int) $row->qty;
                $first = true;

                foreach ($layers as $layer) {
                    if ($left <= 0) {
                        break;
                    }
                    $take = min($left, $remaining[$layer->id]);
                    if ($take <= 0) {
                        continue;
                    }

                    if ($first) {
                        $row->update(['qty' => $take, 'layer_stock_id' => $layer->id]);
                        $first = false;
                    } else {
                        $copy = $row->replicate();
                        $copy->qty = $take;
                        $copy->layer_stock_id = $layer->id;
                        $copy->save();
                    }

                    $remaining[$layer->id] -= $take;
                    $left -= $take;
                }

                if ($left > 0) {
                    // Tidak ada lapisan tersisa: biarkan (sebagian) tanpa atribusi, catat.
                    if ($first) {
                        $unattributed += $left;
                    } else {
                        $rest = $row->replicate();
                        $rest->qty = $left;
                        $rest->layer_stock_id = null;
                        $rest->save();
                        $unattributed += $left;
                    }
                }
            }

            return $unattributed;
        });
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
            $hpp = $this->stockCard->currentHpp($item->medicine_id);

            // Penambahan hanya untuk obat yang sudah punya HPP; stok awal obat baru lewat RO (§7.3).
            if ($item->type_account === 'D' && $hpp === null) {
                $medicine = Medicine::find($item->medicine_id);
                throw new \RuntimeException(
                    'Obat '.($medicine?->name ?? $item->medicine_id).' belum punya riwayat harga — masukkan stok awal lewat Penerimaan, bukan opname.'
                );
            }

            $layer = $item->layer_stock_id ? MedicineStock::find($item->layer_stock_id) : null;
            $isOut = $item->type_account === 'C';

            MedicineStock::create([
                'medicine_id' => $item->medicine_id,
                'qty' => $item->qty,
                'type_account' => $item->type_account,
                // Pengurangan menunjuk lapisan yang dikoreksi (termasuk lapisan kedaluwarsa — satu-satunya
                // jalan mengeluarkannya, F1). Penambahan membuat lapisan baru dengan batch/ED yang disebut,
                // atau menyalin batch/ED lapisan acuan (selisih lebih pada batch yang ada).
                'layer_stock_id' => $isOut ? $item->layer_stock_id : null,
                'batch_number' => $isOut ? null : ($item->batch_number ?? $layer?->batch_number),
                'expired_date' => $isOut ? null : ($item->expired_date ?? $layer?->expired_date),
                'date' => $opname->opname_date,
                'hpp' => $hpp ?? 0,
                'medicine_stock_opname_id' => $opname->id,
                'description' => 'opname dari '.$opname->opname_number.($item->note ? ' — '.$item->note : ''),
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
            $available = $this->stockCard->availableStock($medicine->id);

            if ((int) $item['qty'] > $available) {
                throw new \RuntimeException(
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
