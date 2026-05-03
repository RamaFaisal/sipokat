<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'medicine_id',
        'description',
        'qty',
        'price',
        'discount',
        'total',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total' => 'decimal:2',
        'discount' => 'double',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
