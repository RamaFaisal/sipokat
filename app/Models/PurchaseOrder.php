<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PO = catatan internal pesanan ke satu PBF, dibuat setelah ketersediaan dikonfirmasi
 * lewat telepon/WA (rencana-revisi-2026-09 P1). Bukan dokumen ke PBF; tidak ada harga
 * final, pajak, atau pembayaran di sini — semua itu ada di faktur → RO.
 *
 * status_receive_order diturunkan dari sisa (P7): pending → partial → received; closed
 * hanya lewat aksi "Tutup PO" (P8).
 */
class PurchaseOrder extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'po_number',
        'supplier_id',
        'po_date',
        'status_receive_order',
        'created_by',
    ];

    protected $casts = [
        'po_date' => 'date',
    ];

    protected $attributes = [
        'status_receive_order' => self::STATUS_PENDING,
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiveOrders(): HasMany
    {
        return $this->hasMany(ReceiveOrder::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status_receive_order, [self::STATUS_PENDING, self::STATUS_PARTIAL], true);
    }

    /** Σ baris (satuan jual × harga per satuan jual) — tampil saja, tidak disimpan (P3). */
    public function estimatedTotal(): float
    {
        return (float) $this->items->sum(fn (PurchaseOrderItem $i) => $i->qty * (float) $i->price);
    }

    /**
     * Hitung ulang status penerimaan dari sisa tiap item (P7). PO yang sudah ditutup dibiarkan.
     */
    public function refreshReceiveStatus(): void
    {
        if ($this->status_receive_order === self::STATUS_CLOSED) {
            return;
        }

        $items = $this->items()->get();
        $received = $this->receivedQtyByMedicine();

        $anyReceived = false;
        $allReceived = $items->isNotEmpty();

        foreach ($items as $item) {
            $got = (int) ($received[$item->medicine_id] ?? 0);
            if ($got > 0) {
                $anyReceived = true;
            }
            if ($got < (int) $item->qty) {
                $allReceived = false;
            }
        }

        $status = $allReceived ? self::STATUS_RECEIVED : ($anyReceived ? self::STATUS_PARTIAL : self::STATUS_PENDING);

        // Tulis lewat query, bukan save(): instance ini bisa saja salinan lama (mis. relasi
        // yang di-cache RO) sehingga pengecekan dirty Eloquent tidak bisa dipercaya.
        static::query()->whereKey($this->id)->update(['status_receive_order' => $status]);
        $this->setAttribute('status_receive_order', $status);
        $this->syncOriginalAttribute('status_receive_order');
    }

    /**
     * Jumlah diterima per obat (satuan jual) dari semua RO yang belum dihapus.
     *
     * @return array<int,int> medicine_id => qty
     */
    public function receivedQtyByMedicine(): array
    {
        return ReceiveOrderItem::query()
            ->whereHas('receiveOrder', fn ($q) => $q->where('purchase_order_id', $this->id))
            ->selectRaw('medicine_id, SUM(qty) as total')
            ->groupBy('medicine_id')
            ->pluck('total', 'medicine_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Sisa yang belum diterima per item (satuan jual).
     *
     * @return array<int,int> medicine_id => sisa
     */
    public function remainingByMedicine(): array
    {
        $received = $this->receivedQtyByMedicine();

        return $this->items()->get()
            ->mapWithKeys(fn (PurchaseOrderItem $i) => [
                $i->medicine_id => max(0, (int) $i->qty - (int) ($received[$i->medicine_id] ?? 0)),
            ])
            ->all();
    }
}
