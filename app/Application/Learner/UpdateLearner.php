<?php

declare(strict_types=1);

namespace App\Application\Learner;

use App\Models\Learner;
use App\Support\ImageUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class UpdateLearner
{
    public function __construct(private readonly ImageUploader $images) {}

    /** @param array{name: string, birth_date: string, father_name?: ?string, mother_name?: ?string, contact_phone?: ?string, notes?: ?string} $data */
    public function handle(Learner $learner, array $data, ?UploadedFile $photo = null): Learner
    {
        return DB::transaction(function () use ($learner, $data, $photo) {
            // Consentimento é estado, não campo de texto: marcar registra a data
            // (preservando a original, que é o dado que a LGPD pergunta);
            // desmarcar é revogação, e revogar autorização de imagem apaga a
            // imagem — guardar a foto sem base legal é exatamente o que a
            // revogação proíbe.
            $consentiu = ! empty($data['image_consent']);

            if (! $consentiu && $learner->photo_path !== null) {
                $this->images->delete('local', $learner->photo_path);
                $learner->photo_path = null;
            }

            $learner->fill([
                'image_consent_at' => $consentiu ? ($learner->image_consent_at ?? now()) : null,
                'name' => $data['name'],
                'birth_date' => $data['birth_date'],
                'father_name' => $data['father_name'] ?? null,
                'mother_name' => $data['mother_name'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->save();

            if ($photo !== null) {
                $path = $this->images->store($photo, 'local', "aprendizes/{$learner->id}", $learner->photo_path);
                $learner->update(['photo_path' => $path]);
            }

            return $learner->refresh();
        });
    }
}
