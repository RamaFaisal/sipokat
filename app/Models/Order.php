<?php

namespace App\Models;

use App\Services\StockMovementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_code',
        'no_payment',
        'order_date',
        'grand_total',
        'status',
        'note',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'grand_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // B5: menghapus dokumen menghapus baris kartu stoknya sungguhan, lalu HPP di-replay
        // dan status stok disegarkan. Gagal (R8) → dokumen tidak jadi dihapus.
        static::deleting(function (Order $doc) {
            $movement = app(StockMovementService::class);
            $movement->refreshStockStatus($movement->reverseSale($doc));
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
