<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Progress;

/**
 * Progresso num escopo qualquer — avaliação, nível ou área.
 *
 * "Respondido" é ter resposta confirmada, não ter pontuação maior que zero:
 * marcar zero é um ato clínico, deixar em branco é pendência.
 */
final class Progress
{
    public function __construct(
        public readonly int $answered,
        public readonly int $total,
    ) {}

    public function pending(): int
    {
        return max(0, $this->total - $this->answered);
    }

    public function isComplete(): bool
    {
        return $this->total > 0 && $this->answered >= $this->total;
    }

    public function percent(): int
    {
        return $this->total === 0 ? 0 : (int) round($this->answered / $this->total * 100);
    }

    /** "32 de 45" */
    public function format(): string
    {
        return "{$this->answered} de {$this->total}";
    }

    /** "3/5" — versão compacta para a navegação por área. */
    public function compact(): string
    {
        return "{$this->answered}/{$this->total}";
    }
}
