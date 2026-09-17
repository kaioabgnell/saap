<?php

declare(strict_types=1);

namespace App\Livewire\Assessment;

use App\Application\Report\GenerateAiSummary;
use App\Jobs\GenerateFormPdf;
use App\Models\Assessment;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Ação "Imprimir formulário": três opções, gera em fila, baixa quando pronto.
 *
 * Isolado do LevelBoard porque a geração pode levar segundos — o modal e o
 * polling não podem prender o resto da tela do nível.
 */
class PrintForm extends Component
{
    #[Locked]
    public int $assessmentId;

    #[Locked]
    public int $level;

    #[Locked]
    public string $slugAprendiz;

    public bool $aberto = false;

    public bool $somentePendentes = false;

    public bool $incluirCriterios = false;

    public bool $incluirExemplares = true;

    /** O conteúdo do PDF: um, outro, ou os dois. */
    public bool $incluirResultado = true;

    public bool $incluirResumoIa = false;

    public ?string $token = null;

    public string $status = 'idle'; // idle | queued | ready | failed

    public ?string $erro = null;

    public function mount(Assessment $assessment, int $level): void
    {
        $this->assessmentId = $assessment->id;
        $this->level = $level;
        $this->slugAprendiz = Str::slug($assessment->learner->name) ?: 'aprendiz';
    }

    public function abrir(): void
    {
        $this->aberto = true;
        $this->status = 'idle';
        $this->token = null;
        $this->erro = null;
    }

    /**
     * O resumo por IA está à mão? Precisa de chave configurada E de nível
     * todo respondido — resumir meia avaliação produziria um texto que parece
     * completo e não é, e esse texto vai para a família.
     */
    #[Computed]
    public function resumoDisponivel(): bool
    {
        return $this->resumoConfigurado && $this->nivelCompleto;
    }

    #[Computed]
    public function resumoConfigurado(): bool
    {
        return app(GenerateAiSummary::class)->disponivel();
    }

    #[Computed]
    public function nivelCompleto(): bool
    {
        return app(GenerateAiSummary::class)
            ->nivelEstaCompleto($this->assessmentId, $this->level);
    }

    public function fechar(): void
    {
        $this->aberto = false;
    }

    public function gerar(): void
    {
        // Sem nenhum dos dois, o PDF sairia em branco. Barra aqui em vez de
        // enfileirar um job que só produz papel vazio.
        if (! $this->incluirResultado && ! $this->comResumo()) {
            $this->erro = 'Escolha ao menos um conteúdo para o PDF.';

            return;
        }

        $this->token = (string) Str::uuid();
        $this->status = 'queued';
        $this->erro = null;

        GenerateFormPdf::iniciar($this->token);

        GenerateFormPdf::dispatch(
            token: $this->token,
            assessmentId: $this->assessmentId,
            level: $this->level,
            onlyPending: $this->somentePendentes,
            includeCriteria: $this->incluirCriterios,
            includeExamples: $this->incluirExemplares,
            slugAprendiz: $this->slugAprendiz,
            includeResults: $this->incluirResultado,
            includeAiSummary: $this->comResumo(),
        );

        // Em fila 'database' o job ainda não rodou aqui — o wire:poll é quem
        // vai perceber. Em 'sync' (testes) ele já terminou: sem checar agora,
        // o componente ficaria preso em "queued" até o primeiro poll.
        $this->verificarStatus();
    }

    /**
     * A marca do usuário só vale se o recurso estiver mesmo disponível: o
     * checkbox pode ter sido marcado e o nível deixar de estar completo
     * depois (outra aba apagando uma resposta). Revalidar aqui evita
     * enfileirar um resumo que o caso de uso vai recusar.
     */
    private function comResumo(): bool
    {
        return $this->incluirResumoIa && $this->resumoDisponivel();
    }

    /** Chamado por wire:poll enquanto o status for "queued". */
    public function verificarStatus(): void
    {
        if ($this->token === null || $this->status !== 'queued') {
            return;
        }

        $info = GenerateFormPdf::status($this->token);

        if ($info === null) {
            return;
        }

        $this->status = $info['status'];
        $this->erro = $info['error'] ?? null;
    }

    public function render()
    {
        return view('livewire.assessment.print-form');
    }
}
