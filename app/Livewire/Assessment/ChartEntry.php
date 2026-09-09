<?php

declare(strict_types=1);

namespace App\Livewire\Assessment;

use App\Application\Assessment\BuildChartGrid;
use App\Application\Assessment\CompleteChartAssessment;
use App\Application\Assessment\SaveChartScore;
use App\Domain\Vbmapp\Chart\ChartGrid;
use App\Models\Assessment;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * A tela de lançamento retroativo: o gráfico de marcos vazio, clicável.
 *
 * Aqui não existe cartão de marco, exemplar nem contagem — o formulário de
 * papel já foi aplicado e a única informação que sobrou dele é a pontuação.
 * Por isso a tela é o gráfico e nada mais.
 *
 * Grava a cada clique, como a tela do nível. A diferença é o que a conclusão
 * faz: converte em zero todo marco não tocado, porque no papel a célula em
 * branco é zero (ver `CompleteChartAssessment`).
 */
class ChartEntry extends Component
{
    #[Locked]
    public int $assessmentId;

    public ?string $salvoEm = null;

    public ?string $erro = null;

    public bool $confirmandoConclusao = false;

    public function mount(Assessment $assessment): ?RedirectResponse
    {
        $this->authorize('view', $assessment);

        abort_unless(
            $assessment->isChartEntry(),
            403,
            'Esta avaliação é conduzida marco a marco; use a tela do nível.',
        );

        $this->assessmentId = $assessment->id;

        // Concluída, esta tela não tem mais nada a oferecer: o gráfico
        // definitivo é o do laudo.
        if ($assessment->isLocked()) {
            return redirect()->route('avaliacoes.relatorio', $assessment);
        }

        return null;
    }

    #[Computed]
    public function assessment(): Assessment
    {
        return Assessment::with('learner', 'user', 'levels')->findOrFail($this->assessmentId);
    }

    /** @return array<int, ChartGrid> uma grade por nível lançado */
    #[Computed]
    public function grades(): array
    {
        return app(BuildChartGrid::class)->handle($this->assessment);
    }

    #[Computed]
    public function pendentes(): int
    {
        return array_sum(array_map(fn (ChartGrid $g) => $g->pendingCount(), $this->grades));
    }

    #[Computed]
    public function pontuacaoTotal(): float
    {
        return round(array_sum(array_map(fn (ChartGrid $g) => $g->scoreTotal(), $this->grades)), 1);
    }

    /** Clique numa das metades da célula. A regra de transição é do domínio. */
    public function marcar(int $itemId, string $metade): void
    {
        $celula = $this->celulaDe($itemId);

        if ($celula === null) {
            return;
        }

        $this->gravar($itemId, ChartGrid::nextScore(
            $celula['score'],
            $metade,
            $celula['has_half_point'],
        ));
    }

    /** Valor direto: teclado (1, 5, 0, Backspace) e popover de toque. */
    public function definir(int $itemId, int|float|null $score): void
    {
        $this->gravar($itemId, $score === null ? null : (float) $score);
    }

    public function limparNivel(int $level): void
    {
        $this->authorize('transcribe', $this->assessment);

        $grade = $this->grades[$level] ?? null;

        if ($grade === null) {
            return;
        }

        $this->erro = null;

        foreach ($grade->cells() as $celula) {
            if ($celula['score'] !== null) {
                app(SaveChartScore::class)->handle($this->assessmentId, $celula['item_id'], null);
            }
        }

        $this->limparEstadoDerivado();
    }

    public function confirmarConclusao(): void
    {
        $this->erro = null;
        $this->confirmandoConclusao = true;
    }

    public function concluir()
    {
        $this->authorize('transcribe', $this->assessment);

        try {
            app(CompleteChartAssessment::class)->handle($this->assessment);
        } catch (RuntimeException $e) {
            $this->confirmandoConclusao = false;
            $this->erro = $e->getMessage();
            $this->limparEstadoDerivado();

            return null;
        }

        return redirect()->route('avaliacoes.relatorio', $this->assessmentId);
    }

    private function gravar(int $itemId, ?float $score): void
    {
        // Camada da policy. A do caso de uso continua existindo: é a que um
        // chamador novo não consegue esquecer.
        $this->authorize('transcribe', $this->assessment);

        $this->erro = null;

        try {
            $resultado = app(SaveChartScore::class)->handle($this->assessmentId, $itemId, $score);
            $this->salvoEm = $resultado->savedAt;
        } catch (RuntimeException|InvalidArgumentException $e) {
            $this->erro = $e->getMessage();
        }

        $this->limparEstadoDerivado();
    }

    /** @return array<string, mixed>|null */
    private function celulaDe(int $itemId): ?array
    {
        foreach ($this->grades as $grade) {
            foreach ($grade->cells() as $celula) {
                if ($celula['item_id'] === $itemId) {
                    return $celula;
                }
            }
        }

        return null;
    }

    /**
     * As grades derivam das respostas: depois de gravar, a versão em memória
     * está velha. Sem isto a célula clicada só mudaria de cor no render
     * seguinte — o clássico "o clique não pegou".
     */
    private function limparEstadoDerivado(): void
    {
        unset($this->assessment, $this->grades, $this->pendentes, $this->pontuacaoTotal);
    }

    public function render()
    {
        return view('livewire.assessment.chart-entry');
    }
}
