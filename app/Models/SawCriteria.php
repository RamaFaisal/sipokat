<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SawCriteria extends Model
{
    protected $table = 'saw_criteria';

    protected $fillable = [
        'code',
        'name',
        'type',
        'weight',
        'scale_rules',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'weight' => 'decimal:3',
        'scale_rules' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function isBenefit(): bool
    {
        return $this->type === 'benefit';
    }

    public function isCost(): bool
    {
        return $this->type === 'cost';
    }

    /**
     * Konversi nilai mentah ke skala 1-5 berdasarkan scale_rules.
     * Format scale_rules: [{"min": null|number, "max": null|number, "score": 1..5}, ...]
     * null = open-ended (min null = "≤ max", max null = "≥ min").
     */
    public function convertToScore(int|float|null $rawValue): int
    {
        if ($rawValue === null) {
            return 0;
        }

        foreach ($this->scale_rules ?? [] as $rule) {
            $min = $rule['min'] ?? null;
            $max = $rule['max'] ?? null;

            $matchMin = $min === null || $rawValue >= $min;
            $matchMax = $max === null || $rawValue <= $max;

            if ($matchMin && $matchMax) {
                return (int) ($rule['score'] ?? 0);
            }
        }

        return 0;
    }
}
