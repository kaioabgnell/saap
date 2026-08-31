@php
    $avaliacao = $this->assessment;
    $concluida = $avaliacao->isLocked();
    $cancelada = $avaliacao->status === \App\Domain\Assessment\AssessmentStatus::Cancelled;
@endphp

<div>
    @if ($erro)
        <p class="mb-4 rounded-md bg-danger-soft px-4 py-3 text-sm text-danger">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ $erro }}
        </p>
    @endif

    @if ($concluida)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-success/30 bg-success-soft p-5">
            <div>
                <p class="font-medium text-ink">
                    <i class="fa-solid fa-lock text-success-ink" aria-hidden="true"></i>
                    Avaliação concluída em {{ $avaliacao->completed_at->format('d/m/Y') }}
                </p>
                <p class="mt-0.5 text-sm text-ink-muted">As respostas não podem mais ser alteradas.</p>
            </div>
            <a href="{{ route('avaliacoes.relatorio', $avaliacao) }}"
               class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                Ver relatório
            </a>
        </div>

    @elseif ($cancelada)
        <div class="rounded-lg border border-line bg-canvas p-5">
            <p class="font-medium text-ink-muted">
                <i class="fa-solid fa-ban" aria-hidden="true"></i>
                Avaliação cancelada em {{ $avaliacao->cancelled_at->format('d/m/Y') }}
            </p>
            @if ($avaliacao->cancel_reason)
                <p class="mt-1 text-sm text-ink-muted">Motivo: {{ $avaliacao->cancel_reason }}</p>
            @endif
            <p class="mt-2 text-xs text-ink-subtle">As respostas registradas foram preservadas.</p>
        </div>

    @else
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line bg-surface p-5 shadow-sm">
            <div>
                @if ($this->podeConcluir)
                    <p class="font-medium text-ink">Todos os níveis iniciados estão completos.</p>
                    <p class="mt-0.5 text-sm text-ink-muted">
                        Pontuação total: <span class="font-medium tabular-nums text-ink">{{ str_replace('.', ',', (string) $this->pontuacaoTotal) }}</span>
                    </p>
                @else
                    <p class="font-medium text-ink">Avaliação em andamento</p>
                    <p class="mt-0.5 text-sm text-ink-muted">
                        Conclua todos os níveis iniciados para poder fechar a avaliação.
                    </p>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <button type="button" wire:click="confirmarCancelamento"
                        class="min-h-[44px] rounded-md px-3 py-2 text-sm font-medium text-ink-muted hover:text-danger">
                    Cancelar avaliação
                </button>

                @if ($this->podeConcluir)
                    <button type="button" wire:click="confirmarConclusao"
                            class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                        <i class="fa-solid fa-flag-checkered" aria-hidden="true"></i>
                        Concluir avaliação
                    </button>
                @endif
            </div>
        </div>
    @endif

    {{-- Modal de conclusão: a consequência dita sem eufemismo --}}
    @if ($confirmandoConclusao)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-ink/40 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-lg rounded-lg bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">Concluir avaliação</h2>

                <p class="mt-2 text-sm text-ink">
                    Depois de concluída, <strong>nenhuma resposta pode ser alterada</strong>.
                    O relatório será gerado e ficará disponível permanentemente.
                </p>

                <dl class="mt-4 space-y-1 rounded-md bg-canvas p-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-muted">Aprendiz</dt>
                        <dd class="text-right font-medium text-ink">
                            {{ $avaliacao->learner->name }} — {{ $avaliacao->learner->ageAt($avaliacao->applied_on)->format() }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-muted">Aplicação</dt>
                        <dd class="text-right font-medium text-ink">{{ $avaliacao->applied_on->format('d/m/Y') }}</dd>
                    </div>
                    @foreach ($avaliacao->levels->sortBy('level') as $nivel)
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">Nível {{ $nivel->level }}</dt>
                            <dd class="text-right font-medium tabular-nums text-ink">
                                {{ $nivel->answered_count }} de {{ $nivel->total_count }}
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
                        Concluir avaliação
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de cancelamento --}}
    @if ($confirmandoCancelamento)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-ink/40 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-lg bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">Cancelar avaliação</h2>
                <p class="mt-2 text-sm text-ink-muted">
                    As respostas já registradas serão preservadas, mas a avaliação
                    fica somente leitura e não gera relatório.
                </p>

                <label for="motivo" class="mt-4 block text-sm font-medium text-ink">Motivo</label>
                <textarea id="motivo" wire:model="motivoCancelamento" rows="3"
                          class="mt-1 block w-full rounded-md border-line text-sm focus:border-primary focus:ring-primary"
                          placeholder="Ex.: aprendiz desligado da clínica"></textarea>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" wire:click="$set('confirmandoCancelamento', false)"
                            class="min-h-[44px] rounded-md px-4 py-2 text-sm font-medium text-ink-muted hover:text-ink">
                        Voltar
                    </button>
                    <button type="button" wire:click="cancelar"
                            class="min-h-[44px] rounded-md bg-danger px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                        Cancelar avaliação
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
