<?php

declare(strict_types=1);

namespace App\Application\Assessment;

use App\Domain\Assessment\AssessmentStatus;
use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use App\Models\ReportSnapshot;
use App\Models\Response;
use App\Models\Vbmapp\Area;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Conclui uma transcrição.
 *
 * O passo que só existe aqui é o primeiro: **marco em branco vira 0**. No
 * formulário de papel do VB-MAPP a célula não pintada é zero — a pontuação
 * total é a soma do que foi pintado. Exigir um clique para cada zero seria
 * transcrever uma informação que o papel já deu.
 *
 * Mas zero é pontuação, e pontuação exige ato deliberado. Por isso a
 * conversão acontece exatamente aqui, na conclusão, depois de a tela ter dito
 * quantos marcos ela vai atingir — e em lugar nenhum antes.
 *
 * O resto é delegação: travar, congelar o laudo e enfileirar o PDF continua
 * sendo trabalho do `CompleteAssessment`, em um lugar só.
 */
final class CompleteChartAssessment
{
    public function __construct(
        private readonly RecalculateLevelTotals $totais,
        private readonly CompleteLevel $completeLevel,
        private readonly CompleteAssessment $completeAssessment,
    ) {}

    public function handle(Assessment $assessment): ReportSnapshot
    {
        DB::transaction(function () use ($assessment) {
            $fresco = Assessment::with('levels')->lockForUpdate()->findOrFail($assessment->id);

            if (! $fresco->isChartEntry()) {
                throw new RuntimeException('Esta avaliação não é um lançamento por gráfico.');
            }

            if ($fresco->isLocked()) {
                throw new RuntimeException('Esta avaliação já foi concluída.');
            }

            if ($fresco->status === AssessmentStatus::Cancelled) {
                throw new RuntimeException('Esta avaliação foi cancelada e não pode ser concluída.');
            }

            if ($fresco->levels->isEmpty()) {
                throw new RuntimeException('Nenhum nível foi lançado nesta avaliação.');
            }

            foreach ($fresco->levels as $nivel) {
                $this->zerarPendentes($fresco, $nivel);
                $this->totais->handle($nivel);
                $this->completeLevel->handle($nivel->fresh());
            }
        });

        // Fora da transação acima, e de propósito: o `CompleteAssessment`
        // enfileira o PDF depois de commitar o snapshot. Aninhar as duas
        // faria o job ser despachado antes do commit — a mesma corrida que a
        // F7 já resolveu lá dentro.
        return $this->completeAssessment->handle($assessment->refresh());
    }

    /** Registra 0 em todo marco do nível que ainda não tenha resposta. */
    private function zerarPendentes(Assessment $assessment, AssessmentLevel $nivel): void
    {
        $todos = CatalogCache::level($nivel->level)
            ->flatMap(fn (Area $area) => $area->items->pluck('id'))
            ->all();

        $jaRespondidos = Response::where('assessment_id', $assessment->id)
            ->whereIn('item_id', $todos)
            ->whereNotNull('answered_at')
            ->pluck('item_id')
            ->all();

        $pendentes = array_values(array_diff($todos, $jaRespondidos));

        if ($pendentes === []) {
            return;
        }

        $agora = now();

        // upsert, não insert: pode existir linha sem `answered_at` — não no
        // fluxo de hoje, mas um insert cru quebraria com a chave única em vez
        // de convergir.
        Response::upsert(
            array_map(fn (int $itemId) => [
                'assessment_id' => $assessment->id,
                'item_id' => $itemId,
                'score' => 0,
                'computed_score' => null,
                'is_overridden' => false,
                'override_reason' => null,
                'answered_at' => $agora,
                'created_at' => $agora,
                'updated_at' => $agora,
            ], $pendentes),
            ['assessment_id', 'item_id'],
            ['score', 'computed_score', 'is_overridden', 'override_reason', 'answered_at', 'updated_at'],
        );
    }
}
