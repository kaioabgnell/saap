<?php

declare(strict_types=1);

namespace App\Application\Schedule;

use App\Domain\Schedule\AppointmentStatus;
use App\Models\Appointment;
use RuntimeException;

/**
 * Cancela preservando a linha e o motivo.
 *
 * Cancelar não é apagar: o horário que se abriu, e a razão, são história do
 * acompanhamento. O motivo é obrigatório justamente porque um cancelamento
 * sem motivo, meses depois, não explica nada a ninguém.
 */
final class CancelAppointment
{
    public function handle(Appointment $appointment, string $motivo): Appointment
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new RuntimeException('Informe o motivo do cancelamento.');
        }

        if ($appointment->status->isTerminal()) {
            throw new RuntimeException(
                'Atendimento '.mb_strtolower($appointment->status->label()).' não pode ser cancelado.',
            );
        }

        $appointment->update([
            'status' => AppointmentStatus::Cancelled->value,
            'cancel_reason' => mb_substr($motivo, 0, 255),
        ]);

        return $appointment;
    }
}
