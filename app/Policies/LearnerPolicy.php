<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Learner;
use App\Models\User;

/**
 * Um psicólogo só enxerga os próprios aprendizes. Regra única, repetida em
 * toda ação — nunca inferida do contexto da rota.
 */
class LearnerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Learner $learner): bool
    {
        return $learner->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Learner $learner): bool
    {
        return $learner->user_id === $user->id;
    }

    public function delete(User $user, Learner $learner): bool
    {
        return $learner->user_id === $user->id;
    }
}
