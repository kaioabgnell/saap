<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Vbmapp\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Item */
class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'position' => $this->position,
            'code' => $this->code,
            'statement' => $this->statement,
            'objective' => $this->objective,
            'materials' => $this->materials,
            'examples' => $this->examples,
            'criteria_full' => $this->criteria_full,
            'criteria_half' => $this->criteria_half,
            'response_type' => $this->response_type->value,
            'threshold_full' => $this->threshold_full,
            'threshold_half' => $this->threshold_half,
            'scoring_mode' => $this->scoring_mode->value,
            'observation_minutes' => $this->observation_minutes,
            'fixed_list' => $this->fixed_list,
            'matrix_columns' => $this->matrix_columns,
            'stimuli' => $this->whenLoaded('stimuli', fn () => $this->stimuli->map(fn ($e) => [
                'id' => $e->id,
                'label' => $e->label,
                'url' => Storage::disk('public')->url($e->image_path),
            ])->values()),
        ];
    }
}
