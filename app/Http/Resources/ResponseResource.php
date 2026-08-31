<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Response */
class ResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'item_id' => $this->item_id,
            'score' => (float) $this->score,
            'computed_score' => (float) $this->computed_score,
            'is_overridden' => (bool) $this->is_overridden,
            'override_reason' => $this->override_reason,
            'answered' => $this->isAnswered(),
            'notes' => $this->notes,
            'entries' => $this->whenLoaded('entries', fn () => $this->entries->map(fn ($e) => [
                'position' => $e->position,
                'stimulus_id' => $e->stimulus_id,
                'list_key' => $e->list_key,
                'column_key' => $e->column_key,
                'text_value' => $e->text_value,
                'is_checked' => (bool) $e->is_checked,
            ])->values()),
        ];
    }
}
