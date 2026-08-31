<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Report;

use App\Domain\Vbmapp\Progress\Progress;

/**
 * Tudo que a view do formulário impresso precisa — nada de Eloquent aqui,
 * só o que já foi montado por BuildFormPayload.
 */
final class FormPayload
{
    /** @param list<FormArea> $areas */
    public function __construct(
        public readonly string $learnerName,
        public readonly string $ageAtApplication,
        public readonly string $appliedOn,
        public readonly string $applicatorName,
        public readonly ?string $clinicName,
        public readonly int $level,
        public readonly Progress $progress,
        public readonly array $areas,
        public readonly bool $onlyPending,
        public readonly bool $includeCriteria,
        public readonly bool $includeExamples,
        public readonly string $generatedAt,
    ) {}
}
