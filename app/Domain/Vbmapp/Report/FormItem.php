<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Report;

/**
 * Um marco no formulário impresso.
 *
 * Serve aos dois momentos do mesmo documento: em branco, é ficha de campo —
 * o psicólogo leva à sessão e marca à mão; respondido, é registro do que já
 * foi apurado. `checklist` é o que dá utilidade real ao formulário vazio: a
 * mesma lista de estímulos, palavras ou critérios que a tela mostra, impressa
 * para marcar com caneta.
 */
final class FormItem
{
    /** @param list<string> $checklist O que marcar à mão quando ainda não respondido. */
    public function __construct(
        public readonly string $code,
        public readonly int $position,
        public readonly string $statement,
        public readonly ?string $criteriaFull,
        public readonly ?string $criteriaHalf,
        public readonly bool $answered,
        public readonly ?string $scoreLabel,
        public readonly string $exemplaresSummary,
        public readonly array $checklist,
        public readonly ?string $notes,
        public readonly ?int $observationMinutes,
    ) {}
}
