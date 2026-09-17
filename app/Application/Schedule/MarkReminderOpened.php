<?php

declare(strict_types=1);

namespace App\Application\Schedule;

use App\Models\Appointment;

/**
 * Carimba que o WhatsApp foi aberto para este agendamento.
 *
 * `opened`, nunca `sent`. O sistema abre a conversa; quem envia é a pessoa, e
 * pode desistir na tela seguinte. Registrar "enviado" seria afirmar um fato
 * que não temos como conhecer — o tipo de afirmação que uma agenda clínica
 * não pode inventar.
 */
final class MarkReminderOpened
{
    public function handle(Appointment $appointment): Appointment
    {
        $appointment->update(['reminder_opened_at' => now()]);

        return $appointment;
    }
}
