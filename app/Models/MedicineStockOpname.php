<?php

namespace App\Models;

use App\Services\StockMovementService;
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

    protected static function booted(): void
    {
        // B5: menghapus dokumen menghapus baris kartu stoknya sungguhan, lalu HPP di-replay
        // dan status stok disegarkan. Gagal (R8) → dokumen tidak jadi dihapus.
        static::deleting(function (MedicineStockOpname $doc) {
            $movement = app(StockMovementService::class);
            $movement->refreshStockStatus($movement->reverseOpname($doc));
        });
    }

    public function medicineStockOpnameItems(): HasMany
    {
        return $this->hasMany(MedicineStockOpnameItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
