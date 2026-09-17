<?php

declare(strict_types=1);

namespace App\Application\Schedule;

use App\Domain\Schedule\TimeSlot;
use App\Models\Appointment;

/**
 * Uma ocorrência que colide com atendimentos já marcados.
 *
 * Carrega os agendamentos existentes, e não só um "sim, conflita", porque a
 * tela precisa dizer **quem** já está naquele horário. Um alerta que não
 * nomeia a criança não ajuda a decidir.
 */
final class ScheduleConflict
{
    /** @param  list<Appointment>  $existentes */
    public function __construct(
        public readonly TimeSlot $ocorrencia,
        public readonly array $existentes,
    ) {}

    /** "quinta-feira, 10/09 às 15h" — o cabeçalho da linha do alerta. */
    public function quando(): string
    {
        return $this->ocorrencia->starts->format('d/m/Y \à\s H:i');
    }

    /** Os nomes de quem já ocupa o horário, para a frase do alerta. */
    public function nomes(): string
    {
        return collect($this->existentes)
            ->map(fn (Appointment $a) => $a->learner->name)
            ->unique()
            ->implode(', ');
    }
}
