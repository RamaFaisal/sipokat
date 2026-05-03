<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiveOrderItem extends Model
{
    protected $fillable = [
        'receive_order_id',
        'medicine_id',
        'medicine_name',
        'qty',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function receiveOrder(): BelongsTo
    {
        return $this->belongsTo(ReceiveOrder::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
