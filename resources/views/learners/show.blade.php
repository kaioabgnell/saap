<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('aprendizes.index') }}"
                   class="flex h-9 w-9 items-center justify-center rounded-md text-ink-muted hover:bg-canvas hover:text-ink"
                   aria-label="Voltar para aprendizes">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                </a>
                <h2 class="text-xl font-semibold leading-tight text-ink">{{ $learner->name }}</h2>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('aprendizes.prontuario', $learner) }}"
                   class="inline-flex min-h-[44px] items-center gap-2 rounded-md border border-line px-3 py-1.5 text-sm font-medium text-ink hover:bg-canvas">
                    <i class="fa-solid fa-folder-open text-ink-muted" aria-hidden="true"></i>
                    Prontuário
                </a>
                <a href="{{ route('aprendizes.edit', $learner) }}"
                   class="inline-flex min-h-[44px] items-center rounded-md border border-line px-3 py-1.5 text-sm font-medium text-ink hover:bg-canvas">
                    Editar
                </a>
                <button type="button" x-data=""
                        x-on:click="$dispatch('open-modal', 'excluir-aprendiz')"
                        class="inline-flex min-h-[44px] items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium text-danger hover:bg-danger-soft">
                    <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                    Excluir
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                <div class="flex items-start gap-5">
                    @if ($learner->photoUrl())
                        <img src="{{ $learner->photoUrl() }}" alt="" class="h-20 w-20 flex-none rounded-full object-cover">
                    @else
                        <span class="flex h-20 w-20 flex-none items-center justify-center rounded-full bg-primary-soft">
                            <i class="fa-solid fa-child text-2xl text-primary" aria-hidden="true"></i>
                        </span>
                    @endif

                    <dl class="grid flex-1 grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-ink-subtle">Idade</dt>
                            <dd class="mt-0.5 text-sm font-medium text-ink">{{ $learner->currentAge()->format() }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-ink-subtle">Nascimento</dt>
                            <dd class="mt-0.5 text-sm font-medium text-ink">{{ $learner->birth_date->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-ink-subtle">Telefone</dt>
                            <dd class="mt-0.5 text-sm font-medium text-ink">{{ $learner->contact_phone ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-ink-subtle">Pai</dt>
                            <dd class="mt-0.5 text-sm font-medium text-ink">{{ $learner->father_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-ink-subtle">Mãe</dt>
                            <dd class="mt-0.5 text-sm font-medium text-ink">{{ $learner->mother_name ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                @if ($learner->notes)
                    <p class="mt-5 border-t border-line pt-4 text-sm text-ink-muted">{{ $learner->notes }}</p>
                @endif
            </div>

            <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-ink">Avaliações</h3>

                    <div class="flex flex-wrap items-center gap-2">
                        {{-- Sempre disponível, de propósito: transcrever um formulário
                             de 2024 não é começar uma segunda aplicação, e uma
                             aplicação em andamento hoje não pode impedir o arquivo.
                             Ver OpenChartAssessment. --}}
                        <a href="{{ route('lancamento.create', $learner) }}"
                           class="inline-flex min-h-[44px] items-center gap-2 rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-medium text-ink hover:bg-canvas">
                            <i class="fa-solid fa-file-pen text-ink-muted" aria-hidden="true"></i>
                            Lançar avaliação manual
                        </a>

                        {{-- Uma aplicação guiada aberta por vez. A condição olha só
                             as guiadas, como o guarda de OpenAssessment. --}}
                        @unless ($learner->assessments->contains(fn ($a) => $a->status->isOpen() && ! $a->isChartEntry()))
                            <form method="POST" action="{{ route('avaliacoes.store', $learner) }}">
                                @csrf
                                <button type="submit" class="inline-flex min-h-[44px] items-center rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-hover">
                                    Nova avaliação
                                </button>
                            </form>
                        @endunless
                    </div>
                </div>

                <ul class="mt-4 divide-y divide-line">
                    @forelse ($learner->assessments as $assessment)
                        <li class="flex items-center justify-between py-3">
                            <div class="flex items-center gap-3">
                                <x-status-badge :status="$assessment->status" />
                                <span class="text-sm text-ink-muted">
                                    aplicação em {{ $assessment->applied_on->format('d/m/Y') }}
                                </span>
                            </div>
                            <a href="{{ route('avaliacoes.show', $assessment) }}" class="text-sm font-medium text-primary hover:underline">
                                Ver detalhes
                            </a>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-ink-muted">Nenhuma avaliação ainda.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    {{-- Confirmação de exclusão. A avaliação concluída já bloqueia no
         servidor (LearnerController::destroy) — aqui a tela avisa disso
         ANTES do clique, em vez de deixar a psicóloga confirmar uma
         exclusão que só vai voltar com erro. --}}
    @php $bloqueadoPorAvaliacao = $learner->hasCompletedAssessment(); @endphp
    <x-modal name="excluir-aprendiz" focusable max-width="sm">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <span class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-danger-soft">
                    <i class="fa-solid fa-triangle-exclamation text-lg text-danger" aria-hidden="true"></i>
                </span>

                @if ($bloqueadoPorAvaliacao)
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-ink">Não é possível excluir {{ $learner->name }}</h2>
                        <p class="mt-1.5 text-sm leading-relaxed text-ink-muted">
                            Este aprendiz tem uma avaliação concluída, e o laudo emitido precisa
                            continuar rastreável ao cadastro. Para preservar o histórico, o
                            cadastro não pode ser excluído.
                        </p>
                    </div>
                @else
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-ink">Excluir {{ $learner->name }}?</h2>
                        <p class="mt-1.5 text-sm leading-relaxed text-ink-muted">
                            O cadastro, os dados de contato e a foto deste aprendiz saem da sua
                            lista de aprendizes.
                            <span class="font-medium text-ink">Esta ação não pode ser desfeita por aqui.</span>
                        </p>
                    </div>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ $bloqueadoPorAvaliacao ? 'Entendi' : 'Cancelar' }}
                </x-secondary-button>

                @unless ($bloqueadoPorAvaliacao)
                    <form method="POST" action="{{ route('aprendizes.destroy', $learner) }}">
                        @csrf
                        @method('DELETE')
                        <x-danger-button type="submit">
                            <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                            Excluir aprendiz
                        </x-danger-button>
                    </form>
                @endunless
            </div>
        </div>
    </x-modal>
</x-app-layout>
