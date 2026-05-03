<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'medicine_id',
        'qty',
        'price',
        'discount',
        'total',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
