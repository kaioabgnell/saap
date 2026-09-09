<?php

declare(strict_types=1);

namespace App\Application\Assessment;

use App\Domain\Assessment\LevelStatus;
use App\Models\AssessmentLevel;
use App\Models\Response;

/**
 * Recalcula os contadores desnormalizados de um nível: quantos marcos foram
 * respondidos e quanto somam.
 *
 * Roda sempre na MESMA transação da gravação que a disparou, nunca por job —
 * o progresso é lido no render seguinte, e um contador atrasado por um
 * segundo apareceria como resposta perdida.
 *
 * Vive aqui, e não dentro de um caso de uso, porque dois gravam pontuação: o
 * `SaveResponse` do fluxo guiado e o `SaveChartScore` da transcrição. Duas
 * cópias desta contagem divergiriam na primeira mudança.
 */
final class RecalculateLevelTotals
{
    public function handle(AssessmentLevel $nivel): void
    {
        $agregado = Response::query()
            ->join('vbmapp_items', 'vbmapp_items.id', '=', 'responses.item_id')
            ->where('responses.assessment_id', $nivel->assessment_id)
            ->where('vbmapp_items.level', $nivel->level)
            ->whereNotNull('responses.answered_at')
            ->selectRaw('COUNT(*) AS respondidos, COALESCE(SUM(responses.score), 0) AS pontos')
            ->first();

        $respondidos = (int) $agregado->respondidos;

        $nivel->update([
            'answered_count' => $respondidos,
            'score_total' => (float) $agregado->pontos,
            // Um nível concluído volta a "em andamento" se uma resposta for
            // desfeita. A conclusão da avaliação (F7) é que trava de vez.
            'status' => $respondidos >= $nivel->total_count
                ? $nivel->status
                : LevelStatus::InProgress->value,
        ]);
    }
}
