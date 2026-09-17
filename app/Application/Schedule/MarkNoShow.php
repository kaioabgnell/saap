<?php

declare(strict_types=1);

namespace App\Application\Schedule;

use App\Domain\Schedule\AppointmentStatus;
use App\Models\Appointment;
use RuntimeException;

/**
 * Registra a falta.
 *
 * `no_show` é registro, não descarte: a ausência de uma criança em tratamento
 * é informação clínica, e uma sequência de faltas é justamente o padrão que o
 * prontuário precisa deixar visível.
 */
final class MarkNoShow
{
    public function handle(Appointment $appointment): Appointment
    {
        if ($appointment->status !== AppointmentStatus::Scheduled) {
            throw new RuntimeException(
                'Só um atendimento agendado pode ser marcado como falta.',
            );
        }

        $appointment->update(['status' => AppointmentStatus::NoShow->value]);

        return $appointment;
    }
}
