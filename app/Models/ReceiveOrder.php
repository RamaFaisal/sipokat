<?php

namespace App\Models;

use App\Services\StockMovementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReceiveOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receive_order_number',
        'purchase_order_id',
        'supplier_id',
        'receive_date',
        'description',
        'status',
        'late_arrival',
        'received_by',
    ];

    protected $casts = [
        'receive_date' => 'date',
        'late_arrival' => 'boolean',
    ];

    protected static function booted(): void
    {
        // B5: menghapus dokumen menghapus baris kartu stoknya sungguhan, lalu HPP di-replay
        // dan status stok disegarkan. Gagal (R8) → dokumen tidak jadi dihapus.
        static::deleting(function (ReceiveOrder $doc) {
            $movement = app(StockMovementService::class);
            $movement->refreshStockStatus($movement->reverseReceipt($doc));
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
}
