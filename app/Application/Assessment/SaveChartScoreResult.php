<?php

declare(strict_types=1);

namespace App\Application\Assessment;

use App\Domain\Vbmapp\Progress\Progress;

/**
 * Saída de um clique na grade de lançamento: o novo estado da célula e os
 * contadores já recalculados, para a tela não ter de refazer conta nenhuma.
 */
final class SaveChartScoreResult
{
    public function __construct(
        public readonly int $itemId,
        public readonly ?float $score,
        public readonly string $state,
        public readonly Progress $levelProgress,
        public readonly float $levelScore,
        public readonly Progress $assessmentProgress,
        public readonly string $savedAt,
    ) {}
}
