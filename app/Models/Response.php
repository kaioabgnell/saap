<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Vbmapp\Item;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Response extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id', 'item_id', 'score', 'computed_score',
        'is_overridden', 'override_reason', 'notes', 'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'computed_score' => 'float',
            'is_overridden' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ResponseEntry::class)->orderBy('position');
    }

    /**
     * Respondido é ter confirmação, não ter pontuação maior que zero:
     * marcar zero é um ato clínico, deixar em branco é pendência.
     */
    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }
}
