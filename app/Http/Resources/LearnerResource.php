<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Learner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Learner */
class LearnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'birth_date' => $this->birth_date->toDateString(),
            'current_age' => $this->currentAge()->format(),
            'father_name' => $this->father_name,
            'mother_name' => $this->mother_name,
            'contact_phone' => $this->contact_phone,
            'notes' => $this->notes,
            'photo_url' => $this->photoUrl(),
            // Sem termo assinado não há foto: expor o estado evita que o app
            // ofereça o envio de imagem para quem não pode enviar.
            'image_consent_at' => $this->image_consent_at?->toIso8601String(),
            'assessments' => AssessmentResource::collection($this->whenLoaded('assessments')),
        ];
    }
}
