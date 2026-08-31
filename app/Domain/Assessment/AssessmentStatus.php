<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

/**
 * Estado da avaliação. Ver .claude/specs/02-modelo-de-dados.md — máquina de estados.
 *
 *     not_started ──> in_progress ──> completed
 *           │               │
 *           └───────────────┴───────> cancelled
 */
enum AssessmentStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Não iniciada',
            self::InProgress => 'Iniciada',
            self::Completed => 'Concluída',
            self::Cancelled => 'Cancelada',
        };
    }

    /** Cor semântica da tela de painel — ver 03-design-system.md */
    public function tone(): string
    {
        return match ($this) {
            self::NotStarted => 'neutral',
            self::InProgress => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'muted',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::NotStarted || $this === self::InProgress;
    }
}
