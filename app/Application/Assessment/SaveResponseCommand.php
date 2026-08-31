<?php

declare(strict_types=1);

namespace App\Application\Assessment;

/**
 * Entrada da gravação de um marco.
 *
 * @param  list<array<string, mixed>>  $entries  Exemplares registrados. Cada um
 *                                               com as chaves de response_entries: position, text_value, is_checked,
 *                                               stimulus_id, list_key, column_key.
 * @param  float|null  $explicitScore  Pontuação decidida pelo psicólogo. Usada
 *                                     em dois casos: confirmar o critério de um marco 'assisted', e registrar
 *                                     zero deliberado — que é ato clínico, diferente de deixar em branco.
 */
final class SaveResponseCommand
{
    public function __construct(
        public readonly int $assessmentId,
        public readonly int $itemId,
        public readonly array $entries = [],
        public readonly ?string $notes = null,
        public readonly ?float $explicitScore = null,
        public readonly ?string $overrideReason = null,
    ) {}
}
