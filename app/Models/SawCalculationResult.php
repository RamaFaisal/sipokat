<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SawCalculationResult extends Model
{
    protected $table = 'saw_calculation_results';

    protected $fillable = [
        'saw_calculation_id',
        'medicine_id',
        'c1_raw', 'c2_raw', 'c3_raw', 'c4_raw',
        'c1_score', 'c2_score', 'c3_score', 'c4_score',
        'c1_norm', 'c2_norm', 'c3_norm', 'c4_norm',
        'preference_value',
        'rank',
        'sort_order',
        'c1_stock',
        'c1_min_stock',
    ];

    protected $casts = [
        'c1_raw' => 'decimal:2',
        'c2_raw' => 'decimal:2',
        'c3_raw' => 'decimal:2',
        'c4_raw' => 'decimal:2',
        'c1_score' => 'integer',
        'c2_score' => 'integer',
        'c3_score' => 'integer',
        'c4_score' => 'integer',
        'c1_norm' => 'decimal:6',
        'c2_norm' => 'decimal:6',
        'c3_norm' => 'decimal:6',
        'c4_norm' => 'decimal:6',
        'preference_value' => 'decimal:6',
        'rank' => 'integer',
        'sort_order' => 'integer',
        'c1_stock' => 'integer',
        'c1_min_stock' => 'integer',
    ];

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(SawCalculation::class, 'saw_calculation_id');
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
