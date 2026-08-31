<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Assessment\AssessmentStatus;
use App\Domain\Learner\Age;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

class Learner extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'name', 'birth_date', 'father_name', 'mother_name',
        'contact_phone', 'photo_path', 'image_consent_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'image_consent_at' => 'datetime'];
    }

    /** Há termo de uso de imagem assinado pelos responsáveis? */
    public function temConsentimentoDeImagem(): bool
    {
        return $this->image_consent_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /** Idade calculada na referência dada — nunca "hoje" para fins de laudo. */
    public function ageAt(\DateTimeInterface|string $reference): Age
    {
        return Age::between($this->birth_date, $reference);
    }

    /** Idade calculada agora, só para exibição informativa no painel. */
    public function currentAge(): Age
    {
        return Age::between($this->birth_date, now());
    }

    public function hasCompletedAssessment(): bool
    {
        return $this->assessments()
            ->where('status', AssessmentStatus::Completed->value)
            ->exists();
    }

    /**
     * URL assinada e temporária para a foto — nunca um link direto.
     * Ver LearnerPhotoController e .claude/specs/02-modelo-de-dados.md.
     */
    public function photoUrl(string $tamanho = 'padrao'): ?string
    {
        if ($this->photo_path === null) {
            return null;
        }

        return URL::temporarySignedRoute('aprendizes.foto', now()->addMinutes(10), [
            'learner' => $this->id,
            'tamanho' => $tamanho,
        ]);
    }
}
