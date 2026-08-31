<?php

declare(strict_types=1);

namespace App\Livewire\Assessment;

use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Domain\Vbmapp\Scoring\EntryTally;
use App\Domain\Vbmapp\Scoring\MatrixStrategy;
use App\Domain\Vbmapp\Scoring\ResponseType;
use App\Models\Assessment;
use App\Models\Response;
use App\Models\Vbmapp\Item;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * Um marco.
 *
 * É o componente do salvamento, isolado de propósito: marcar um check envia só
 * o estado deste cartão, não os 45 da tela. O psicólogo está com a criança à
 * frente — cada gravação precisa ser barata e imediata.
 */
class ItemCard extends Component
{
    #[Locked]
    public int $assessmentId;

    #[Locked]
    public int $itemId;

    /** O nível do marco. Serve para achá-lo no cache do catálogo sem ir ao banco. */
    #[Locked]
    public int $level;

    /** Caixas de texto dos tipos counter_free e counter_stimuli (fallback da F4). */
    public array $caixas = [];

    /** Checks do tipo counter_list, indexados pela palavra da lista. */
    public array $marcadas = [];

    /** Checks da grade de imagens, indexados pelo id do estímulo. */
    public array $estimulos = [];

    /**
     * Itens acrescentados a um counter_list além da fixed_list — o
     * instrumento autoriza ("Você pode usar o espaço fornecido para
     * escrever outros itens"). Nunca altera o catálogo; grava em
     * response_entries.text_value, à parte dos checks da lista fixa.
     */
    public array $itensAcrescentados = [];

    public string $novoItemLista = '';

    /** Checks da matrix, indexados por "linha::coluna". */
    public array $matrizMarcadas = [];

    /** Linhas acrescentadas à matrix além da fixed_list (ou a única fonte de linhas, quando não há lista fixa — caso do Tato 11). */
    public array $matrizExtras = [];

    public string $novaLinhaMatrix = '';

    /** Ordinal escolhido em binary_criteria: 2 = 1 ponto, 1 = ½, 0 = não atingiu. */
    public ?int $ordinal = null;

    public ?string $observacao = null;

    public bool $criteriosAbertos = false;

    // Estado derivado, devolvido pelo caso de uso a cada gravação.
    public float $score = 0.0;

    public int $acertos = 0;

    public bool $respondido = false;

    public bool $pedeConfirmacao = false;

    public bool $sobrescrito = false;

    public ?string $salvoEm = null;

    public ?string $erro = null;

    public function mount(Assessment $assessment, Item $item, ?Response $response = null): void
    {
        $this->assessmentId = $assessment->id;
        $this->itemId = $item->id;
        $this->level = $item->level;

        $this->hidratar($item, $response);
    }

    private function hidratar(Item $item, ?Response $response): void
    {
        $entradas = $response?->entries ?? collect();

        match ($item->response_type) {
            ResponseType::CounterFree => $this->caixas = $this->hidratarCaixas($item, $entradas, $item->threshold_full),
            ResponseType::CounterStimuli => $this->hidratarGrade($item, $entradas),
            ResponseType::CounterList => $this->hidratarListaEAcrescimos($item, $entradas),
            ResponseType::BinaryCriteria => $this->ordinal = $this->hidratarOrdinal($entradas),
            ResponseType::Matrix => $this->hidratarMatrix($item, $entradas),
        };

        if ($response === null) {
            return;
        }

        $this->observacao = $response->notes;
        $this->score = (float) $response->score;
        $this->respondido = $response->isAnswered();
        $this->sobrescrito = (bool) $response->is_overridden;
        $this->pedeConfirmacao = $item->scoring_mode->requiresConfirmation() && ! $response->isAnswered();
        $this->acertos = $this->contarLocal($item);
    }

    /**
     * A grade cobre o que o acervo tem; o que faltar vira caixa de texto.
     * Falta de imagem nunca pode bloquear a aplicação.
     */
    private function hidratarGrade(Item $item, $entradas): void
    {
        foreach ($item->stimuli as $estimulo) {
            $this->estimulos[$estimulo->id] = false;
        }

        foreach ($entradas as $entrada) {
            if ($entrada->stimulus_id !== null && array_key_exists($entrada->stimulus_id, $this->estimulos)) {
                $this->estimulos[$entrada->stimulus_id] = (bool) $entrada->is_checked;
            }
        }

        $faltando = max(0, $item->threshold_full - count($this->estimulos));
        $this->caixas = $faltando > 0 ? $this->hidratarCaixas($item, $entradas, $faltando) : [];
    }

    private function hidratarCaixas(Item $item, $entradas, int $quantidade): array
    {
        $caixas = array_fill(1, max(1, $quantidade), '');

        foreach ($entradas as $entrada) {
            if (isset($caixas[$entrada->position])) {
                $caixas[$entrada->position] = (string) $entrada->text_value;
            }
        }

        return $caixas;
    }

