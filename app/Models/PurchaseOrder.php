<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'po_date',
        'sub_total',
        'discount',
        'tax',
        'total_tax',
        'shipping_cost',
        'other_cost',
        'grand_total',
        'status',
        'description',
        'estimated_arrival',
        'status_payment',
        'status_receive_order',
        'created_by',
    ];

    protected $casts = [
        'po_date' => 'date',
        'estimated_arrival' => 'date',
        'sub_total' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'other_cost' => 'decimal:2',
        'grand_total' => 'decimal:2',
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
}
