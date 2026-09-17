<?php

declare(strict_types=1);

namespace App\Application\Schedule;

use App\Domain\Schedule\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A criança chegou: `scheduled` → `in_progress`.
 *
 * Sem janela de horário de propósito. A criança chega adiantada, o
 * atendimento anterior varou — travar o check-in pelo relógio atrapalharia o
 * dia real e não protegeria nada, já que quem faz o check-in é a própria
 * psicóloga, na frente da família.
 */
final class CheckInAppointment
{
    public function handle(Appointment $appointment): Appointment
    {
        return DB::transaction(function () use ($appointment) {
            $fresco = Appointment::lockForUpdate()->findOrFail($appointment->id);

            if ($fresco->status !== AppointmentStatus::Scheduled) {
                throw new RuntimeException(
                    'Este atendimento está como "'.$fresco->status->label().'" e não aceita check-in.',
                );
            }

            $fresco->update([
                'status' => AppointmentStatus::InProgress->value,
                'checked_in_at' => now(),
            ]);

            $appointment->setRawAttributes($fresco->getAttributes(), sync: true);

            return $appointment;
        });
    }
}
