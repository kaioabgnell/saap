<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-ink">{{ $learner->name }}</h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('aprendizes.edit', $learner) }}"
                   class="inline-flex min-h-[44px] items-center rounded-md border border-line px-3 py-1.5 text-sm font-medium text-ink hover:bg-canvas">
                    Editar
                </a>
                <form method="POST" action="{{ route('aprendizes.destroy', $learner) }}"
                      onsubmit="return confirm('Excluir {{ $learner->name }}? Esta ação não pode ser desfeita.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex min-h-[44px] items-center rounded-md px-3 py-1.5 text-sm font-medium text-danger hover:bg-danger-soft">
                        Excluir
                    </button>
                </form>
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
                            Lançar avaliação em papel
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
</x-app-layout>
