<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;

/**
 * Mesma regra de posse do LearnerPolicy, mais duas travas:
 *
 *   imutabilidade — update() nega quando locked_at não é nulo. É aqui, não em
 *   `if` espalhado pelos componentes, que a conclusão vira definitiva.
 *
 *   modo de lançamento — cada fluxo só alcança as avaliações do seu modo. Sem
 *   isso a tela do nível abriria uma transcrição e o primeiro toque a zeraria,
 *   sem erro nenhum: do ponto de vista do fluxo guiado, gravar um marco sem
 *   exemplar nenhum é zero legítimo.
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

    /** Abrir a tela do nível. Leitura — vale também depois de concluída. */
    public function viewLevelBoard(User $user, Assessment $assessment): bool
    {
        return $this->view($user, $assessment) && ! $assessment->isChartEntry();
    }

    /** Gravar marco a marco: o ItemCard e o PUT da API. */
    public function applyGuided(User $user, Assessment $assessment): bool
    {
        return $this->update($user, $assessment) && ! $assessment->isChartEntry();
    }

    /** Abrir e gravar a tela de lançamento por gráfico. */
    public function transcribe(User $user, Assessment $assessment): bool
    {
        return $this->update($user, $assessment) && $assessment->isChartEntry();
    }
}
