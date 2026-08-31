<?php

declare(strict_types=1);

namespace App\Models\Vbmapp;

use App\Domain\Vbmapp\Scoring\ResponseType;
use App\Domain\Vbmapp\Scoring\ScoringMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $table = 'vbmapp_items';

    protected $fillable = [
        'area_id', 'level', 'position', 'code',
        'statement', 'objective', 'materials', 'examples',
        'criteria_full', 'criteria_half',
        'response_type', 'threshold_full', 'threshold_half',
        'scoring_mode', 'observation_minutes',
        'fixed_list', 'matrix_columns',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'position' => 'integer',
            'threshold_full' => 'integer',
            'threshold_half' => 'integer',
            'observation_minutes' => 'integer',
            'response_type' => ResponseType::class,
            'scoring_mode' => ScoringMode::class,
            'fixed_list' => 'array',
            'matrix_columns' => 'array',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function stimuli(): HasMany
    {
        return $this->hasMany(Stimulus::class, 'item_id')->orderBy('position');
    }

    /** O marco admite meio ponto? Nem todos admitem — ver Ouvinte 2-M. */
    public function hasHalfPoint(): bool
    {
        return $this->threshold_half !== null;
    }
}
