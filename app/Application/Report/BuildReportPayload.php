<?php

declare(strict_types=1);

namespace App\Application\Report;

use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Domain\Vbmapp\Report\ReportPayload;
use App\Domain\Vbmapp\Scoring\ResponseType;
use App\Models\Assessment;
use App\Models\Response;
use App\Models\Vbmapp\Item;
use App\Models\Vbmapp\Stimulus;
use Illuminate\Support\Collection;

/**
 * Monta o laudo a partir do catálogo e das respostas, no momento da conclusão.
 *
 * Roda UMA vez por avaliação. Depois disso o resultado vira snapshot e nunca
 * mais é recalculado — mudar o catálogo não muda laudo já emitido.
 */
final class BuildReportPayload
{
    public function handle(Assessment $assessment): ReportPayload
    {
        $assessment->loadMissing('learner', 'user', 'levels');

        $respostas = Response::with('entries')
            ->where('assessment_id', $assessment->id)
            ->get()
            ->keyBy('item_id');

        $rotulosDeEstimulo = $this->rotulosDeEstimulo($respostas);

        $niveis = $assessment->levels
            ->sortBy('level')
            ->map(fn ($nivel) => $this->montarNivel($nivel, $respostas, $rotulosDeEstimulo))
            ->values()
            ->all();

        return new ReportPayload([
            'gerado_em' => now()->toIso8601String(),
            'aprendiz' => [
                'nome' => $assessment->learner->name,
                'nascimento' => $assessment->learner->birth_date->toDateString(),
                'idade_na_aplicacao' => $assessment->learner->ageAt($assessment->applied_on)->format(),
                'pai' => $assessment->learner->father_name,
                'mae' => $assessment->learner->mother_name,
            ],
            'aplicador' => [
                'nome' => $assessment->user->name,
                'registro' => $assessment->user->council_id,
                'clinica' => [
                    'nome' => $assessment->user->clinic_name,
                    'telefone' => $assessment->user->clinic_phone,
                    'email' => $assessment->user->clinic_email,
                    'endereco' => $assessment->user->clinic_address,
                    'cidade' => $assessment->user->clinic_city,
                    'uf' => $assessment->user->clinic_state,
                ],
            ],
            'aplicacao' => [
                'data' => $assessment->applied_on->toDateString(),
                'iniciada_em' => $assessment->started_at?->toIso8601String(),
                'concluida_em' => now()->toIso8601String(),
                'instrumento' => $assessment->instrument,
                // De onde vieram os dados. Entra no payload — e portanto no
                // content_hash — porque um laudo transcrito de papel não pode
                // virar "aplicado no sistema" por edição de banco sem que a
                // verificação de integridade acuse.
                'modo' => $assessment->entry_mode->reportMode(),
                'transcrita_em' => $assessment->isChartEntry()
                    ? $assessment->created_at?->toIso8601String()
                    : null,
            ],
            'niveis' => $niveis,
            'total' => [
                'pontuacao' => round(array_sum(array_column($niveis, 'pontuacao')), 1),
                'marcos' => array_sum(array_column($niveis, 'total')),
                'respondidos' => array_sum(array_column($niveis, 'respondidos')),
            ],
            'observacoes' => $assessment->observations,
        ]);
    }

    /** @param Collection<int, Response> $respostas */
    private function rotulosDeEstimulo(Collection $respostas): array
    {
        $ids = $respostas
            ->flatMap(fn (Response $r) => $r->entries->pluck('stimulus_id'))
            ->filter()
            ->unique()
            ->all();

        return $ids === [] ? [] : Stimulus::whereIn('id', $ids)->pluck('label', 'id')->all();
    }

    private function montarNivel($nivel, Collection $respostas, array $rotulos): array
    {
        $areas = CatalogCache::level($nivel->level)
            ->map(fn ($area) => $this->montarArea($area, $respostas, $rotulos))
            ->values()
            ->all();

        return [
            'nivel' => $nivel->level,
            'total' => $nivel->total_count,
            'respondidos' => $nivel->answered_count,
            'pontuacao' => round((float) $nivel->score_total, 1),
            'concluido_em' => $nivel->completed_at?->toIso8601String(),
            'areas' => $areas,
        ];
    }

    private function montarArea($area, Collection $respostas, array $rotulos): array
    {
        $marcos = $area->items
            ->map(fn (Item $item) => $this->montarMarco($item, $respostas->get($item->id), $rotulos))
            ->values()
            ->all();

        return [
            'code' => $area->code,
            'name' => $area->name,
            'short_name' => $area->short_name,
            'pontuacao' => round(array_sum(array_column($marcos, 'score')), 1),
            'marcos' => $marcos,
        ];
    }

    private function montarMarco(Item $item, ?Response $response, array $rotulos): array
    {
        $respondido = $response?->isAnswered() ?? false;

        return [
            'posicao' => $item->position,
            'codigo' => $item->code,
            'enunciado' => $item->statement,
            'criterio_1' => $item->criteria_full,
            'criterio_meio' => $item->criteria_half,
            'tipo' => $item->response_type->value,
            'respondido' => $respondido,
            'score' => $respondido ? (float) $response->score : 0.0,
            // Nulo quando não houve cálculo: numa transcrição a pontuação
            // veio pronta do papel. `(float) null` daria 0,0 — e 0,0 aqui
            // significaria "o sistema calculou zero", que é outra coisa.
            'score_calculado' => $respondido && $response->computed_score !== null
                ? (float) $response->computed_score
                : null,
            'sobrescrito' => (bool) ($response?->is_overridden ?? false),
            'motivo_sobrescrita' => $response?->override_reason,
            'exemplares' => $respondido ? $this->exemplares($item, $response, $rotulos) : [],
            'observacoes' => $response?->notes,
        ];
    }

    /** @return list<string> */
    private function exemplares(Item $item, Response $response, array $rotulos): array
    {
        $entradas = $response->entries;

        return match ($item->response_type) {
            ResponseType::CounterFree => $entradas
                ->pluck('text_value')
                ->filter(fn (?string $v) => trim((string) $v) !== '')
                ->values()->all(),

            ResponseType::CounterStimuli => $entradas
                ->filter(fn ($e) => (bool) $e->is_checked || trim((string) $e->text_value) !== '')
                ->map(fn ($e) => $e->stimulus_id !== null
                    ? ($rotulos[$e->stimulus_id] ?? '?')
                    : trim((string) $e->text_value))
                ->filter()
                ->values()->all(),

            ResponseType::CounterList => $entradas
                ->filter(fn ($e) => (bool) $e->is_checked || trim((string) $e->text_value) !== '')
                ->map(fn ($e) => $e->list_key ?? trim((string) $e->text_value))
                ->filter()
                ->values()->all(),

            ResponseType::BinaryCriteria => match ((int) ($entradas->first()?->position ?? -1)) {
                2 => [$item->criteria_full],
                1 => [$item->criteria_half ?? ''],
                0 => ['Não atingiu o critério'],
                default => [],
            },

            ResponseType::Matrix => $entradas
                ->filter(fn ($e) => (bool) $e->is_checked)
                ->groupBy('list_key')
                ->map(fn ($celulas, $linha) => "{$linha} ({$celulas->pluck('column_key')->implode(', ')})")
                ->values()->all(),
        };
    }
}
