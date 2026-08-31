<?php

declare(strict_types=1);

namespace App\Application\Learner;

use App\Models\Learner;
use App\Models\User;
use App\Support\ImageUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class CreateLearner
{
    public function __construct(private readonly ImageUploader $images) {}

    /** @param array{name: string, birth_date: string, father_name?: ?string, mother_name?: ?string, contact_phone?: ?string, notes?: ?string} $data */
    public function handle(User $user, array $data, ?UploadedFile $photo = null): Learner
    {
        return DB::transaction(function () use ($user, $data, $photo) {
            $learner = Learner::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'birth_date' => $data['birth_date'],
                'father_name' => $data['father_name'] ?? null,
                'mother_name' => $data['mother_name'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'image_consent_at' => empty($data['image_consent']) ? null : now(),
            ]);

            if ($photo !== null) {
                // Foto de aprendiz é dado sensível: disco privado, nunca 'public'.
                $path = $this->images->store($photo, 'local', "aprendizes/{$learner->id}");
                $learner->update(['photo_path' => $path]);
            }

            return $learner->refresh();
        });
    }
}
