<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicineStockOpname extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'opname_number',
        'opname_date',
        'status',
        'description',
        'created_by',
    ];

    protected $casts = [
        'opname_date' => 'date',
    ];

    public function medicineStockOpnameItems(): HasMany
    {
        return $this->hasMany(MedicineStockOpnameItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
