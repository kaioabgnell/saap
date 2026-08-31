<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-ink">Painel</h2>
            <a href="{{ route('aprendizes.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Novo aprendiz
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

            @if ($learners->isEmpty())
                <div class="rounded-lg border border-dashed border-line bg-surface p-10 text-center">
                    <i class="fa-solid fa-user-plus text-3xl text-ink-subtle" aria-hidden="true"></i>
                    <p class="mt-3 text-sm text-ink-muted">Nenhum aprendiz cadastrado ainda.</p>
                    <a href="{{ route('aprendizes.create') }}" class="mt-4 inline-block text-sm font-medium text-primary hover:underline">
                        Cadastrar o primeiro aprendiz
                    </a>
                </div>
            @endif

            @foreach ($learners as $learner)
                @php
                    $avaliacaoAberta = $learner->assessments->first(fn ($a) => $a->status->isOpen());
                    $ultimaConcluida = $learner->assessments->firstWhere('status', \App\Domain\Assessment\AssessmentStatus::Completed);
                @endphp

                <div class="rounded-lg border border-line bg-surface p-5 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <a href="{{ route('aprendizes.show', $learner) }}" class="flex items-center gap-4 min-w-0">
                            @if ($learner->photoUrl('miniatura'))
                                <img src="{{ $learner->photoUrl('miniatura') }}" alt="" class="h-12 w-12 flex-none rounded-full object-cover">
                            @else
                                <span class="flex h-12 w-12 flex-none items-center justify-center rounded-full bg-primary-soft">
                                    <i class="fa-solid fa-child text-primary" aria-hidden="true"></i>
                                </span>
                            @endif
                            <div class="min-w-0">
                                <p class="truncate font-medium text-ink">{{ $learner->name }}</p>
                                <p class="text-sm text-ink-muted">{{ $learner->currentAge()->format() }}</p>
                            </div>
                        </a>

                        <div class="flex flex-none items-center gap-3">
                            @if ($avaliacaoAberta)
                                <x-status-badge :status="$avaliacaoAberta->status" />
                                <span class="text-xs text-ink-subtle">
                                    aberta desde {{ $avaliacaoAberta->created_at->diffForHumans() }}
                                </span>
                                <a href="{{ route('avaliacoes.show', $avaliacaoAberta) }}"
                                   class="inline-flex min-h-[44px] items-center rounded-md border border-line px-3 py-1.5 text-sm font-medium text-ink hover:bg-canvas">
                                    Continuar
                                </a>
                            @elseif ($ultimaConcluida)
                                <x-status-badge :status="$ultimaConcluida->status" />
                                <a href="{{ route('avaliacoes.show', $ultimaConcluida) }}"
                                   class="inline-flex min-h-[44px] items-center rounded-md border border-line px-3 py-1.5 text-sm font-medium text-ink hover:bg-canvas">
                                    Ver avaliação
                                </a>
                                <form method="POST" action="{{ route('avaliacoes.store', $learner) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-[44px] items-center rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-hover">
                                        Nova avaliação
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('avaliacoes.store', $learner) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-[44px] items-center rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-hover">
                                        Iniciar avaliação
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
