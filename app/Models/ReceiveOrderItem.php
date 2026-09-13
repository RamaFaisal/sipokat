<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu baris faktur PBF. qty dan price dalam satuan jual; pack_* jejak konversi kemasan (R6).
 * expired_date selalu tanggal 1 bulan ED — obat dianggap kedaluwarsa sejak awal bulan (R3).
 */
class ReceiveOrderItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receive_order_id',
        'medicine_id',
        'medicine_name',
        'pack_unit_id',
        'pack_size',
        'pack_qty',
        'qty',
        'price',
        'batch_number',
        'expired_date',
    ];

    protected $casts = [
        'price' => 'decimal:2',
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

    public function packUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'pack_unit_id');
    }

    /** Lapisan kartu stok (baris D) yang lahir dari item ini. */
    public function layer(): HasOne
    {
        return $this->hasOne(MedicineStock::class, 'receive_order_item_id')->where('type_account', 'D');
    }
}
