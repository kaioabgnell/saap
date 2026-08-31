<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

use RuntimeException;

/**
 * Lançada ao tentar abrir uma avaliação enquanto outra do mesmo aprendiz
 * ainda está aberta (não iniciada ou em andamento). Ver 02-modelo-de-dados.md.
 */
class AssessmentAlreadyOpenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Este aprendiz já tem uma avaliação em aberto. Conclua ou cancele-a antes de abrir outra.');
    }
}
