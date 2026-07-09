<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReceiveOrderItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receive_order_id',
        'medicine_id',
        'medicine_name',
        'qty',
        'price',
        'batch_number',
        'manufacture_date',
        'expired_date',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'manufacture_date' => 'date',
        'expired_date' => 'date',
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
