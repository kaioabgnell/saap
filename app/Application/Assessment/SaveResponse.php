<?php

declare(strict_types=1);

namespace App\Application\Assessment;

use App\Domain\Assessment\LevelStatus;
use App\Domain\Vbmapp\Progress\Progress;
use App\Domain\Vbmapp\Progress\ProgressCounter;
use App\Domain\Vbmapp\Scoring\EntryTally;
use App\Domain\Vbmapp\Scoring\MatrixStrategy;
use App\Domain\Vbmapp\Scoring\Score;
use App\Domain\Vbmapp\Scoring\ScoreCalculator;
use App\Domain\Vbmapp\Scoring\ScoringMode;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use App\Models\Response;
use App\Models\Vbmapp\Item;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A única porta de escrita de resposta. Web e API chamam este caso de uso —
 * nenhuma regra de pontuação existe em dois lugares.
 *
 * Gravar e pontuar acontecem na mesma transação, mas são responsabilidades
 * separadas: aqui orquestramos, o EntryTally conta e o ScoreCalculator decide.
 */
final class SaveResponse
{
    public function __construct(
        private readonly EntryTally $tally,
        private readonly ScoreCalculator $calculator,
        private readonly ProgressCounter $progress,
    ) {}

    public function handle(SaveResponseCommand $command): SaveResponseResult
    {
        return DB::transaction(function () use ($command) {
            $assessment = Assessment::lockForUpdate()->findOrFail($command->assessmentId);

            if ($assessment->isLocked()) {
                throw new RuntimeException('Esta avaliação foi concluída e não pode ser alterada.');
            }

            $item = Item::findOrFail($command->itemId);

            $nivel = AssessmentLevel::where('assessment_id', $assessment->id)
                ->where('level', $item->level)
                ->first();

            if ($nivel === null) {
                throw new RuntimeException("O nível {$item->level} desta avaliação ainda não foi iniciado.");
            }

            $entradas = $this->limpar($command->entries);
            $acertos = $this->tally->count(
                $item->response_type,
                $entradas,
                MatrixStrategy::fromColumns($item->matrix_columns),
            );

            $computado = $this->calculator->score(
                $acertos,
                $item->threshold_full,
                $item->threshold_half,
            );

            [$valendo, $respondido, $pedeConfirmacao] = $this->decidir(
                $item, $command, $entradas, $acertos, $computado,
            );

            $response = Response::updateOrCreate(
                ['assessment_id' => $assessment->id, 'item_id' => $item->id],
                [
                    'score' => $valendo->toFloat(),
                    'computed_score' => $computado->toFloat(),
                    'is_overridden' => $valendo !== $computado,
                    'override_reason' => $valendo !== $computado ? $command->overrideReason : null,
                    'notes' => $command->notes,
                    'answered_at' => $respondido ? now() : null,
                ],
            );

            $this->sincronizarEntradas($response, $entradas);
            $this->recalcularNivel($nivel);

            return new SaveResponseResult(
                score: $valendo,
                computedScore: $computado,
                isOverridden: $valendo !== $computado,
                isAnswered: $respondido,
                needsConfirmation: $pedeConfirmacao,
                tally: $acertos,
                areaProgress: $this->progressoDaArea($assessment, $item),
                levelProgress: $this->progress->forLevel($nivel->fresh()->answered_count, $item->level),
                assessmentProgress: $this->progressoDaAvaliacao($assessment),
                savedAt: now()->format('H:i'),
            );
        });
    }

    /**
     * Decide o que vale, se conta como respondido e se ainda falta confirmar.
     *
     * @return array{Score, bool, bool}
     */
    private function decidir(
        Item $item,
        SaveResponseCommand $command,
        array $entradas,
        int $acertos,
        Score $computado,
    ): array {
        // Decisão explícita do psicólogo sempre vence — é ela que permite
        // registrar zero deliberado, que é diferente de deixar em branco.
        if ($command->explicitScore !== null) {
            return [Score::fromFloat($command->explicitScore), true, false];
        }

        // Marco 'assisted': os dois critérios pedem a mesma contagem e só a
        // qualidade os separa (Mando 4-M — 5 mandos diferentes contra 5 sempre
        // com a mesma palavra). A régua não distingue, então o marco fica
        // salvo mas pendente até a confirmação. Abaixo do limiar de meio ponto
        // não há ambiguidade: é zero, e conta como respondido.
        if ($item->scoring_mode === ScoringMode::Assisted && $this->ambiguo($item, $acertos)) {
            return [$computado, false, true];
        }

        // Sem entrada nenhuma o marco volta a pendente: formulário vazio é
        // indistinguível de formulário nunca tocado.
        return [$computado, $entradas !== [], false];
    }

    private function ambiguo(Item $item, int $acertos): bool
    {
        $piso = $item->threshold_half ?? $item->threshold_full;

        return $acertos >= $piso;
    }

    /**
     * Descarta entradas vazias: caixa em branco não é exemplar registrado.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function limpar(array $entries): array
    {
        $limpas = array_filter($entries, static function (array $e): bool {
            $texto = trim((string) ($e['text_value'] ?? ''));

            return $texto !== ''
                || (bool) ($e['is_checked'] ?? false)
                || ($e['stimulus_id'] ?? null) !== null
                // Célula de matrix, mesmo desmarcada: precisa sobreviver para
                // linhasCompletas() saber que a linha tem uma coluna vazia.
                // Sem isso, uma linha com só 1 de 3 exemplares marcados perde
                // as 2 entradas "false" e parece completa com 1 célula só.
                || ($e['column_key'] ?? null) !== null;
        });

        return array_values($limpas);
    }

    /** @param list<array<string, mixed>> $entradas */
    private function sincronizarEntradas(Response $response, array $entradas): void
    {
        // Substituição completa em vez de diferencial: o conjunto é pequeno
        // (no máximo algumas dezenas) e assim não há estado órfão possível.
        $response->entries()->delete();

        if ($entradas === []) {
            return;
        }

        $agora = now();

        $response->entries()->insert(array_map(static fn (array $e) => [
            'response_id' => $response->id,
            'position' => (int) ($e['position'] ?? 0),
            'stimulus_id' => $e['stimulus_id'] ?? null,
            'list_key' => $e['list_key'] ?? null,
            'column_key' => $e['column_key'] ?? null,
            'text_value' => isset($e['text_value']) ? trim((string) $e['text_value']) : null,
            'is_checked' => (bool) ($e['is_checked'] ?? false),
            'created_at' => $agora,
            'updated_at' => $agora,
        ], $entradas));
    }

    /**
     * Recalcula os contadores do nível na MESMA transação da gravação.
     * Nunca por job: o progresso é lido no render seguinte.
     */
    private function recalcularNivel(AssessmentLevel $nivel): void
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

    private function progressoDaArea(Assessment $assessment, Item $item): Progress
    {
        $respondidos = Response::query()
            ->join('vbmapp_items', 'vbmapp_items.id', '=', 'responses.item_id')
            ->where('responses.assessment_id', $assessment->id)
            ->where('vbmapp_items.level', $item->level)
            ->where('vbmapp_items.area_id', $item->area_id)
            ->whereNotNull('responses.answered_at')
            ->count();

        return $this->progress->forArea($respondidos);
    }

    private function progressoDaAvaliacao(Assessment $assessment): Progress
    {
        $respondidos = Response::where('assessment_id', $assessment->id)
            ->whereNotNull('answered_at')
            ->count();

        return $this->progress->forAssessment(
            $respondidos,
            $assessment->levels()->pluck('level')->map(intval(...))->all(),
        );
    }
}
