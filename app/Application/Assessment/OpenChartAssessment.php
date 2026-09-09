<?php

declare(strict_types=1);

namespace App\Application\Assessment;

use App\Domain\Assessment\AssessmentStatus;
use App\Domain\Assessment\EntryMode;
use App\Models\Assessment;
use App\Models\Learner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Abre uma transcrição: uma avaliação já aplicada em papel, que será lançada
 * direto no gráfico de marcos.
 *
 * Diferente de `OpenAssessment` em três pontos, e cada um tem motivo:
 *
 *   1. Não há guarda de "já existe uma aberta". O psicólogo tem anos de papel
 *      arquivado e vai lançar um formulário atrás do outro — inclusive com uma
 *      aplicação em andamento na tela.
 *   2. Nasce `in_progress`. Não existe estado "não iniciada" aqui: a aplicação
 *      aconteceu há um ano, no papel.
 *   3. Os níveis são escolhidos agora, não descobertos pelo caminho. Nível não
 *      escolhido não ganha linha em `assessment_levels` e não aparece no laudo.
 */
final class OpenChartAssessment
{
    public function __construct(private readonly StartLevel $startLevel) {}

    /** @param list<int> $levels níveis presentes no formulário de papel */
    public function handle(
        Learner $learner,
        User $user,
        string $appliedOn,
        array $levels,
        ?string $observations = null,
    ): Assessment {
        $niveis = array_values(array_unique(array_map(intval(...), $levels)));
        sort($niveis);

        if ($niveis === []) {
            throw new InvalidArgumentException('Informe ao menos um nível para lançar.');
        }

        foreach ($niveis as $nivel) {
            if (! in_array($nivel, [1, 2, 3], true)) {
                throw new InvalidArgumentException("Nível inválido: {$nivel}.");
            }
        }

        return DB::transaction(function () use ($learner, $user, $appliedOn, $niveis, $observations) {
            $assessment = Assessment::create([
                'learner_id' => $learner->id,
                'user_id' => $user->id,
                'instrument' => 'vbmapp',
                'entry_mode' => EntryMode::Chart->value,
                'status' => AssessmentStatus::InProgress->value,
                'applied_on' => $appliedOn,
                'started_at' => now(),
                'observations' => $observations,
            ]);

            foreach ($niveis as $nivel) {
                $this->startLevel->handle($assessment, $nivel);
            }

            return $assessment->refresh();
        });
    }
}
