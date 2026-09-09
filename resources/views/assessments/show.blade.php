@use('App\Domain\Vbmapp\Progress\ProgressCounter')
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-ink">Avaliação VB-MAPP</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Cabeçalho de identificação --}}
            <div class="flex flex-wrap items-center gap-x-8 gap-y-3 rounded-lg border border-line bg-surface p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    @if ($assessment->learner->photoUrl('miniatura'))
                        <img src="{{ $assessment->learner->photoUrl('miniatura') }}" alt="" class="h-10 w-10 rounded-full object-cover">
                    @else
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-soft">
                            <i class="fa-solid fa-child text-primary" aria-hidden="true"></i>
                        </span>
                    @endif
                    <div>
                        <p class="font-medium text-ink">{{ $assessment->learner->name }}</p>
                        <p class="text-xs text-ink-muted">{{ $assessment->learner->ageAt($assessment->applied_on)->format() }}</p>
                    </div>
                </div>

                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wider text-ink-subtle">Data da aplicação</p>
                    <p class="text-sm font-medium text-ink">{{ $assessment->applied_on->format('d/m/Y') }}</p>
                </div>

                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wider text-ink-subtle">Aplicador</p>
                    <p class="text-sm font-medium text-ink">{{ $assessment->user->name }}</p>
                </div>

                <div class="ml-auto">
                    <x-status-badge :status="$assessment->status" />
                </div>
            </div>

            {{-- Conclusão / cancelamento --}}
            <livewire:assessment.complete-assessment-panel :assessment="$assessment" />

            {{-- Níveis. Não aparece na transcrição: lá não há tela de nível para
                 abrir, e o progresso se lê no próprio gráfico de lançamento. --}}
            @unless ($assessment->isChartEntry())
            <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                <h3 class="text-base font-semibold text-ink">Níveis</h3>
                <p class="mt-1 text-sm text-ink-muted">
                    Os níveis são independentes: comece por qualquer um deles.
                </p>

                <ul class="mt-4 space-y-3">
                    @foreach ([1, 2, 3] as $n)
                        @php
                            $nivel = $assessment->levels->firstWhere('level', $n);
                            $total = ProgressCounter::totalForLevel($n);
                            $feitos = $nivel?->answered_count ?? 0;
                            $percentual = $total === 0 ? 0 : (int) round($feitos / $total * 100);
                        @endphp
                        <li class="flex flex-wrap items-center gap-4 rounded-md border border-line p-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="font-medium text-ink">Nível {{ $n }}</p>
                                    @if ($nivel?->status?->value === 'completed')
                                        <span class="rounded-full bg-success-soft px-2 py-0.5 text-[11px] font-medium text-success-ink">Concluído</span>
                                    @elseif ($nivel)
                                        <span class="rounded-full bg-primary-soft px-2 py-0.5 text-[11px] font-medium text-primary">Em andamento</span>
                                    @else
                                        <span class="rounded-full bg-canvas px-2 py-0.5 text-[11px] font-medium text-ink-muted">Não iniciado</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm tabular-nums text-ink-muted">{{ $feitos }} de {{ $total }} marcos</p>
                                @if ($nivel)
                                    <div class="mt-2 h-1.5 w-full max-w-xs overflow-hidden rounded-full bg-canvas">
                                        <div class="h-full rounded-full bg-primary" style="width: {{ $percentual }}%"></div>
                                    </div>
                                @endif
                            </div>

                            <a href="{{ route('avaliacoes.nivel', ['assessment' => $assessment, 'level' => $n]) }}"
                               class="inline-flex min-h-[44px] flex-none items-center rounded-md px-4 py-2 text-sm font-medium
                                      {{ $nivel ? 'border border-line bg-surface text-ink hover:bg-canvas' : 'bg-primary text-white hover:bg-primary-hover' }}">
                                {{ $nivel ? 'Continuar' : 'Iniciar nível '.$n }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            @endunless

            <div class="text-center">
                <a href="{{ route('aprendizes.show', $assessment->learner) }}" class="text-sm font-medium text-primary hover:underline">
                    Voltar para {{ $assessment->learner->name }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
