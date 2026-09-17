<?php

declare(strict_types=1);

namespace App\Application\Schedule;

/**
 * O que a tela de novo agendamento manda para o caso de uso.
 *
 * `permitirConflito` é o segundo turno da conversa: a primeira chamada vem
 * com false e volta com a lista de conflitos; a tela pergunta; a segunda vem
 * com true. Ver a "Regra do conflito de horário" na spec da F11.
 */
final class ScheduleAppointmentCommand
{
    public function __construct(
        public readonly int $userId,
        public readonly int $learnerId,
        public readonly string $startsAt,
        public readonly int $durationMinutes = 50,
        public readonly int $repeatWeeks = 1,
        public readonly ?string $bookingNote = null,
        public readonly bool $permitirConflito = false,
    ) {}
}
