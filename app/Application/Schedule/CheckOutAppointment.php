<?php

declare(strict_types=1);

namespace App\Application\Schedule;

use App\Domain\Schedule\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fecha o atendimento — e, com ele, o registro escrito.
 *
 * `checked_out_at` e `notes_locked_at` são carimbados na mesma transação, e
 * um último salvamento do texto entra junto: sem isso, o que a psicóloga
 * digitou nos segundos anteriores ao clique ficaria de fora do documento que
 * acabou de ser lacrado.
 *
 * Concluir com registro vazio é legítimo — a criança não colaborou, a sessão
 * durou cinco minutos —, mas a tela confirma antes. Aqui não se recusa: quem
 * decide o que é atendimento é quem atendeu.
 */
final class CheckOutAppointment
{
    public function handle(Appointment $appointment, ?string $notesFinais = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $notesFinais) {
            $fresco = Appointment::lockForUpdate()->findOrFail($appointment->id);

            if ($fresco->status !== AppointmentStatus::InProgress) {
                throw new RuntimeException(
                    'Só um atendimento em andamento pode ser concluído.',
                );
            }

            $notes = $notesFinais === null ? $fresco->notes : trim($notesFinais);

            $fresco->update([
                'status' => AppointmentStatus::Completed->value,
                'notes' => $notes === '' ? null : $notes,
                'checked_out_at' => now(),
                'notes_locked_at' => now(),
            ]);

            $appointment->setRawAttributes($fresco->getAttributes(), sync: true);

            return $appointment;
        });
    }
}
