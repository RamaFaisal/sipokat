<?php

namespace App\Models;

use App\Services\StockMovementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu RO = satu faktur PBF (rencana-revisi-2026-09 R10). Tidak ada status: RO menulis
 * kartu stok saat disimpan (R5). PO opsional — hanya pengisi awal (R11).
 */
class ReceiveOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receive_order_number',
        'invoice_number',
        'purchase_order_id',
        'supplier_id',
        'receive_date',
        'received_by',
    ];

    protected $casts = [
        'receive_date' => 'date',
    ];

    protected static function booted(): void
    {
        // B5: menghapus dokumen menghapus baris kartu stoknya sungguhan, lalu HPP di-replay
        // dan status stok disegarkan. Gagal (R8) → dokumen tidak jadi dihapus.
        static::deleting(function (ReceiveOrder $doc) {
            $movement = app(StockMovementService::class);
            $movement->refreshStockStatus($movement->reverseReceipt($doc));
        });

        // Sisa PO berubah begitu RO hilang (P7).
        static::deleted(function (ReceiveOrder $doc) {
            $doc->purchaseOrder?->refreshReceiveStatus();
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReceiveOrderItem::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** Σ baris = Total faktur (harga sudah termasuk PPN, R13). */
    public function total(): float
    {
        return (float) $this->items->sum(fn (ReceiveOrderItem $i) => $i->qty * (float) $i->price);
    }

    /** Nomor RO berikutnya: RO{YYYYMMDD}-XXXX, banyak RO per hari (R10). */
    public static function nextNumber(?\DateTimeInterface $date = null): string
    {
        $prefix = 'RO'.($date ? $date->format('Ymd') : now()->format('Ymd')).'-';

        $last = static::withTrashed()
            ->where('receive_order_number', 'like', $prefix.'%')
            ->orderByDesc('receive_order_number')
            ->value('receive_order_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return sprintf('%s%04d', $prefix, $next);
    }
}
