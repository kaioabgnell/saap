@use('App\Domain\Vbmapp\Scoring\ResponseType')
@php
    // Borda esquerda codifica o estado — ver 03-design-system.md.
    // "0 ponto" e "não respondido" são estados diferentes: marcar zero é ato
    // clínico, deixar em branco é pendência.
    $faixa = match (true) {
        ! $respondido => 'border-l-line border-dashed',
        $score >= 1.0 => 'border-l-success',
        $score >= 0.5 => 'border-l-warning',
        default => 'border-l-ink-subtle',
    };
@endphp

<div class="rounded-lg border border-line border-l-[3px] {{ $faixa }} bg-surface p-5 shadow-sm">

    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="font-mono text-[11px] font-semibold uppercase tracking-wider text-primary">
                {{ $item->area->short_name }} {{ $item->position }}
            </p>
            <p class="mt-1 max-w-[68ch] text-[17px] leading-relaxed text-ink">{{ $item->statement }}</p>
        </div>

        <div class="flex flex-none flex-col items-end gap-2">
            @if ($item->observation_minutes)
                <span class="whitespace-nowrap rounded-full bg-canvas px-2.5 py-1 text-[11px] font-medium text-ink-muted">
                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                    {{ $item->observation_minutes }} min de observação
                </span>
            @endif

            @if ($respondido)
                <span @class([
                    'flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold',
                    'bg-success-soft text-success-ink' => $score >= 1.0,
                    'bg-warning-soft text-warning-ink' => $score >= 0.5 && $score < 1.0,
                    'bg-canvas text-ink-subtle' => $score < 0.5,
                ])>
                    {{ $score >= 1.0 ? '1' : ($score >= 0.5 ? '½' : '0') }}
                </span>
            @endif
        </div>
    </div>

    @if ($erro)
        <p class="mt-3 rounded-md bg-danger-soft px-3 py-2 text-sm text-danger">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ $erro }}
        </p>
    @endif

    @if ($item->response_type !== ResponseType::CounterStimuli && $this->paginasDoMarco->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($this->paginasDoMarco as $pagina)
                <a href="{{ Storage::disk('public')->url($pagina->image_path) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 rounded-md border border-line px-2.5 py-1.5 text-xs text-ink-muted hover:bg-canvas">
                    <i class="fa-solid fa-file-image" aria-hidden="true"></i>
                    Página {{ $pagina->page_number }} do material
                </a>
            @endforeach
        </div>
    @endif

    {{-- Controle conforme o tipo de resposta do marco --}}
    <div class="mt-4">
        @switch($item->response_type)

            @case(ResponseType::BinaryCriteria)
                <fieldset class="space-y-2">
                    <legend class="sr-only">Critério atingido</legend>

                    <label class="flex cursor-pointer items-start gap-3 rounded-md border border-line p-3 hover:bg-canvas has-[:checked]:border-primary has-[:checked]:bg-primary-soft">
                        <input type="radio" wire:model.live="ordinal" value="2" class="mt-1 text-primary focus:ring-primary">
                        <span class="text-sm text-ink">{{ $item->criteria_full }}</span>
                    </label>

                    @if ($item->criteria_half)
                        <label class="flex cursor-pointer items-start gap-3 rounded-md border border-line p-3 hover:bg-canvas has-[:checked]:border-warning has-[:checked]:bg-warning-soft">
                            <input type="radio" wire:model.live="ordinal" value="1" class="mt-1 text-warning-ink focus:ring-warning">
                            <span class="text-sm text-ink">{{ $item->criteria_half }}</span>
                        </label>
                    @endif

                    <label class="flex cursor-pointer items-start gap-3 rounded-md border border-line p-3 hover:bg-canvas has-[:checked]:border-ink-subtle has-[:checked]:bg-canvas">
                        <input type="radio" wire:model.live="ordinal" value="0" class="mt-1 text-ink-subtle focus:ring-ink-subtle">
                        <span class="text-sm text-ink-muted">Não atingiu o critério</span>
                    </label>
                </fieldset>
                @break

            @case(ResponseType::CounterList)
                <div x-data="{ busca: '' }">
                    @if (count($marcadas) > 20)
                        <div class="relative mb-3">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-ink-subtle" aria-hidden="true"></i>
                            <input type="search" x-model="busca" placeholder="Buscar na lista de {{ count($marcadas) }} itens…"
                                   class="min-h-[44px] w-full rounded-md border-line pl-8 text-sm focus:border-primary focus:ring-primary">
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($marcadas as $palavra => $marcada)
                            <label x-show="!busca || {{ \Illuminate\Support\Js::from(mb_strtolower($palavra)) }}.includes(busca.toLowerCase())"
                                   class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 hover:bg-canvas">
                                <input type="checkbox" wire:model.live="marcadas.{{ $palavra }}"
                                       class="rounded border-line text-success-ink focus:ring-success">
                                <span class="text-sm text-ink">{{ $palavra }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                @if (count($itensAcrescentados) > 0)
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach ($itensAcrescentados as $extra)
                            <li class="rounded-full bg-success-soft px-2.5 py-1 text-xs font-medium text-success-ink">
                                <i class="fa-solid fa-plus text-[9px]" aria-hidden="true"></i> {{ $extra }}
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-3 flex items-center gap-2">
                    <input type="text" wire:model="novoItemLista" wire:keydown.enter="acrescentarItemLista"
                           placeholder="Acrescentar item fora da lista"
                           aria-label="Acrescentar item fora da lista"
                           class="min-h-[44px] flex-1 rounded-md border-line text-sm focus:border-primary focus:ring-primary">
                    <button type="button" wire:click="acrescentarItemLista"
                            class="min-h-[44px] rounded-md border border-line px-3 text-sm font-medium text-ink hover:bg-canvas">
                        Adicionar
                    </button>
                </div>
                @break

            @case(ResponseType::CounterStimuli)
                <x-vbmapp.stimulus-grid :item="$item" :estimulos="$estimulos" :paginas="$this->paginasDoMarco" />

                @if (count($caixas) > 0)
                    <p class="mt-4 mb-2 rounded-md bg-canvas px-3 py-2 text-xs text-ink-muted">
                        <i class="fa-regular fa-image" aria-hidden="true"></i>
                        O acervo tem menos imagens que este marco exige. Registre por
                        escrito os {{ count($caixas) }} exemplares restantes.
                    </p>

                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($caixas as $posicao => $valor)
                            <div class="flex items-center gap-2">
                                <span class="w-6 flex-none text-right font-mono text-xs text-ink-subtle">{{ $posicao }}.</span>
                                <input type="text"
                                       wire:model.live.debounce.800ms="caixas.{{ $posicao }}"
                                       wire:blur="salvar"
                                       aria-label="Exemplar complementar {{ $posicao }}"
                                       class="min-h-[44px] w-full rounded-md border-line text-sm focus:border-primary focus:ring-primary">
                            </div>
                        @endforeach
                    </div>
                @endif
                @break

            @case(ResponseType::Matrix)
                @php $linhasMatriz = $this->linhasDaMatriz(); $colunasMatriz = $this->colunasDaMatriz(); @endphp

                {{-- A grade rola dentro do próprio contêiner — a página nunca rola de lado. --}}
                <div x-data="{ busca: '' }">
                    @if (count($linhasMatriz) > 20)
                        <div class="relative mb-3">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-ink-subtle" aria-hidden="true"></i>
                            <input type="search" x-model="busca" placeholder="Buscar entre {{ count($linhasMatriz) }} itens…"
                                   class="min-h-[44px] w-full rounded-md border-line pl-8 text-sm focus:border-primary focus:ring-primary">
                        </div>
                    @endif

                    <div class="overflow-x-auto rounded-md border border-line">
                        <table class="w-full min-w-[480px] text-sm">
                            <thead>
                                <tr class="border-b border-line bg-canvas">
                                    <th class="sticky left-0 bg-canvas px-3 py-2 text-left font-medium text-ink-muted">Item</th>
                                    @foreach ($colunasMatriz as $coluna)
                                        <th class="px-3 py-2 text-center font-medium text-ink-muted">{{ $coluna }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($linhasMatriz as $linha)
                                    <tr x-show="!busca || {{ \Illuminate\Support\Js::from(mb_strtolower($linha)) }}.includes(busca.toLowerCase())"
                                        class="border-b border-line last:border-0 odd:bg-surface even:bg-canvas/40">
                                        <td class="sticky left-0 bg-inherit px-3 py-2 text-ink">{{ $linha }}</td>
                                        @foreach ($colunasMatriz as $coluna)
                                            @php $chave = $linha.'::'.$coluna; @endphp
                                            <td class="px-3 py-2 text-center">
                                                <input type="checkbox"
                                                       wire:model.live="matrizMarcadas.{{ $chave }}"
                                                       aria-label="{{ $linha }}, {{ $coluna }}"
                                                       class="h-5 w-5 rounded border-line text-success-ink focus:ring-success">
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <input type="text" wire:model="novaLinhaMatrix" wire:keydown.enter="acrescentarLinhaMatriz"
                           placeholder="Acrescentar item à grade"
                           aria-label="Acrescentar item à grade"
                           class="min-h-[44px] flex-1 rounded-md border-line text-sm focus:border-primary focus:ring-primary">
                    <button type="button" wire:click="acrescentarLinhaMatriz"
                            class="min-h-[44px] rounded-md border border-line px-3 text-sm font-medium text-ink hover:bg-canvas">
                        Adicionar
                    </button>
                </div>
                @break

            @default
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($caixas as $posicao => $valor)
                        <div class="flex items-center gap-2">
                            <span class="w-6 flex-none text-right font-mono text-xs text-ink-subtle">{{ $posicao }}.</span>
                            <input type="text"
                                   wire:model.live.debounce.800ms="caixas.{{ $posicao }}"
                                   wire:blur="salvar"
                                   aria-label="Exemplar {{ $posicao }}"
                                   class="min-h-[44px] w-full rounded-md border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                    @endforeach
                </div>
        @endswitch
    </div>

    {{-- Contador vivo --}}
    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
        @if ($item->response_type !== ResponseType::BinaryCriteria)
            <p class="text-ink-muted">
                <span class="font-medium text-ink tabular-nums">{{ $acertos }}</span>
                de {{ $item->threshold_full }} registrados
                @if ($respondido)
                    · vale <span class="font-medium text-ink">{{ $score >= 1.0 ? '1' : ($score >= 0.5 ? '½' : '0') }}</span>
                @endif
            </p>
        @endif

        @if (! $respondido && ! $pedeConfirmacao)
            <button type="button" wire:click="confirmar('0')"
                    class="rounded-md px-2 py-1 text-xs font-medium text-ink-muted underline-offset-2 hover:text-ink hover:underline">
                Registrar como 0 ponto
            </button>
        @endif

        @if ($sobrescrito)
            <span class="rounded-full bg-warning-soft px-2 py-0.5 text-[11px] font-medium text-warning-ink">
                pontuação ajustada pelo aplicador
            </span>
        @endif
    </div>

    {{-- Marco assisted: a régua não distingue os critérios, o psicólogo decide --}}
    @if ($pedeConfirmacao)
        <div class="mt-4 rounded-md border border-warning/30 bg-warning-soft p-4">
            <p class="text-sm font-medium text-ink">
                Os dois critérios pedem a mesma quantidade. Qual foi atingido?
            </p>
            <div class="mt-3 space-y-2">
                <button type="button" wire:click="confirmar('1')"
                        class="block w-full rounded-md border border-line bg-surface p-3 text-left text-sm text-ink hover:border-success hover:bg-success-soft">
                    <span class="font-semibold">1 ponto</span> — {{ $item->criteria_full }}
                </button>
                @if ($item->criteria_half)
                    <button type="button" wire:click="confirmar('0.5')"
                            class="block w-full rounded-md border border-line bg-surface p-3 text-left text-sm text-ink hover:border-warning hover:bg-warning/10">
                        <span class="font-semibold">½ ponto</span> — {{ $item->criteria_half }}
                    </button>
                @endif
                <button type="button" wire:click="confirmar('0')"
                        class="block w-full rounded-md border border-line bg-surface p-3 text-left text-sm text-ink-muted hover:border-ink-subtle hover:bg-canvas">
                    <span class="font-semibold">0 ponto</span> — não atingiu nenhum dos critérios
                </button>
            </div>
        </div>
    @endif

    {{-- Observações e critérios do manual --}}
    <div class="mt-4 border-t border-line pt-3">
        <input type="text" wire:model.live.debounce.800ms="observacao" wire:blur="salvar"
               placeholder="Observações deste marco"
               aria-label="Observações do marco"
               class="min-h-[44px] w-full rounded-md border-transparent bg-canvas text-sm placeholder:text-ink-subtle focus:border-primary focus:bg-surface focus:ring-primary">

        <button type="button" wire:click="alternarCriterios"
                class="mt-2 flex items-center gap-1.5 text-xs font-medium text-ink-muted hover:text-ink">
            <i class="fa-solid fa-chevron-{{ $criteriosAbertos ? 'down' : 'right' }} text-[10px]" aria-hidden="true"></i>
            Critérios do manual
        </button>

        @if ($criteriosAbertos)
            <dl class="mt-2 space-y-2 rounded-md bg-canvas p-3 text-sm">
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-success-ink">1 ponto</dt>
                    <dd class="mt-0.5 text-ink-muted">{{ $item->criteria_full }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-warning-ink">½ ponto</dt>
                    <dd class="mt-0.5 text-ink-muted">{{ $item->criteria_half ?? 'Este marco não admite meio ponto.' }}</dd>
                </div>
                @if ($item->objective)
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-ink-subtle">Objetivo</dt>
                        <dd class="mt-0.5 text-ink-muted">{{ $item->objective }}</dd>
                    </div>
                @endif
            </dl>
        @endif
    </div>
</div>
