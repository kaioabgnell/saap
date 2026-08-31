<?php

declare(strict_types=1);

namespace App\Application\Report;

use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Domain\Vbmapp\Progress\ProgressCounter;
use App\Domain\Vbmapp\Report\FormArea;
use App\Domain\Vbmapp\Report\FormItem;
use App\Domain\Vbmapp\Report\FormPayload;
use App\Domain\Vbmapp\Scoring\ResponseType;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use App\Models\Response;
use App\Models\Vbmapp\Item;
use App\Models\Vbmapp\Stimulus;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Monta os dados do formulário impresso a partir do catálogo e das respostas.
 *
 * Fica na camada de aplicação porque orquestra Eloquent, mas devolve um
 * `FormPayload` sem Eloquent nenhum — a view do PDF não sabe o que é um
 * `Response` ou um `Stimulus`, só sabe ler o que este caso de uso já montou.
 */
final class BuildFormPayload
{
    public function handle(
        Assessment $assessment,
        int $level,
        bool $onlyPending = false,
        bool $includeCriteria = false,
        bool $includeExamples = true,
    ): FormPayload {
        $assessmentLevel = AssessmentLevel::where('assessment_id', $assessment->id)
            ->where('level', $level)
            ->first();

        if ($assessmentLevel === null) {
            throw new RuntimeException("O nível {$level} desta avaliação ainda não foi iniciado.");
        }

        $assessment->loadMissing('learner', 'user');

        $respostas = Response::with('entries')
            ->where('assessment_id', $assessment->id)
            ->get()
            ->keyBy('item_id');

        $areas = CatalogCache::level($level)
            ->map(fn ($area) => new FormArea(
                name: $area->name,
                shortName: $area->short_name,
                items: $area->items
                    ->map(fn (Item $item) => $this->montarItem($item, $respostas->get($item->id), $includeExamples))
                    ->when($onlyPending, fn (Collection $items) => $items->reject(fn (FormItem $i) => $i->answered))
                    ->values()
                    ->all(),
            ))
            ->reject(fn (FormArea $area) => $area->items === [])
            ->values()
            ->all();

        return new FormPayload(
            learnerName: $assessment->learner->name,
            ageAtApplication: $assessment->learner->ageAt($assessment->applied_on)->format(),
            appliedOn: $assessment->applied_on->format('d/m/Y'),
            applicatorName: $assessment->user->name,
            clinicName: $assessment->user->clinic_name,
            level: $level,
            progress: app(ProgressCounter::class)->forLevel($assessmentLevel->answered_count, $level),
            areas: $areas,
            onlyPending: $onlyPending,
            includeCriteria: $includeCriteria,
            includeExamples: $includeExamples,
            generatedAt: now()->format('d/m/Y H:i'),
        );
    }

    private function montarItem(Item $item, ?Response $response, bool $includeExamples): FormItem
    {
        $respondido = $response?->isAnswered() ?? false;

        return new FormItem(
            code: $item->code,
            position: $item->position,
            statement: $item->statement,
            criteriaFull: $item->criteria_full,
            criteriaHalf: $item->criteria_half,
            answered: $respondido,
            scoreLabel: $respondido ? $this->rotuloDaPontuacao((float) $response->score) : null,
            exemplaresSummary: ($respondido && $includeExamples) ? $this->resumoExemplares($item, $response) : '',
            checklist: $respondido ? [] : $this->checklistParaPreencher($item),
            notes: $response?->notes,
            observationMinutes: $item->observation_minutes,
        );
    }

    private function rotuloDaPontuacao(float $score): string
    {
        return match (true) {
            $score >= 1.0 => '1',
            $score >= 0.5 => '½',
            default => '0',
        };
    }

    private function resumoExemplares(Item $item, Response $response): string
    {
        $entradas = $response->entries;

        return match ($item->response_type) {
            ResponseType::CounterFree => $entradas
                ->pluck('text_value')
                ->filter(fn (?string $v) => trim((string) $v) !== '')
                ->implode(', '),

            ResponseType::CounterStimuli => $this->resumoStimuli($entradas),

            ResponseType::CounterList => $entradas
                ->map(fn ($e) => $e->list_key ?? $e->text_value)
                ->filter(fn (?string $v) => trim((string) $v) !== '')
                ->implode(', '),

            ResponseType::BinaryCriteria => match ((int) ($entradas->first()?->position ?? -1)) {
                2 => $item->criteria_full,
                1 => $item->criteria_half ?? '',
                0 => 'Não atingiu o critério',
                default => '',
            },

            ResponseType::Matrix => $entradas
                ->filter(fn ($e) => (bool) $e->is_checked)
                ->groupBy('list_key')
                ->map(fn ($porLinha, $linha) => "{$linha} ({$porLinha->pluck('column_key')->implode(', ')})")
                ->implode('; '),
        };
    }

    private function resumoStimuli(Collection $entradas): string
    {
        $ids = $entradas->pluck('stimulus_id')->filter()->all();
        $rotulos = $ids === [] ? collect() : Stimulus::whereIn('id', $ids)->pluck('label', 'id');

        $doAcervo = $entradas
            ->filter(fn ($e) => (bool) $e->is_checked && $e->stimulus_id !== null)
            ->map(fn ($e) => $rotulos->get($e->stimulus_id, '?'));

        $porTexto = $entradas
            ->filter(fn ($e) => $e->stimulus_id === null && trim((string) $e->text_value) !== '')
            ->pluck('text_value');

        return $doAcervo->merge($porTexto)->implode(', ');
    }

    /** @return list<string> */
    private function checklistParaPreencher(Item $item): array
    {
        return match ($item->response_type) {
            ResponseType::CounterStimuli => $item->stimuli->isNotEmpty()
                ? $item->stimuli->pluck('label')->all()
                : range(1, $item->threshold_full),

            ResponseType::CounterList => $item->fixed_list ?? [],

            ResponseType::Matrix => $this->checklistDaMatrix($item),

            ResponseType::BinaryCriteria => array_values(array_filter([
                $item->criteria_full,
                $item->criteria_half,
            ])),

            ResponseType::CounterFree => range(1, $item->threshold_full),
        };
    }

    /** @return list<string> */
    private function checklistDaMatrix(Item $item): array
    {
        $colunas = $item->matrix_columns['columns'] ?? [];
        $linhas = $item->fixed_list ?? [];

        if ($linhas === []) {
            // Sem lista fixa (caso do Tato 11): o psicólogo escolhe os itens
            // na hora, então o impresso só mostra as colunas esperadas.
            return [implode(' / ', $colunas)];
        }

        return array_map(
            static fn (string $linha) => "{$linha}: ".implode(' / ', $colunas),
            $linhas,
        );
    }
}
