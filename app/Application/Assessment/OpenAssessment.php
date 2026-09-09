<?php

declare(strict_types=1);

namespace App\Application\Assessment;

use App\Domain\Assessment\AssessmentAlreadyOpenException;
use App\Domain\Assessment\AssessmentStatus;
use App\Domain\Assessment\EntryMode;
use App\Models\Assessment;
use App\Models\Learner;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Abre uma nova avaliação para o aprendiz.
 *
 * Um aprendiz pode ter várias avaliações ao longo do tempo, mas nunca duas
 * abertas ao mesmo tempo — "aberta" inclui tanto a não iniciada quanto a em
 * andamento, porque não faz sentido começar uma segunda enquanto a primeira
 * espera para ser retomada ou cancelada.
 *
 * A regra vale só entre avaliações GUIADAS. Transcrever um formulário de
 * papel de 2024 não é começar uma segunda aplicação: é arquivar uma que já
 * terminou, e uma aplicação em andamento hoje não pode impedir isso —
 * ver OpenChartAssessment.
 */
final class OpenAssessment
{
    public function handle(Learner $learner, User $user, ?string $appliedOn = null): Assessment
    {
        return DB::transaction(function () use ($learner, $user, $appliedOn) {
            $temAbertura = Assessment::where('learner_id', $learner->id)
                ->where('entry_mode', EntryMode::Guided->value)
                ->whereIn('status', [AssessmentStatus::NotStarted->value, AssessmentStatus::InProgress->value])
                ->lockForUpdate()
                ->exists();

            if ($temAbertura) {
                throw new AssessmentAlreadyOpenException;
            }

            return Assessment::create([
                'learner_id' => $learner->id,
                'user_id' => $user->id,
                'instrument' => 'vbmapp',
                'entry_mode' => EntryMode::Guided->value,
                'status' => AssessmentStatus::NotStarted->value,
                'applied_on' => $appliedOn ?? now()->toDateString(),
            ]);
        });
    }
}
