<?php

declare(strict_types=1);

namespace App\Livewire\Assessment;

use App\Application\Assessment\CompleteLevel;
use App\Application\Assessment\StartLevel;
use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Domain\Vbmapp\Progress\Progress;
use App\Domain\Vbmapp\Progress\ProgressCounter;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use App\Models\Response;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Component;
use RuntimeException;

/**
 * A tela de aplicação de um nível.
 *
 * Renderiza os marcos de UMA área por vez. Isso não é só navegação: é o que
 * mantém a tela leve no iPad, porque só ~5 ItemCards ficam montados em vez de
 * 45. "Todas as áreas" existe, mas é escolha explícita do psicólogo.
 */
class LevelBoard extends Component
{
    #[Locked]
    public int $assessmentId;

    #[Locked]
    public int $level;

    public ?string $areaCode = null;

    /** Persistido na sessão: o filtro sobrevive à navegação entre áreas. */
    #[Session(key: 'saap.filtro-pendentes')]
    public bool $somentePendentes = false;

    public ?string $salvoEm = null;

    public bool $confirmandoConclusao = false;

    public ?string $erro = null;

    public function mount(Assessment $assessment, int $level): void
    {
        // 'viewLevelBoard' e não 'view': uma avaliação transcrita do papel
        // não tem tela de nível, e abri-la aqui seria o começo de apagá-la.
        $this->authorize('viewLevelBoard', $assessment);

        $this->assessmentId = $assessment->id;
        $this->level = $level;

        // Entrar na tela do nível é iniciá-lo. Não há passo intermediário —
        // níveis são independentes e começar pelo 2 ou 3 é caminho normal.
        app(StartLevel::class)->handle($assessment, $level);

        $this->areaCode ??= $this->primeiraAreaIncompleta();
    }

    #[Computed]
    public function assessment(): Assessment
    {
        return Assessment::with('learner', 'user', 'levels')->findOrFail($this->assessmentId);
    }

    #[Computed]
    public function assessmentLevel(): AssessmentLevel
    {
        return AssessmentLevel::where('assessment_id', $this->assessmentId)
            ->where('level', $this->level)
            ->sole();
    }

    /** Áreas do nível com os marcos já carregados — vem do cache do catálogo. */
    #[Computed]
    public function areas(): Collection
    {
        return CatalogCache::level($this->level);
    }

    /**
     * Respostas da avaliação, indexadas por marco — **sem as entradas**.
     *
     * É tudo de que o progresso e o filtro de pendentes precisam: `isAnswered()`
     * olha `answered_at`, não as entradas. Carregá-las aqui traria milhares de
     * linhas (um marco matrix sozinho tem dezenas) para renderizar os ~5
     * cartões da área visível.
     */
    #[Computed]
    public function respostas(): Collection
    {
        return Response::where('assessment_id', $this->assessmentId)
            ->get()
            ->keyBy('item_id');
    }

    /** As entradas só dos marcos na tela — são os únicos cartões que as hidratam. */
    #[Computed]
    public function respostasVisiveis(): Collection
    {
        return Response::with('entries')
            ->where('assessment_id', $this->assessmentId)
            ->whereIn('item_id', $this->marcosVisiveis->pluck('id'))
            ->get()
            ->keyBy('item_id');
    }

    /** Quantos marcos de cada área já foram respondidos. */
    #[Computed]
    public function respondidasPorArea(): array
    {
        $porArea = [];

        foreach ($this->areas as $area) {
            $porArea[$area->code] = $area->items
                ->filter(fn ($item) => $this->respostas->get($item->id)?->isAnswered() ?? false)
                ->count();
        }

        return $porArea;
    }

    #[Computed]
    public function levelProgress(): Progress
    {
        return app(ProgressCounter::class)->forLevel(
            array_sum($this->respondidasPorArea),
            $this->level,
        );
    }

    /** Os marcos visíveis, já aplicados os dois filtros. */
    #[Computed]
    public function marcosVisiveis(): Collection
    {
        $areas = $this->areaCode === null
            ? $this->areas
            : $this->areas->where('code', $this->areaCode);

        $itens = $areas->flatMap(fn ($area) => $area->items);

        if ($this->somentePendentes) {
            $itens = $itens->reject(fn ($item) => $this->respostas->get($item->id)?->isAnswered() ?? false);
        }

        return $itens->values();
    }

    #[Computed]
    public function areaAtual()
    {
        return $this->areaCode === null ? null : $this->areas->firstWhere('code', $this->areaCode);
    }

    #[Computed]
    public function proximaAreaIncompleta(): ?string
    {
        foreach ($this->areas as $area) {
            if (($this->respondidasPorArea[$area->code] ?? 0) < ProgressCounter::MARCOS_POR_AREA
                && $area->code !== $this->areaCode) {
                return $area->code;
            }
        }

        return null;
    }

    private function primeiraAreaIncompleta(): ?string
    {
        foreach ($this->areas as $area) {
            if (($this->respondidasPorArea[$area->code] ?? 0) < ProgressCounter::MARCOS_POR_AREA) {
                return $area->code;
            }
        }

        return $this->areas->first()?->code;
    }

    public function selecionarArea(?string $code): void
    {
        $this->areaCode = $code;
        $this->limparEstadoDerivado();
    }

    public function alternarPendentes(): void
    {
        $this->somentePendentes = ! $this->somentePendentes;
        $this->limparEstadoDerivado();
    }

    /**
     * Um cartão gravou: o progresso mudou, então o estado derivado precisa
     * cair. O cartão em si já se atualizou sozinho.
     */
    #[On('resposta-salva')]
    public function aoSalvarResposta(?string $salvoEm = null): void
    {
        $this->salvoEm = $salvoEm;
        $this->limparEstadoDerivado();
    }

    private function limparEstadoDerivado(): void
    {
        unset($this->respostas, $this->respostasVisiveis, $this->respondidasPorArea,
            $this->levelProgress, $this->marcosVisiveis, $this->areaAtual,
            $this->proximaAreaIncompleta, $this->assessmentLevel);
    }

    public function confirmarConclusao(): void
    {
        $this->confirmandoConclusao = true;
    }

    public function concluirNivel(): void
    {
        try {
            app(CompleteLevel::class)->handle($this->assessmentLevel);
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();
            $this->confirmandoConclusao = false;

            return;
        }

        $this->confirmandoConclusao = false;
        $this->limparEstadoDerivado();

        $this->redirectRoute('avaliacoes.show', $this->assessmentId, navigate: true);
    }

    public function render()
    {
        return view('livewire.assessment.level-board');
    }
}
