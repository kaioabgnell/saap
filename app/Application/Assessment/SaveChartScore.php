<?php

declare(strict_types=1);

namespace App\Application\Assessment;

use App\Domain\Vbmapp\Chart\ChartGrid;
use App\Domain\Vbmapp\Progress\ProgressCounter;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use App\Models\Response;
use App\Models\Vbmapp\Item;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * A única porta de escrita da transcrição: grava a pontuação que o psicólogo
 * marcou direto na célula do gráfico.
 *
 * Não chama `EntryTally` nem `ScoreCalculator`, e isso é o ponto — não há
 * exemplares para contar. A pontuação veio pronta do formulário de papel; o
 * que este caso de uso faz é registrá-la dizendo a verdade sobre a origem:
 *
 *     computed_score = null   o sistema não calculou nada
 *     is_overridden  = false  ninguém discordou de cálculo nenhum
 *     sem entries             o detalhe ficou no papel
 *
 * O caminho curto seria mandar `explicitScore` pelo `SaveResponse`. Ele
 * funcionaria — e carimbaria "pontuação ajustada pelo aplicador" em quase
 * todos os marcos do laudo, afirmando uma conduta clínica que nunca houve.
 */
final class SaveChartScore
{
    public function __construct(
        private readonly RecalculateLevelTotals $totais,
        private readonly ProgressCounter $progress,
    ) {}

    /** @param float|null $score 0, 0.5, 1 — ou null para voltar a pendente */
    public function handle(int $assessmentId, int $itemId, ?float $score): SaveChartScoreResult
    {
        return DB::transaction(function () use ($assessmentId, $itemId, $score) {
            $assessment = Assessment::lockForUpdate()->findOrFail($assessmentId);

            if ($assessment->isLocked()) {
                throw new RuntimeException('Esta avaliação foi concluída e não pode ser alterada.');
            }

            if (! $assessment->isChartEntry()) {
                throw new RuntimeException('Esta avaliação é conduzida marco a marco; use a tela do nível.');
            }

            $item = Item::findOrFail($itemId);

            if ($score !== null && ! in_array($score, [0.0, 0.5, 1.0], true)) {
                throw new InvalidArgumentException('Pontuação de marco só pode ser 0, ½ ou 1.');
            }

            // O catálogo mandando na tela: é a única regra do manual que
            // sobrevive à transcrição. Ver `threshold_half` anulável.
            if ($score === 0.5 && ! $item->hasHalfPoint()) {
                throw new InvalidArgumentException("O marco {$item->code} não admite meio ponto.");
            }

            $nivel = AssessmentLevel::where('assessment_id', $assessment->id)
                ->where('level', $item->level)
                ->first();

            if ($nivel === null) {
                throw new RuntimeException("O nível {$item->level} não faz parte deste lançamento.");
            }

            if ($score === null) {
                Response::where('assessment_id', $assessment->id)
                    ->where('item_id', $item->id)
                    ->delete();
            } else {
                Response::updateOrCreate(
                    ['assessment_id' => $assessment->id, 'item_id' => $item->id],
                    [
                        'score' => $score,
                        'computed_score' => null,
                        'is_overridden' => false,
                        'override_reason' => null,
                        'answered_at' => now(),
                    ],
                );
            }

            $this->totais->handle($nivel);
            $nivel->refresh();

            return new SaveChartScoreResult(
                itemId: $item->id,
                score: $score,
                state: ChartGrid::estadoDe($score),
                levelProgress: $this->progress->forLevel($nivel->answered_count, $nivel->level),
                levelScore: (float) $nivel->score_total,
                assessmentProgress: $this->progress->forAssessment(
                    Response::where('assessment_id', $assessment->id)->whereNotNull('answered_at')->count(),
                    $assessment->levels()->pluck('level')->map(intval(...))->all(),
                ),
                savedAt: now()->format('H:i'),
            );
        });
    }
}
