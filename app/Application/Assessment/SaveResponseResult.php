<?php

declare(strict_types=1);

namespace App\Application\Assessment;

use App\Domain\Vbmapp\Progress\Progress;
use App\Domain\Vbmapp\Scoring\Score;

/**
 * Saída da gravação: pontuação e progresso já recalculados.
 *
 * Devolver o progresso pronto evita que o cliente — componente Livewire hoje,
 * app móvel depois — duplique a regra de contagem.
 */
final class SaveResponseResult
{
    public function __construct(
        public readonly Score $score,
        public readonly Score $computedScore,
        public readonly bool $isOverridden,
        public readonly bool $isAnswered,
        public readonly bool $needsConfirmation,
        public readonly int $tally,
        public readonly Progress $areaProgress,
        public readonly Progress $levelProgress,
        public readonly Progress $assessmentProgress,
        public readonly string $savedAt,
    ) {}
}
