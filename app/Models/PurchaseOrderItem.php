<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Baris PO. qty dan price selalu dalam satuan jual obat; pack_* adalah jejak input
 * dalam kemasan (rencana-revisi-2026-09 P4).
 */
class PurchaseOrderItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'purchase_order_id',
        'medicine_id',
        'pack_unit_id',
        'pack_size',
        'pack_qty',
        'qty',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function packUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'pack_unit_id');
    }
}
