<?php

declare(strict_types=1);

namespace App\Application\Schedule;

use App\Models\Appointment;
use RuntimeException;

/**
 * Grava o registro escrito da sessão — o resumo livre do que foi conversado.
 *
 * Chamado a cada pausa na digitação, como o ItemCard: a psicóloga está com a
 * criança e não vai redigitar o que se perdeu.
 *
 * Recusa depois de `notes_locked_at`. Essa recusa é a espinha do módulo: o
 * que está fechado só muda por aditamento datado.
 */
final class SaveSessionNotes
{
    public function handle(Appointment $appointment, ?string $notes): string
    {
        if ($appointment->registroFechado()) {
            throw new RuntimeException(
                'Este atendimento foi concluído. Para corrigir, use um aditamento.',
            );
        }

        $notes = trim((string) $notes);

        $appointment->update(['notes' => $notes === '' ? null : $notes]);

        return now()->format('H:i');
    }
}
