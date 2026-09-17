<?php

declare(strict_types=1);

namespace App\Application\Schedule;

use App\Models\Appointment;
use App\Models\AppointmentAddendum;
use RuntimeException;

/**
 * Acrescenta ao registro já fechado.
 *
 * É a única forma de corrigir depois do check-out, e é assim de propósito: o
 * texto original nunca é reescrito. Um prontuário que pode ser editado meses
 * depois, sem deixar rastro, não serve como documento — é a mesma razão pela
 * qual o laudo desta plataforma é congelado em snapshot.
 */
final class AddSessionAddendum
{
    public function handle(Appointment $appointment, string $texto): AppointmentAddendum
    {
        $texto = trim($texto);

        if ($texto === '') {
            throw new RuntimeException('Escreva o aditamento antes de acrescentá-lo.');
        }

        if (! $appointment->registroFechado()) {
            throw new RuntimeException(
                'Este atendimento ainda está aberto: corrija o próprio registro.',
            );
        }

        return AppointmentAddendum::create([
            'appointment_id' => $appointment->id,
            'body' => $texto,
        ]);
    }
}
