<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Assessment\LevelStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id', 'level', 'status', 'started_at', 'completed_at',
        'answered_count', 'total_count', 'score_total',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'status' => LevelStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'answered_count' => 'integer',
            'total_count' => 'integer',
            'score_total' => 'float',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function isComplete(): bool
    {
        return $this->answered_count >= $this->total_count;
    }
}