    private function hidratarLista(Item $item, $entradas): array
    {
        $marcadas = [];

        foreach ($item->fixed_list ?? [] as $palavra) {
            $marcadas[$palavra] = false;
        }

        foreach ($entradas as $entrada) {
            if ($entrada->list_key !== null && array_key_exists($entrada->list_key, $marcadas)) {
                $marcadas[$entrada->list_key] = (bool) $entrada->is_checked;
            }
        }

        return $marcadas;
    }

    private function hidratarListaEAcrescimos(Item $item, $entradas): void
    {
        $this->marcadas = $this->hidratarLista($item, $entradas);

        $this->itensAcrescentados = [];
        foreach ($entradas as $entrada) {
            $texto = trim((string) $entrada->text_value);
            if ($entrada->list_key === null && $texto !== '') {
                $this->itensAcrescentados[] = $texto;
            }
        }
    }

    private function hidratarMatrix(Item $item, $entradas): void
    {
        $fixas = $item->fixed_list ?? [];
        $extras = [];

        foreach ($entradas as $entrada) {
            if ($entrada->list_key === null || $entrada->column_key === null) {
                continue;
            }

            $chave = "{$entrada->list_key}::{$entrada->column_key}";
            $this->matrizMarcadas[$chave] = (bool) $entrada->is_checked;

            if (! in_array($entrada->list_key, $fixas, true) && ! in_array($entrada->list_key, $extras, true)) {
                $extras[] = $entrada->list_key;
            }
        }

        $this->matrizExtras = $extras;
    }

    private function hidratarOrdinal($entradas): ?int
    {
        foreach ($entradas as $entrada) {
            if ($entrada->is_checked) {
                return (int) $entrada->position;
            }
        }

        return null;
    }

    /**
     * O marco vem do cache do catálogo, não do banco.
     *
     * Buscar por id aqui seria um N+1 clássico: são até 65 cartões numa tela,
     * cada um refazendo a consulta do próprio marco mais a da área. O catálogo
     * é imutável em runtime, então já está em memória — ver CatalogCache.
     */
    #[Computed]
    public function item(): Item
    {
        return CatalogCache::item($this->level, $this->itemId)
            ?? Item::with('area', 'stimuli')->findOrFail($this->itemId);
    }

    /** Linhas da matrix: a lista fixa do catálogo mais o que foi acrescentado. */
    public function linhasDaMatriz(): array
    {
        return $this->linhasDaMatrizPara($this->item);
    }

    /** Colunas da matrix, vindas do catálogo — ex.: ["Cor", "Forma", "Função"]. */
    public function colunasDaMatriz(): array
    {
        return $this->item->matrix_columns['columns'] ?? [];
    }

    /** Contagem otimista, só para o contador vivo entre uma gravação e outra. */
    private function contarLocal(Item $item): int
    {
        return match ($item->response_type) {
            ResponseType::CounterFree => count(array_filter(
                $this->caixas, static fn ($v) => trim((string) $v) !== ''
            )),
            ResponseType::CounterStimuli => count(array_filter($this->estimulos)) + count(array_filter(
                $this->caixas, static fn ($v) => trim((string) $v) !== ''
            )),
            ResponseType::CounterList => count(array_filter($this->marcadas)) + count(array_filter(
                $this->itensAcrescentados, static fn ($v) => trim((string) $v) !== ''
            )),
            ResponseType::BinaryCriteria => $this->ordinal ?? 0,
            ResponseType::Matrix => app(EntryTally::class)->count(
                ResponseType::Matrix,
                $this->entradasDaMatrix($item),
                MatrixStrategy::fromColumns($item->matrix_columns),
            ),
        };
    }

    /** Qualquer mudança de estado do cartão dispara a gravação. */
    public function updated(string $campo): void
    {
        if (str_starts_with($campo, 'caixas') || str_starts_with($campo, 'marcadas')
            || str_starts_with($campo, 'estimulos') || str_starts_with($campo, 'matrizMarcadas')
            || $campo === 'ordinal' || $campo === 'observacao') {
            $this->salvar();
        }
    }

