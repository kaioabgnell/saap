<?php

declare(strict_types=1);

namespace App\Application\Report;

use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Domain\Vbmapp\Report\SummaryBriefing;
use App\Models\AiSummary;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use App\Models\Response;
use App\Support\Ia\GeminiSummarizer;
use RuntimeException;
use Throwable;

/**
 * Gera e guarda resumos redigidos por IA — de um nível avulso ou da avaliação
 * inteira, que é o do laudo.
 *
 * Duas travas antes de qualquer chamada externa:
 *
 *   O recorte precisa estar TODO respondido: o nível, quando é um nível; a
 *   avaliação concluída, quando é o laudo. Resumir meia avaliação produziria
 *   um texto que parece completo e não é — e esse texto vai para a família.
 *
 *   A conta é feita aqui, do banco, e não recebida pronta de quem chamou:
 *   é a mesma revalidação no servidor que CompleteAssessment faz.
 *
 * O resumo do laudo NÃO entra no `report_snapshots`. O snapshot é o registro
 * congelado e o `content_hash` existe para detectar adulteração dele; escrever
 * texto novo lá dentro exigiria recalcular o hash, que é exatamente o que ele
 * serve para impedir. O resumo é outro documento, com outra autoria e outra
 * data — e mora em `ai_summaries`, ao lado.
 */
final class GenerateAiSummary
{
    public function __construct(private readonly GeminiSummarizer $ia) {}

    /** O recurso aparece na tela? Sem chave, nem o checkbox existe. */
    public function disponivel(): bool
    {
        return $this->ia->configurado();
    }

    /** O nível está todo respondido? É a condição para o resumo fazer sentido. */
    public function nivelEstaCompleto(int $assessmentId, int $level): bool
    {
        $nivel = AssessmentLevel::where('assessment_id', $assessmentId)
            ->where('level', $level)
            ->first();

        return $nivel !== null && $nivel->isComplete();
    }

    public function handle(Assessment $assessment, int $level): AiSummary
    {
        if (! $this->nivelEstaCompleto($assessment->id, $level)) {
            throw new RuntimeException("O nível {$level} ainda não está todo respondido.");
        }

        $nivel = AssessmentLevel::where('assessment_id', $assessment->id)
            ->where('level', $level)
            ->sole();

        $rascunho = $this->ia->resumir(SummaryBriefing::deNivel(
            level: $level,
            pontuacao: (float) $nivel->score_total,
            totalDeMarcos: $nivel->total_count,
            areas: $this->pontuacaoPorArea($assessment, $level),
        ));

        $assessment->loadMissing('learner');

        return AiSummary::create([
            'assessment_id' => $assessment->id,
            'level' => $level,
            // O nome entra AGORA, dentro de casa. O modelo escreveu o
            // marcador — ver SummaryBriefing para o porquê.
            'body' => str_replace(
                SummaryBriefing::MARCADOR_APRENDIZ,
                $assessment->learner->name,
                $rascunho->body,
            ),
            'model' => $rascunho->model,
            'tokens_used' => $rascunho->tokensUsed,
            'generated_at' => now(),
        ]);
    }

    /**
     * O resumo do laudo, gerado UMA vez e reaproveitado.
     *
     * Reaproveitar não é economia de token: é o documento. A tela e o PDF
     * precisam mostrar o MESMO texto, e um laudo cujo resumo muda a cada
     * visita não é laudo. Regerar é ato explícito — apagar a linha.
     *
     * Devolve null em vez de estourar: o resumo é acessório do laudo, e o
     * laudo não pode deixar de abrir porque o Google não respondeu.
     */
    public function paraAvaliacao(Assessment $assessment): ?AiSummary
    {
        $existente = AiSummary::maisRecente($assessment->id, null);

        if ($existente !== null) {
            return $existente;
        }

        if (! $assessment->isLocked()) {
            return null;
        }

        try {
            $rascunho = $this->ia->resumir(SummaryBriefing::deAvaliacao(
                $this->niveisDaAvaliacao($assessment),
            ));
        } catch (Throwable) {
            // Sem log do erro: a mensagem pode carregar trecho do que foi
            // enviado, e nada de aprendiz vai para log (docs/operacao.md).
            return null;
        }

        $assessment->loadMissing('learner');

        return AiSummary::create([
            'assessment_id' => $assessment->id,
            'level' => null,
            'body' => str_replace(
                SummaryBriefing::MARCADOR_APRENDIZ,
                $assessment->learner->name,
                $rascunho->body,
            ),
            'model' => $rascunho->model,
            'tokens_used' => $rascunho->tokensUsed,
            'generated_at' => now(),
        ]);
    }

    /**
     * Os níveis aplicados, com pontuação por área.
     *
     * @return list<array{nivel: int, pontuacao: float, total: int, areas: list<array{nome: string, pontuacao: float, total: int}>}>
     */
    private function niveisDaAvaliacao(Assessment $assessment): array
    {
        return $assessment->levels
            ->sortBy('level')
            ->map(fn (AssessmentLevel $nivel) => [
                'nivel' => $nivel->level,
                'pontuacao' => round((float) $nivel->score_total, 1),
                'total' => $nivel->total_count,
                'areas' => $this->pontuacaoPorArea($assessment, $nivel->level),
            ])
            ->values()
            ->all();
    }

    /**
     * Pontuação somada por área, na ordem do catálogo.
     *
     * @return list<array{nome: string, pontuacao: float, total: int}>
     */
    private function pontuacaoPorArea(Assessment $assessment, int $level): array
    {
        $respostas = Response::where('assessment_id', $assessment->id)
            ->get()
            ->keyBy('item_id');

        return CatalogCache::level($level)
            ->map(fn ($area) => [
                'nome' => $area->name,
                'pontuacao' => round(
                    $area->items->sum(fn ($item) => (float) ($respostas->get($item->id)?->score ?? 0)),
                    1,
                ),
                'total' => $area->items->count(),
            ])
            ->values()
            ->all();
    }
}
