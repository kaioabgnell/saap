<div>
    <button type="button" wire:click="abrir"
            class="inline-flex min-h-[44px] items-center gap-2 rounded-md border border-line bg-surface px-3 py-2 text-sm font-medium text-ink hover:bg-canvas">
        <i class="fa-solid fa-print" aria-hidden="true"></i>
        Imprimir formulário
    </button>

    {{-- O modal PRECISA sair daqui para o <body>.
         Este componente é renderizado dentro da barra de ações fixa do rodapé,
         que tem `backdrop-blur`. `backdrop-filter` cria bloco de contenção para
         descendentes `position: fixed` — o `inset-0` passa a valer contra a
         caixa da barra, não contra a janela — e cria stacking context próprio,
         que aprisiona o z-40 dentro do z-20 da barra. O resultado é o modal
         espremido na faixa do rodapé e por trás do resto da tela.
         Mexer no z-index não resolve: o problema é o bloco de contenção. --}}
    @if ($aberto)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4"
                 x-on:keydown.escape.window="$wire.fechar()"
                 role="dialog" aria-modal="true" aria-label="Imprimir formulário do nível {{ $level }}">
            <div class="w-full max-w-md rounded-lg bg-surface p-6 shadow-xl">

                @if ($status === 'idle')
                    <h2 class="text-lg font-semibold text-ink">Imprimir formulário — Nível {{ $level }}</h2>
                    <p class="mt-1 text-sm text-ink-muted">
                        Gera um PDF com o que já foi respondido. Marcado como rascunho —
                        não é o relatório final.
                    </p>

                    <div class="mt-4 space-y-3">
                        <label class="flex items-start gap-2">
                            <input type="checkbox" wire:model="somentePendentes" class="mt-0.5 rounded border-line text-primary focus:ring-primary">
                            <span class="text-sm text-ink">Somente não respondidas</span>
                        </label>
                        <label class="flex items-start gap-2">
                            <input type="checkbox" wire:model="incluirCriterios" class="mt-0.5 rounded border-line text-primary focus:ring-primary">
                            <span class="text-sm text-ink">Incluir critérios do manual</span>
                        </label>
                        <label class="flex items-start gap-2">
                            <input type="checkbox" wire:model="incluirExemplares" class="mt-0.5 rounded border-line text-primary focus:ring-primary">
                            <span class="text-sm text-ink">Incluir exemplares registrados</span>
                        </label>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button type="button" wire:click="fechar" class="min-h-[44px] rounded-md px-4 py-2 text-sm font-medium text-ink-muted hover:text-ink">
                            Cancelar
                        </button>
                        <button type="button" wire:click="gerar" class="min-h-[44px] rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                            Gerar PDF
                        </button>
                    </div>

                @elseif ($status === 'queued')
                    <div wire:poll.1500ms="verificarStatus" class="py-6 text-center">
                        <i class="fa-solid fa-circle-notch fa-spin text-2xl text-primary" aria-hidden="true"></i>
                        <p class="mt-3 text-sm text-ink-muted">Gerando formulário…</p>
                    </div>

                @elseif ($status === 'ready')
                    <div class="py-4 text-center">
                        <i class="fa-solid fa-circle-check text-3xl text-success-ink" aria-hidden="true"></i>
                        <p class="mt-3 text-sm font-medium text-ink">Formulário pronto.</p>
                        <a href="{{ route('avaliacoes.formulario', ['assessment' => $assessmentId, 'token' => $token]) }}"
                           target="_blank"
                           class="mt-4 inline-flex min-h-[44px] items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                            <i class="fa-solid fa-download" aria-hidden="true"></i>
                            Baixar PDF
                        </a>
                        <button type="button" wire:click="fechar" class="mt-3 block w-full text-sm font-medium text-ink-muted hover:text-ink">
                            Fechar
                        </button>
                    </div>

                @elseif ($status === 'failed')
                    <div class="py-4 text-center">
                        <i class="fa-solid fa-triangle-exclamation text-3xl text-danger" aria-hidden="true"></i>
                        <p class="mt-3 text-sm font-medium text-ink">Não foi possível gerar o formulário.</p>
                        @if ($erro)
                            <p class="mt-1 text-xs text-ink-muted">{{ $erro }}</p>
                        @endif
                        <button type="button" wire:click="gerar" class="mt-4 min-h-[44px] rounded-md border border-line px-4 py-2 text-sm font-medium text-ink hover:bg-canvas">
                            Tentar novamente
                        </button>
                    </div>
                @endif
                </div>
            </div>
        @endteleport
    @endif
</div>
