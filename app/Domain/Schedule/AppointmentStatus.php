<?php

declare(strict_types=1);

namespace App\Domain\Schedule;

/**
 * Estado do agendamento. Ver .claude/specs/fases/F11-agenda-e-atendimentos.md.
 *
 *                     ┌──> cancelled
 *                     │
 *    scheduled ───────┼──> no_show
 *        │            │
 *        └──> in_progress ──> completed
 *
 * `completed` e `cancelled` são terminais. Reabrir atendimento concluído não
 * existe — o que existe é aditamento.
 */
enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendado',
            self::InProgress => 'Em atendimento',
            self::Completed => 'Concluído',
            self::NoShow => 'Faltou',
            self::Cancelled => 'Cancelado',
        };
    }

    /** Cor semântica — ver 03-design-system.md. Nunca aparece sem o rótulo. */
    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'neutral',
            self::InProgress => 'primary',
            self::Completed => 'success',
            self::NoShow => 'warning',
            self::Cancelled => 'muted',
        };
    }

    /**
     * Ocupa horário? Só o que está ativo entra na conta do conflito — um
     * atendimento cancelado libera a hora, e um concluído já passou.
     */
    public function ocupaHorario(): bool
    {
        return $this === self::Scheduled || $this === self::InProgress;
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /** Estados que ocupam horário, para a cláusula `whereIn` do conflito. */
    public static function ativos(): array
    {
        return [self::Scheduled->value, self::InProgress->value];
    }
}
