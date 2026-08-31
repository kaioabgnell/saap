<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Assessment\AssessmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'learner_id', 'user_id', 'instrument', 'status',
        'applied_on', 'started_at', 'completed_at', 'locked_at',
        'cancelled_at', 'cancel_reason', 'observations',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssessmentStatus::class,
            'applied_on' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'locked_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(AssessmentLevel::class)->orderBy('level');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    public function reportSnapshot(): HasOne
    {
        return $this->hasOne(ReportSnapshot::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /** @return list<int> níveis efetivamente iniciados */
    public function startedLevels(): array
    {
        return $this->levels->pluck('level')->map(intval(...))->sort()->values()->all();
    }
}
