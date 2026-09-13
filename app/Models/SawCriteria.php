<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kriteria SAW. Dikunci tepat empat (C1–C4, K8); yang dapat diubah admin: nama, jenis,
 * bobot, dan skala konversi. Tidak ada skala per obat atau per satuan (K6).
 */
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
     * Format: [{"min": null|number, "max": null|number, "score": 1..5}, ...] — semua batas
     * INKLUSIF (B2); null = terbuka. Aturan berdesimal (C1 rasio) ditulis dua desimal tanpa
     * celah dan nilai mentahnya dibulatkan dua desimal sebelum dicocokkan (K14).
     * null → 0 = data tidak tersedia.
     */
    public function convertToScore(int|float|null $rawValue): int
    {
        if ($rawValue === null) {
            return 0;
        }

        $value = round((float) $rawValue, 2);

        foreach ($this->scale_rules ?? [] as $rule) {
            $min = isset($rule['min']) && $rule['min'] !== '' ? (float) $rule['min'] : null;
            $max = isset($rule['max']) && $rule['max'] !== '' ? (float) $rule['max'] : null;

            $matchMin = $min === null || $value >= $min;
            $matchMax = $max === null || $value <= $max;

            if ($matchMin && $matchMax) {
                return (int) ($rule['score'] ?? 0);
            }
        }

        return 0;
    }

    /** Σ bobot kriteria aktif, dibulatkan 3 desimal (K7). */
    public static function totalActiveWeight(): float
    {
        return round((float) static::query()->where('is_active', true)->sum('weight'), 3);
    }
}
