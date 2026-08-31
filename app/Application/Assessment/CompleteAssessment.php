<?php

declare(strict_types=1);

namespace App\Application\Assessment;

use App\Application\Report\BuildReportPayload;
use App\Domain\Assessment\AssessmentStatus;
use App\Domain\Assessment\LevelStatus;
use App\Jobs\GenerateReportPdf;
use App\Models\Assessment;
use App\Models\ReportSnapshot;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Conclui a avaliação: trava, congela o laudo e enfileira o PDF.
 *
 * É a operação mais consequente do sistema — depois dela nenhuma resposta
 * muda. Por isso revalida a condição no servidor: um botão habilitado por
 * tela desatualizada não pode travar uma avaliação incompleta.
 */
final class CompleteAssessment
{
    public function __construct(private readonly BuildReportPayload $builder) {}

    public function handle(Assessment $assessment): ReportSnapshot
    {
        $snapshot = DB::transaction(function () use ($assessment) {
            $fresco = Assessment::with('levels', 'learner', 'user')
                ->lockForUpdate()
                ->findOrFail($assessment->id);

            if ($fresco->isLocked()) {
                throw new RuntimeException('Esta avaliação já foi concluída.');
            }

            if ($fresco->status === AssessmentStatus::Cancelled) {
                throw new RuntimeException('Esta avaliação foi cancelada e não pode ser concluída.');
            }

            $this->revalidar($fresco);

            $payload = $this->builder->handle($fresco);

            $fresco->update([
                'status' => AssessmentStatus::Completed->value,
                'completed_at' => now(),
                'locked_at' => now(),
            ]);

            $snapshot = ReportSnapshot::updateOrCreate(
                ['assessment_id' => $fresco->id],
                [
                    'payload' => $payload->toArray(),
                    'content_hash' => $payload->contentHash(),
                    'generated_at' => now(),
                ],
            );

            return $snapshot;
        });

        // O PDF é enfileirado FORA da transação. Na fila 'sync' (testes) o job
        // rodaria dentro dela e não enxergaria o snapshot ainda não commitado;
        // na 'database', o worker poderia pegar o job antes do commit e falhar
        // com "snapshot não encontrado". É a mesma corrida nos dois casos.
        GenerateReportPdf::dispatch($snapshot->id);

        return $snapshot->refresh();
    }

    /**
     * Todos os níveis INICIADOS precisam estar completos. Níveis nunca
     * iniciados não bloqueiam — um aprendiz avaliado só no nível 2 conclui
     * com 60 de 60.
     */
    private function revalidar(Assessment $assessment): void
    {
        if ($assessment->levels->isEmpty()) {
            throw new RuntimeException('Nenhum nível foi iniciado nesta avaliação.');
        }

        $incompletos = $assessment->levels
            ->filter(fn ($n) => $n->status !== LevelStatus::Completed || ! $n->isComplete());

        if ($incompletos->isNotEmpty()) {
            $lista = $incompletos
                ->map(fn ($n) => "nível {$n->level} ({$n->answered_count} de {$n->total_count})")
                ->implode(', ');

            throw new RuntimeException("Ainda há nível incompleto: {$lista}.");
        }
    }
}
