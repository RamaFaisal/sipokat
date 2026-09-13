<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SawCalculation extends Model
{
    protected $table = 'saw_calculations';

    protected $fillable = [
        'calculated_at',
        'calculated_by',
        'period_start',
        'period_end',
        'trigger_type',
        'criteria_snapshot',
        'total_alternatives',
        'excluded_count',
        'notes',
    ];

    protected $casts = [
        'calculated_at' => 'datetime',
        'period_start' => 'date',
        'period_end' => 'date',
        'criteria_snapshot' => 'array',
        'total_alternatives' => 'integer',
        'excluded_count' => 'integer',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(SawCalculationResult::class, 'saw_calculation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function scopeLatestCalculation($query)
    {
        return $query->orderByDesc('calculated_at');
    }
}
