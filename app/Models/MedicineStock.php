<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicineStock extends Model
{
    protected $fillable = [
        'medicine_id',
        'qty',
        'type_account',
        'date',
        'receive_order_id',
        'medicine_stock_opname_id',
        'order_id',
        'hpp',
        'description',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'hpp' => 'decimal:2',
    ];

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function receiveOrder(): BelongsTo
    {
        return $this->belongsTo(ReceiveOrder::class);
    }

    public function medicineStockOpname(): BelongsTo
    {
        return $this->belongsTo(MedicineStockOpname::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
