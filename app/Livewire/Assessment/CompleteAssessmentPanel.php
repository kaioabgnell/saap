<?php

declare(strict_types=1);

namespace App\Livewire\Assessment;

use App\Application\Assessment\CancelAssessment;
use App\Application\Assessment\CompleteAssessment;
use App\Domain\Assessment\AssessmentStatus;
use App\Domain\Assessment\LevelStatus;
use App\Models\Assessment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * Conclusão e cancelamento da avaliação.
 *
 * A conclusão é a operação mais consequente do sistema: trava tudo. Por isso
 * o modal explicita a consequência sem eufemismo e mostra o resumo do que
 * está sendo congelado.
 */
class CompleteAssessmentPanel extends Component
{
    #[Locked]
    public int $assessmentId;

    public bool $confirmandoConclusao = false;

    public bool $confirmandoCancelamento = false;

    public string $motivoCancelamento = '';

    public ?string $erro = null;

    public function mount(Assessment $assessment): void
    {
        $this->assessmentId = $assessment->id;
    }

    #[Computed]
    public function assessment(): Assessment
    {
        return Assessment::with('learner', 'levels')->findOrFail($this->assessmentId);
    }

    /** Todos os níveis iniciados completos — e ao menos um iniciado. */
    #[Computed]
    public function podeConcluir(): bool
    {
        $avaliacao = $this->assessment;

        if ($avaliacao->isLocked() || $avaliacao->status === AssessmentStatus::Cancelled) {
            return false;
        }

        if ($avaliacao->levels->isEmpty()) {
            return false;
        }

        return $avaliacao->levels->every(
            fn ($n) => $n->status === LevelStatus::Completed && $n->isComplete(),
        );
    }

    #[Computed]
    public function pontuacaoTotal(): float
    {
        return round((float) $this->assessment->levels->sum('score_total'), 1);
    }

    public function confirmarConclusao(): void
    {
        $this->erro = null;
        $this->confirmandoConclusao = true;
    }

    public function concluir(): void
    {
        $this->authorize('update', $this->assessment);

        try {
            app(CompleteAssessment::class)->handle($this->assessment);
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();
            $this->confirmandoConclusao = false;

            return;
        }

        $this->redirectRoute('avaliacoes.relatorio', $this->assessmentId, navigate: true);
    }

    public function confirmarCancelamento(): void
    {
        $this->erro = null;
        $this->confirmandoCancelamento = true;
    }

    public function cancelar(): void
    {
        $this->authorize('update', $this->assessment);

        try {
            app(CancelAssessment::class)->handle($this->assessment, $this->motivoCancelamento);
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        $this->confirmandoCancelamento = false;
        unset($this->assessment, $this->podeConcluir, $this->pontuacaoTotal);

        $this->redirectRoute('avaliacoes.show', $this->assessmentId, navigate: true);
    }

    public function render()
    {
        return view('livewire.assessment.complete-assessment-panel');
    }
}
