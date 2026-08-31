<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin Assessment */
class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $niveis = $this->whenLoaded('levels', fn () => $this->levels, collect());
        $iniciados = $niveis instanceof Collection
            ? $niveis->pluck('level')->map(intval(...))->all()
            : [];

        return [
            'id' => $this->id,
            'learner_id' => $this->learner_id,
            'instrument' => $this->instrument,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'applied_on' => $this->applied_on->toDateString(),
            'age_at_application' => $this->whenLoaded('learner',
                fn () => $this->learner->ageAt($this->applied_on)->format()),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'locked' => $this->isLocked(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'observations' => $this->observations,
            'levels' => $this->whenLoaded('levels', fn () => $this->levels->map(fn ($n) => [
                'level' => $n->level,
                'status' => $n->status->value,
                'answered' => $n->answered_count,
                'total' => $n->total_count,
                'score_total' => (float) $n->score_total,
                'completed_at' => $n->completed_at?->toIso8601String(),
            ])->values()),
            'has_report' => $this->whenLoaded('reportSnapshot', fn () => $this->reportSnapshot !== null),
        ];
    }
}
