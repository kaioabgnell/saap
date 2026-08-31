<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;

/**
 * Mesma regra de posse do LearnerPolicy, mais a trava de imutabilidade:
 * update() nega quando locked_at não é nulo. É aqui — não em `if` espalhado
 * pelos componentes — que a conclusão de uma avaliação vira definitiva.
 */
class AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Assessment $assessment): bool
    {
        return $assessment->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Assessment $assessment): bool
    {
        return $assessment->user_id === $user->id && ! $assessment->isLocked();
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        return $assessment->user_id === $user->id && ! $assessment->isLocked();
    }
}
