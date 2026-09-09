@php
    $avaliacao = $this->assessment;
    $aprendiz = $avaliacao->learner;
@endphp

<div style="padding-bottom: calc(7rem + env(safe-area-inset-bottom));">

    @include('pdf.relatorio._estilo')

    {{-- Ajustes que valem só nesta tela. O gráfico do laudo tem 13px de
         célula, dimensionado para o A4; aqui a célula é alvo de clique e
         precisa de 48px — 24 por metade. --}}
    <style>
        .grade-lancamento .grafico-marcos { width: 100%; table-layout: fixed; min-width: 560px; }
        .grade-lancamento .grafico-marcos .celula { width: auto; height: 24px; }
        .grade-lancamento .grafico-marcos .eixo { width: 30px; font-size: 11px; }
        .grade-lancamento .grafico-marcos .rotulo-area { font-size: 10px; padding-top: 8px; }

        /* No laudo, "pendente" e "zero" quase não se distinguem — e não
           precisam, porque laudo emitido não tem pendente. Aqui a diferença é
           o próprio trabalho: o pontilhado diz "ainda não transcrevi", o
           branco diz "transcrevi, e foi zero". */
        .grade-lancamento .grafico-marcos .celula.pendente {
            background-color: #FFFFFF;
            background-image: radial-gradient(#CBD5E1 1px, transparent 1px);
            background-size: 6px 6px;
        }

        .grade-lancamento .grafico-marcos .celula { cursor: pointer; }

        .grade-lancamento .grafico-marcos .celula:hover { box-shadow: inset 0 0 0 2px #4338CA; }
        .grade-lancamento .grafico-marcos .celula:focus-visible {
            outline: none;
            box-shadow: inset 0 0 0 2px #4338CA;
        }

        /* A legenda do PDF tem 8px, dimensionada para o A4. Aqui ela é o que
           explica a diferença entre "0 ponto" e "ainda não marcado" — a
           distinção mais importante da tela. */
        .grade-lancamento-legenda { font-size: 11px; }
        .grade-lancamento-legenda span { margin-right: 16px; }
        .grade-lancamento-legenda i { width: 11px; height: 11px; }

        .legenda i.pontilhada {
            background-color: #FFFFFF;
            background-image: radial-gradient(#CBD5E1 1px, transparent 1px);
            background-size: 6px 6px;
        }
    </style>

    {{-- Identificação --}}
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
                        <p class="text-xs text-ink-muted">{{ $aprendiz->ageAt($avaliacao->applied_on)->format() }} na aplicação</p>
                    </div>
                </div>

                <div>
                    <p class="text-[10px] font-medium uppercase tracking-wider text-ink-subtle">Aplicado em papel</p>
                    <p class="text-sm text-ink">{{ $avaliacao->applied_on->format('d/m/Y') }}</p>
                </div>

                <span class="rounded-full bg-warning-soft px-3 py-1 text-xs font-medium text-warning-ink">
                    <i class="fa-solid fa-file-pen" aria-hidden="true"></i> Lançamento retroativo
                </span>

                <div class="ml-auto flex items-center gap-3">
                    @if ($salvoEm)
                        <span class="text-xs text-ink-muted" wire:loading.remove wire:target="marcar,definir,limparNivel">
                            <i class="fa-solid fa-check text-success-ink" aria-hidden="true"></i> Salvo às {{ $salvoEm }}
                        </span>
                    @endif
                    <span class="text-xs text-ink-muted" wire:loading wire:target="marcar,definir,limparNivel">
                        <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i> Salvando…
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

        @if ($erro)
            <p class="rounded-md bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ $erro }}
            </p>
        @endif

        <div class="rounded-lg border border-line bg-surface p-5 shadow-sm">
            <h1 class="text-base font-semibold text-ink">Lançar resultado do formulário em papel</h1>
            <p class="mt-1 text-sm text-ink-muted">
                Clique na metade de baixo da célula para <strong>½ ponto</strong> e na metade de
                cima para <strong>1 ponto</strong>. Clicar embaixo de novo registra
                <strong>0</strong>. Com a célula em foco, as teclas
                <kbd class="rounded border border-line px-1 text-xs">1</kbd>,
                <kbd class="rounded border border-line px-1 text-xs">5</kbd> e
                <kbd class="rounded border border-line px-1 text-xs">0</kbd> fazem o mesmo, e as
                setas andam pela grade.
            </p>
            <p class="mt-2 text-sm text-ink-muted">
                Célula que ficar em branco será registrada como 0 ponto na conclusão —
                o número aparece antes, na confirmação.
            </p>
        </div>

        {{-- Uma grade por nível lançado. O escopo do tooltip é o de fora; o do
             teclado e do popover de toque é o de dentro. --}}
        @foreach ($this->grades as $nivel => $grade)
            <div class="rounded-lg border border-line bg-surface p-6 shadow-sm" wire:key="grade-{{ $nivel }}">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <h2 class="text-base font-semibold text-ink">Nível {{ $nivel }}</h2>
                    <div class="flex items-center gap-4">
                        <p class="text-sm tabular-nums text-ink-muted">
                            {{ $grade->markedCount() }} de {{ $grade->total() }} marcados ·
                            <span class="font-medium text-ink">{{ str_replace('.', ',', (string) $grade->scoreTotal()) }} pontos</span>
                        </p>
                        @if ($grade->markedCount() > 0)
                            <button type="button"
                                    wire:click="limparNivel({{ $nivel }})"
                                    wire:confirm="Apagar todas as marcações do nível {{ $nivel }}?"
                                    class="min-h-[44px] rounded-md px-2 text-sm font-medium text-ink-muted hover:text-danger">
                                Limpar nível
                            </button>
                        @endif
                    </div>
                </div>

                <div class="relative mt-4" x-data="graficoMarcos">
                    <div class="grade-lancamento overflow-x-auto" x-data="gradeMarcos" x-on:click.outside="fecharPopover()">
                        <x-vbmapp.milestone-grid :grade="$grade" />

                        {{-- Ponteiro grosso: meia célula tem 24px, os alvos de
                             verdade ficam aqui. --}}
                        <template x-if="popover">
                            <div class="fixed z-40 flex gap-1 rounded-lg border border-line bg-surface p-1 shadow-xl"
                                 x-bind:style="`left:${popover.x}px; top:${popover.y}px`"
                                 role="group" aria-label="Pontuação do marco">
                                <button type="button" x-on:click="escolher(1)"
                                        class="h-11 w-11 rounded-md bg-canvas text-sm font-semibold text-ink hover:bg-primary-soft">1</button>
                                <button type="button" x-on:click="escolher(0.5)" x-bind:disabled="! popover.temMeioPonto"
                                        class="h-11 w-11 rounded-md bg-canvas text-sm font-semibold text-ink hover:bg-primary-soft disabled:opacity-40">½</button>
                                <button type="button" x-on:click="escolher(0)"
                                        class="h-11 w-11 rounded-md bg-canvas text-sm font-semibold text-ink hover:bg-primary-soft">0</button>
                                <button type="button" x-on:click="escolher(null)" aria-label="Limpar marcação"
                                        class="h-11 w-11 rounded-md text-ink-muted hover:text-danger">
                                    <i class="fa-solid fa-eraser" aria-hidden="true"></i>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div x-show="aberto" x-cloak
                         x-bind:style="`left:${x}px; top:${y}px`"
                         class="pointer-events-none fixed z-50 w-[19rem] max-w-[calc(100vw-2rem)] rounded-lg border border-line bg-surface p-3 text-left shadow-xl"
                         role="tooltip">
                        <p class="text-[11px] font-medium uppercase tracking-wider text-ink-subtle" x-text="dados.area"></p>
                        <p class="mt-0.5 text-[11px] text-ink-muted">
                            Marco <span x-text="dados.position"></span> ·
                            <span class="font-mono text-primary" x-text="dados.code"></span>
                        </p>
                        <p class="mt-1.5 text-sm leading-snug text-ink" x-text="dados.statement"></p>
                        <p class="mt-2 border-t border-line pt-2 text-xs">
                            <span class="font-semibold text-ink">Marcado:</span>
                            <span class="text-ink-muted" x-text="dados.score"></span>
                        </p>
                        <p class="mt-1 text-xs text-warning-ink" x-show="dados.answer" x-text="dados.answer"></p>
                    </div>

                    <div class="legenda grade-lancamento-legenda mt-3">
                        <span><i class="cheia"></i>1 ponto</span>
                        <span><i class="meia"><b></b></i>½ ponto</span>
                        <span><i class="vazia"></i>0 ponto</span>
                        <span><i class="pontilhada"></i>ainda não marcado</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Barra de conclusão --}}
    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-surface/95 backdrop-blur"
         style="padding-bottom: env(safe-area-inset-bottom);">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
            <div>
                <p class="text-sm font-medium text-ink">
                    {{ str_replace('.', ',', (string) $this->pontuacaoTotal) }}
                    {{ $this->pontuacaoTotal === 1.0 ? 'ponto lançado' : 'pontos lançados' }}
                </p>
                <p class="text-xs text-ink-muted">
                    @if ($this->pendentes > 0)
                        {{ $this->pendentes }} {{ $this->pendentes === 1 ? 'marco ficará' : 'marcos ficarão' }} com 0 ponto
                    @else
                        Todos os marcos foram marcados
                    @endif
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('avaliacoes.show', $avaliacao) }}"
                   class="min-h-[44px] rounded-md px-3 py-2 text-sm font-medium text-ink-muted hover:text-ink">
                    Sair sem concluir
                </a>
                <button type="button" wire:click="confirmarConclusao"
                        class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                    <i class="fa-solid fa-flag-checkered" aria-hidden="true"></i>
                    Concluir e gerar relatório
                </button>
            </div>
        </div>
    </div>

    {{-- Modal de conclusão.

         Teleportado para o body: o cabeçalho e a barra desta tela usam
         `backdrop-blur`, e `backdrop-filter` cria bloco de contenção para
         descendentes `position: fixed` — o modal nasceria preso dentro da
         barra, no rodapé. --}}
    @if ($confirmandoConclusao)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4" role="dialog" aria-modal="true">
                <div class="w-full max-w-lg rounded-lg bg-surface p-6 shadow-xl">
                    <h2 class="text-lg font-semibold text-ink">Concluir lançamento</h2>

                    @if ($this->pendentes > 0)
                        <p class="mt-2 text-sm text-ink">
                            {{ $this->pendentes === 1 ? 'O marco ainda não marcado será registrado' : 'Os '.$this->pendentes.' marcos ainda não marcados serão registrados' }}
                            como <strong>0 ponto</strong>.
                        </p>
                    @endif

                    <p class="mt-2 text-sm text-ink">
                        Depois de concluída, <strong>nenhuma pontuação pode ser alterada</strong>.
                        O relatório será gerado e ficará disponível permanentemente.
                    </p>

                    <dl class="mt-4 space-y-1 rounded-md bg-canvas p-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">Aprendiz</dt>
                            <dd class="text-right font-medium text-ink">
                                {{ $aprendiz->name }} — {{ $aprendiz->ageAt($avaliacao->applied_on)->format() }} na data da aplicação
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">Aplicação</dt>
                            <dd class="text-right font-medium text-ink">{{ $avaliacao->applied_on->format('d/m/Y') }} (em papel)</dd>
                        </div>
                        @foreach ($this->grades as $nivel => $grade)
                            <div class="flex justify-between gap-4">
                                <dt class="text-ink-muted">Nível {{ $nivel }}</dt>
                                <dd class="text-right font-medium tabular-nums text-ink">
                                    {{ $grade->markedCount() }} marcados, {{ $grade->pendingCount() }} em zero
                                </dd>
                            </div>
                        @endforeach
                        <div class="flex justify-between gap-4 border-t border-line pt-1">
                            <dt class="text-ink-muted">Pontuação</dt>
                            <dd class="text-right font-semibold tabular-nums text-ink">
                                {{ str_replace('.', ',', (string) $this->pontuacaoTotal) }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('confirmandoConclusao', false)"
                                class="min-h-[44px] rounded-md px-4 py-2 text-sm font-medium text-ink-muted hover:text-ink">
                            Voltar
                        </button>
                        <button type="button" wire:click="concluir"
                                class="min-h-[44px] rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                            Concluir lançamento
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
