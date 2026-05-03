<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicineStockOpnameItem extends Model
{
    protected $fillable = [
        'medicine_stock_opname_id',
        'medicine_id',
        'qty',
        'type_account',
        'hpp',
        'note',
    ];

    public function medicineStockOpname(): BelongsTo
    {
        return $this->belongsTo(MedicineStockOpname::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
