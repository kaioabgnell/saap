<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Scoring;

/**
 * Alguns critérios têm componente qualitativo que a contagem não resolve.
 *
 * Caso real — Mando 4-M: 1 ponto para 5 mandos espontâneos *diferentes*,
 * ½ ponto para 5 mandos espontâneos *sempre com a mesma palavra*. A contagem
 * é idêntica; o que difere é a qualidade. Nesses marcos o sistema calcula uma
 * sugestão e exige confirmação explícita do psicólogo.
 */
enum ScoringMode: string
{
    case Auto = 'auto';
    case Assisted = 'assisted';

    public function requiresConfirmation(): bool
    {
        return $this === self::Assisted;
    }
}
