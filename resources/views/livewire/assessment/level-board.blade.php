@php
    $avaliacao = $this->assessment;
    $aprendiz = $avaliacao->learner;
    $progresso = $this->levelProgress;
    $nivel = $this->assessmentLevel;
@endphp

{{-- O respiro no rodapé precisa somar a barra fixa E a área segura do
     aparelho: num iPhone com indicador de home a barra cresce, e um pb-28 seco
     esconderia o último cartão atrás dela. --}}
<div style="padding-bottom: calc(7rem + env(safe-area-inset-bottom));">

    {{-- Cabeçalho de identificação — fixo durante toda a aplicação --}}
    <div class="sticky top-0 z-20 border-b border-line bg-surface/95 backdrop-blur">
        <div class="mx-auto max-w-5xl px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <div class="flex min-w-0 items-center gap-3">
                    @if ($aprendiz->photoUrl('miniatura'))
                        <img src="{{ $aprendiz->photoUrl('miniatura') }}" alt="" class="h-9 w-9 flex-none rounded-full object-cover">
                    @else
                        <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-primary-soft">
                            <i class="fa-solid fa-child text-primary" aria-hidden="true"></i>
                        </span>
                    @endif
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ $aprendiz->name }}</p>
                        <p class="text-xs text-ink-muted">{{ $aprendiz->ageAt($avaliacao->applied_on)->format() }}</p>
                    </div>
                </div>

                <div class="hidden sm:block">
                    <p class="text-[10px] font-medium uppercase tracking-wider text-ink-subtle">Aplicação</p>
                    <p class="text-sm text-ink">{{ $avaliacao->applied_on->format('d/m/Y') }}</p>
                </div>

                <div class="hidden md:block">
                    <p class="text-[10px] font-medium uppercase tracking-wider text-ink-subtle">Aplicador</p>
                    <p class="truncate text-sm text-ink">{{ $avaliacao->user->name }}</p>
                </div>

                <div class="ml-auto flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-[10px] font-medium uppercase tracking-wider text-ink-subtle">Nível {{ $level }}</p>
                        <p class="text-sm font-semibold tabular-nums text-ink">{{ $progresso->format() }}</p>
                    </div>
                    <div class="h-2 w-24 overflow-hidden rounded-full bg-canvas" role="progressbar"
                         aria-valuenow="{{ $progresso->percent() }}" aria-valuemin="0" aria-valuemax="100"
                         aria-label="Progresso do nível {{ $level }}">
                        <div class="h-full rounded-full bg-primary transition-all" style="width: {{ $progresso->percent() }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">

        @if ($erro)
            <p class="mb-4 rounded-md bg-danger-soft px-4 py-3 text-sm text-danger">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ $erro }}
            </p>
        @endif

        {{-- Navegação por área --}}
        <nav aria-label="Áreas do nível {{ $level }}" class="-mx-4 mb-6 overflow-x-auto px-4 sm:mx-0 sm:px-0">
            <ul class="flex gap-2 pb-1">
                @foreach ($this->areas as $area)
                    @php
                        $feitos = $this->respondidasPorArea[$area->code] ?? 0;
                        $completa = $feitos >= 5;
                        $ativa = $areaCode === $area->code;
                    @endphp
                    <li class="flex-none">
                        <button type="button" wire:click="selecionarArea('{{ $area->code }}')"
                                @class([
                                    'flex min-h-[44px] items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition',
                                    'border-primary bg-primary text-white' => $ativa,
                                    'border-line bg-surface text-ink hover:bg-canvas' => ! $ativa,
                                ])>
                            <span>{{ $area->short_name }}</span>
                            <span @class([
                                'rounded-full px-1.5 py-0.5 text-[11px] tabular-nums',
                                'bg-white/20 text-white' => $ativa,
                                'bg-success-soft text-success-ink' => ! $ativa && $completa,
                                'bg-canvas text-ink-muted' => ! $ativa && ! $completa,
                            ])>{{ $feitos }}/5</span>
                        </button>
                    </li>
                @endforeach
                <li class="flex-none">
                    <button type="button" wire:click="selecionarArea(null)"
                            @class([
                                'flex min-h-[44px] items-center rounded-md border px-3 py-2 text-sm font-medium',
                                'border-primary bg-primary text-white' => $areaCode === null,
                                'border-line bg-surface text-ink-muted hover:bg-canvas' => $areaCode !== null,
                            ])>
                        Todas as áreas
                    </button>
                </li>
            </ul>
        </nav>

        {{-- Cartões dos marcos --}}
        <div class="space-y-4">
            @forelse ($this->marcosVisiveis as $item)
                <livewire:assessment.item-card
                    :assessment="$avaliacao"
                    :item="$item"
                    :response="$this->respostasVisiveis->get($item->id)"
                    :wire:key="'marco-'.$item->id" />
            @empty
                <div class="rounded-lg border border-dashed border-line bg-surface p-10 text-center">
                    <i class="fa-solid fa-circle-check text-3xl text-success-ink" aria-hidden="true"></i>
                    <p class="mt-3 text-sm text-ink-muted">
                        @if ($somentePendentes)
                            Nenhuma pendência {{ $this->areaAtual ? 'nesta área' : 'neste nível' }}.
                        @else
                            Nenhum marco para exibir.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>

        {{-- Avançar para a próxima área incompleta --}}
        @if ($this->areaAtual && ($this->respondidasPorArea[$areaCode] ?? 0) >= 5 && $this->proximaAreaIncompleta)
            <div class="mt-6 flex justify-end">
                <button type="button" wire:click="selecionarArea('{{ $this->proximaAreaIncompleta }}')"
                        class="inline-flex min-h-[44px] items-center gap-2 rounded-md border border-line bg-surface px-4 py-2 text-sm font-medium text-ink hover:bg-canvas">
                    Próxima área incompleta
                    <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
                </button>
            </div>
        @endif
    </div>

    {{-- Barra de ações, fixa no rodapé --}}
    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-surface/95 backdrop-blur"
         style="padding-bottom: env(safe-area-inset-bottom);">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">

            {{-- Indicador de salvamento --}}
            <div class="flex items-center gap-2 text-sm" wire:loading.class="opacity-60">
                <span wire:loading wire:target="salvar, confirmar">
                    <i class="fa-solid fa-circle-notch fa-spin text-ink-subtle" aria-hidden="true"></i>
                    <span class="text-ink-muted">Salvando…</span>
                </span>
                <span wire:loading.remove wire:target="salvar, confirmar" x-data="filaSalvamento">
                    <template x-if="pendentes === 0">
                        <span class="flex items-center gap-1.5">
                            <i class="fa-solid fa-check text-success-ink" aria-hidden="true"></i>
                            <span class="text-ink-muted">
                                {{ $salvoEm ? 'Salvo às '.$salvoEm : 'Nada pendente' }}
                            </span>
                        </span>
                    </template>
                    <template x-if="pendentes > 0">
                        <span class="flex items-center gap-1.5">
                            <i class="fa-solid fa-triangle-exclamation text-warning-ink" aria-hidden="true"></i>
                            <span class="text-warning-ink">
                                Sem conexão — <span x-text="pendentes"></span> <span x-text="pendentes === 1 ? 'alteração' : 'alterações'"></span> na fila
                            </span>
                        </span>
                    </template>
                </span>
            </div>

            <livewire:assessment.print-form :assessment="$avaliacao" :level="$level" :key="'print-'.$level" />

            {{-- Filtro de pendências --}}
            <button type="button" wire:click="alternarPendentes"
                    aria-pressed="{{ $somentePendentes ? 'true' : 'false' }}"
                    @class([
                        'ml-auto inline-flex min-h-[44px] items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium',
                        'border-primary bg-primary-soft text-primary' => $somentePendentes,
                        'border-line bg-surface text-ink hover:bg-canvas' => ! $somentePendentes,
                    ])>
                <i class="fa-solid fa-filter text-xs" aria-hidden="true"></i>
                Somente não respondidas
                <span class="rounded-full bg-canvas px-1.5 py-0.5 text-[11px] tabular-nums text-ink-muted">
                    {{ $progresso->pending() }}
                </span>
            </button>

            {{-- Ação primária: só vira "Concluir" quando o nível fecha --}}
            @if ($progresso->isComplete() && $nivel->status->value !== 'completed')
                <button type="button" wire:click="confirmarConclusao"
                        class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                    <i class="fa-solid fa-flag-checkered" aria-hidden="true"></i>
                    Concluir nível {{ $level }}
                </button>
            @elseif ($nivel->status->value === 'completed')
                <span class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-success-soft px-4 py-2 text-sm font-medium text-success-ink">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    Nível {{ $level }} concluído
                </span>
            @endif
        </div>
    </div>

    {{-- Confirmação de conclusão do nível --}}
    @if ($confirmandoConclusao)
        <div class="fixed inset-0 z-30 flex items-center justify-center bg-ink/40 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-lg bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">Concluir nível {{ $level }}</h2>
                <p class="mt-2 text-sm text-ink-muted">
                    Os {{ $progresso->total }} marcos deste nível foram respondidos, somando
                    <span class="font-medium text-ink">{{ rtrim(rtrim(number_format($nivel->score_total, 1, ',', ''), '0'), ',') }}</span>
                    de {{ $progresso->total }} pontos.
                </p>
                <p class="mt-2 text-sm text-ink-muted">
                    Você poderá continuar editando as respostas até concluir a avaliação inteira.
                </p>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" wire:click="$set('confirmandoConclusao', false)"
                            class="min-h-[44px] rounded-md px-4 py-2 text-sm font-medium text-ink-muted hover:text-ink">
                        Voltar
                    </button>
                    <button type="button" wire:click="concluirNivel"
                            class="min-h-[44px] rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                        Concluir nível
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
