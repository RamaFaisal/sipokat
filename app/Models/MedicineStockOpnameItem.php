<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu penyesuaian opname pada satu lapisan (rencana-revisi-2026-09 §5.3): C mengurangi lapisan
 * `layer_stock_id`; D menambah lapisan baru dengan batch/ED (atau menyalin lapisan acuan).
 */
class MedicineStockOpnameItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'medicine_stock_opname_id',
        'medicine_id',
        'layer_stock_id',
        'batch_number',
        'expired_date',
        'qty',
        'type_account',
        'hpp',
        'note',
    ];

    protected $casts = [
        'expired_date' => 'date',
        'hpp' => 'decimal:2',
    ];

    public function medicineStockOpname(): BelongsTo
    {
        return $this->belongsTo(MedicineStockOpname::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(MedicineStock::class, 'layer_stock_id');
    }
}
