<?php

declare(strict_types=1);

namespace App\Application\Assessment;

use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Domain\Vbmapp\Chart\ChartGrid;
use App\Models\Assessment;
use App\Models\Response;
use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\Item;

/**
 * Monta as grades editáveis de uma transcrição — uma por nível lançado.
 *
 * Lê o banco e o catálogo; a regra de montagem fica no `ChartGrid`, que é PHP
 * puro. Uma consulta de respostas para a avaliação inteira, não uma por
 * nível: são 170 linhas no pior caso.
 */
final class BuildChartGrid
{
    /** @return array<int, ChartGrid> indexado pelo número do nível */
    public function handle(Assessment $assessment): array
    {
        $pontuacoes = $this->pontuacoes($assessment);

        $grades = [];

        foreach ($assessment->startedLevels() as $nivel) {
            $grades[$nivel] = ChartGrid::build($nivel, $this->areas($nivel), $pontuacoes);
        }

        return $grades;
    }

    /**
     * Só as respostas de fato respondidas viram pontuação na grade. Uma
     * `Response` sem `answered_at` é rascunho do fluxo guiado e não existe na
     * transcrição — mas se existisse, apareceria aqui como marcada.
     *
     * @return array<int, float>
     */
    private function pontuacoes(Assessment $assessment): array
    {
        return Response::where('assessment_id', $assessment->id)
            ->whereNotNull('answered_at')
            ->pluck('score', 'item_id')
            ->map(fn ($score) => (float) $score)
            ->all();
    }

    /** O catálogo do nível na forma de arrays que o domínio aceita. */
    private function areas(int $level): array
    {
        return CatalogCache::level($level)
            ->map(fn (Area $area) => [
                'code' => $area->code,
                'name' => $area->name,
                'short_name' => $area->short_name,
                'items' => $area->items->map(fn (Item $item) => [
                    'id' => $item->id,
                    'position' => $item->position,
                    'code' => $item->code,
                    'statement' => $item->statement,
                    'has_half_point' => $item->hasHalfPoint(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
