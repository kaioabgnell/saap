<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

/**
 * Mesma regra de posse do LearnerPolicy, mais a trava dos estados terminais.
 *
 * É aqui, e não em `if` espalhado pelos componentes, que "atendimento
 * concluído não se altera" vira definitivo — do mesmo jeito que o
 * AssessmentPolicy trata o `locked_at` da avaliação.
 */
class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $appointment->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $appointment->user_id === $user->id && ! $appointment->status->isTerminal();
    }

    /**
     * Acrescentar ao registro fechado. Não passa por `update` de propósito:
     * o aditamento é justamente o que continua possível depois que o
     * atendimento fecha — é a saída que o fechamento deixa aberta.
     */
    public function addendum(User $user, Appointment $appointment): bool
    {
        return $appointment->user_id === $user->id && $appointment->registroFechado();
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $appointment->user_id === $user->id && $appointment->podeSerApagado();
    }
}