    public function salvar(?float $explicito = null): void
    {
        $item = $this->item;

        try {
            $resultado = app(SaveResponse::class)->handle(new SaveResponseCommand(
                assessmentId: $this->assessmentId,
                itemId: $this->itemId,
                entries: $this->montarEntradas($item),
                notes: $this->observacao,
                explicitScore: $explicito,
            ));
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        $this->erro = null;
        $this->score = $resultado->score->toFloat();
        $this->acertos = $resultado->tally;
        $this->respondido = $resultado->isAnswered;
        $this->pedeConfirmacao = $resultado->needsConfirmation;
        $this->sobrescrito = $resultado->isOverridden;
        $this->salvoEm = $resultado->savedAt;

        $this->dispatch('resposta-salva',
            itemId: $this->itemId,
            areaId: $item->area_id,
            salvoEm: $resultado->savedAt,
        );
    }

    /** Confirma o critério de um marco assisted, ou registra zero deliberado. */
    public function confirmar(string $valor): void
    {
        $this->salvar((float) $valor);
    }

    /** @return list<array<string, mixed>> */
    private function montarEntradas(Item $item): array
    {
        return match ($item->response_type) {
            ResponseType::CounterFree => $this->entradasDasCaixas(0),

            ResponseType::CounterStimuli => [
                ...$this->entradasDaGrade(),
                ...$this->entradasDasCaixas(count($this->estimulos)),
            ],

            ResponseType::CounterList => $this->entradasDaLista(),

            ResponseType::BinaryCriteria => $this->ordinal === null
                ? []
                : [['position' => $this->ordinal, 'is_checked' => true]],

            ResponseType::Matrix => $this->entradasDaMatrix($item),
        };
    }

    /** @return list<array<string, mixed>> */
    private function entradasDasCaixas(int $deslocamento): array
    {
        $entradas = [];

        foreach ($this->caixas as $posicao => $valor) {
            $entradas[] = [
                'position' => $deslocamento + (int) $posicao,
                'text_value' => (string) $valor,
            ];
        }

        return $entradas;
    }

    /** @return list<array<string, mixed>> */
    private function entradasDaGrade(): array
    {
        $entradas = [];
        $posicao = 1;

        foreach ($this->estimulos as $estimuloId => $marcado) {
            $entradas[] = [
                'position' => $posicao++,
                'stimulus_id' => (int) $estimuloId,
                'is_checked' => (bool) $marcado,
            ];
        }

        return $entradas;
    }

    /** @return list<array<string, mixed>> */
    private function entradasDaLista(): array
    {
        $entradas = [];
        $posicao = 1;

        foreach ($this->marcadas as $palavra => $marcada) {
            $entradas[] = [
                'position' => $posicao++,
                'list_key' => (string) $palavra,
                'is_checked' => (bool) $marcada,
            ];
        }

        // Acréscimo do instrumento ("escreva outros itens"): vai em
        // text_value, à parte dos checks da lista fixa, e nunca altera
        // vbmapp_items.fixed_list — o catálogo é imutável.
        foreach ($this->itensAcrescentados as $texto) {
            $entradas[] = [
                'position' => $posicao++,
                'text_value' => (string) $texto,
            ];
        }

        return $entradas;
    }

    /** @return list<array<string, mixed>> */
    private function entradasDaMatrix(Item $item): array
    {
        $entradas = [];
        $posicao = 1;
        $colunas = $item->matrix_columns['columns'] ?? [];

        foreach ($this->linhasDaMatrizPara($item) as $linha) {
            foreach ($colunas as $coluna) {
                $chave = "{$linha}::{$coluna}";
                $entradas[] = [
                    'position' => $posicao++,
                    'list_key' => $linha,
                    'column_key' => $coluna,
                    'is_checked' => (bool) ($this->matrizMarcadas[$chave] ?? false),
                ];
            }
        }

        return $entradas;
    }

    /** @return list<string> */
    private function linhasDaMatrizPara(Item $item): array
    {
        return [...($item->fixed_list ?? []), ...$this->matrizExtras];
    }

    /**
     * Páginas inteiras do material, como conferência e fallback da grade.
     *
     * Lê de um índice montado uma vez por requisição, não do banco: uma
     * consulta por cartão multiplicaria por até 65 na tela do nível — o mesmo
     * N+1 que a F3 já havia resolvido para marcos e áreas.
     */
    #[Computed]
    public function paginasDoMarco()
    {
        $item = $this->item;

        return CatalogCache::materialPagesFor($this->level, $item->area_id, $item->position);
    }

    /** Acréscimo em counter_list — o instrumento autoriza escrever itens fora da lista fixa. */
    public function acrescentarItemLista(): void
    {
        $valor = trim($this->novoItemLista);

        if ($valor === '') {
            return;
        }

        $this->itensAcrescentados[] = $valor;
        $this->novoItemLista = '';
        $this->salvar();
    }

    /** Acréscimo em matrix — mesma autorização do instrumento, mas a linha vira uma grade própria. */
    public function acrescentarLinhaMatriz(): void
    {
        $valor = trim($this->novaLinhaMatrix);

        if ($valor === '' || in_array($valor, $this->matrizExtras, true)
            || in_array($valor, $this->item->fixed_list ?? [], true)) {
            return;
        }

        $this->matrizExtras[] = $valor;
        $this->novaLinhaMatrix = '';
        // Sem checks ainda: só grava quando a primeira célula da linha for marcada.
    }

    public function alternarCriterios(): void
    {
        $this->criteriosAbertos = ! $this->criteriosAbertos;
    }

    public function render()
    {
        return view('livewire.assessment.item-card', ['item' => $this->item]);
    }
}
