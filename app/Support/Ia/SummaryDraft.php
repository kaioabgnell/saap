<?php

declare(strict_types=1);

namespace App\Support\Ia;

/** O que o modelo devolveu, antes de virar linha no banco. */
final class SummaryDraft
{
    public function __construct(
        public readonly string $body,
        public readonly string $model,
        public readonly ?int $tokensUsed,
    ) {}
}
