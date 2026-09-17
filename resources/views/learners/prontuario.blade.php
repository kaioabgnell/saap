<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('aprendizes.show', $learner) }}"
                   class="flex h-11 w-11 items-center justify-center rounded-md text-ink-muted hover:bg-canvas hover:text-ink"
                   aria-label="Voltar para o aprendiz">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                </a>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-ink">Prontuário</h2>
                    <p class="text-sm text-ink-muted">{{ $learner->name }}</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- 1. Identificação --}}
            <section class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                <h3 class="text-base font-semibold text-ink">Identificação</h3>

                <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-wider text-ink-subtle">Idade hoje</dt>
                        <dd class="mt-0.5 text-sm font-medium text-ink">{{ $learner->currentAge()->format() }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-wider text-ink-subtle">Nascimento</dt>
                        <dd class="mt-0.5 text-sm font-medium tabular-nums text-ink">{{ $learner->birth_date->format('d/m/Y') }}</dd>
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
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-wider text-ink-subtle">Uso de imagem</dt>
                        <dd class="mt-0.5 text-sm font-medium text-ink">
                            {{ $learner->image_consent_at
                                ? 'autorizado em '.$learner->image_consent_at->format('d/m/Y')
                                : 'sem autorização registrada' }}
                        </dd>
                    </div>
                </dl>
            </section>

            {{-- 2. Avaliações --}}
            <section class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                <h3 class="text-base font-semibold text-ink">Avaliações</h3>

                <ul class="mt-3 divide-y divide-line">
                    @forelse ($learner->assessments as $assessment)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div class="flex items-center gap-3">
                                <x-status-badge :status="$assessment->status" />
                                <span class="text-sm tabular-nums text-ink-muted">
                                    aplicação em {{ $assessment->applied_on->format('d/m/Y') }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                @if ($assessment->isLocked())
                                    <a href="{{ route('avaliacoes.relatorio', $assessment) }}"
                                       class="inline-flex min-h-[44px] items-center text-sm font-medium text-primary hover:underline">Laudo</a>
                                @endif
                                <a href="{{ route('avaliacoes.show', $assessment) }}"
                                   class="inline-flex min-h-[44px] items-center text-sm font-medium text-primary hover:underline">Ver detalhes</a>
                            </div>
                        </li>
                    @empty
                        {{-- Um aprendiz que só fez anamnese tem prontuário; esta
                             seção simplesmente aparece vazia. --}}
                        <li class="py-6 text-center text-sm text-ink-muted">Nenhuma avaliação registrada.</li>
                    @endforelse
                </ul>
            </section>

            {{-- 3. Linha do tempo de atendimentos --}}
            <section class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-ink">Atendimentos</h3>
                    <a href="{{ route('agenda') }}"
                       class="inline-flex min-h-[44px] items-center text-sm font-medium text-primary hover:underline">Ir para a agenda</a>
                </div>

                @if ($learner->appointments->isEmpty())
                    <p class="py-6 text-center text-sm text-ink-muted">Nenhum atendimento registrado.</p>
                @else
                    <ol class="mt-4 space-y-5">
                        @foreach ($learner->appointments as $atendimento)
                            @php
                                $apagado = in_array($atendimento->status->value, ['cancelled', 'no_show'], true);
                            @endphp

                            <li wire:key="at-{{ $atendimento->id }}"
                                class="border-l-2 ps-4 {{ $apagado ? 'border-line' : 'border-primary/30' }}">

                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span class="text-sm font-medium tabular-nums {{ $apagado ? 'text-ink-subtle' : 'text-ink' }}">
                                        {{ $atendimento->starts_at->format('d/m/Y') }}
                                        às {{ $atendimento->starts_at->format('H:i') }}
                                    </span>
                                    <x-appointment-status :status="$atendimento->status" />

                                    @if ($atendimento->duracaoReal() !== null)
                                        <span class="text-xs tabular-nums text-ink-subtle">
                                            {{ $atendimento->duracaoReal() }} min
                                            ({{ $atendimento->checked_in_at->format('H:i') }}–{{ $atendimento->checked_out_at->format('H:i') }})
                                        </span>
                                    @endif
                                </div>

                                @if ($atendimento->cancel_reason)
                                    <p class="mt-1 text-sm text-ink-muted">
                                        Motivo: {{ $atendimento->cancel_reason }}
                                    </p>
                                @endif

                                @if ($atendimento->temRegistro())
                                    <p class="mt-2 whitespace-pre-wrap rounded-md bg-canvas px-3 py-2 text-sm leading-relaxed text-ink">{{ $atendimento->notes }}</p>
                                @elseif ($atendimento->status === \App\Domain\Schedule\AppointmentStatus::Completed)
                                    <p class="mt-2 text-sm italic text-ink-subtle">Concluído sem registro escrito.</p>
                                @endif

                                @foreach ($atendimento->addenda as $adendo)
                                    <div wire:key="ad-{{ $adendo->id }}" class="mt-2 rounded-md border border-line px-3 py-2">
                                        <p class="text-[11px] font-medium uppercase tracking-wider text-ink-subtle">
                                            Aditamento · {{ $adendo->created_at->format('d/m/Y \à\s H:i') }}
                                        </p>
                                        <p class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-ink">{{ $adendo->body }}</p>
                                    </div>
                                @endforeach

                                @if ($atendimento->status === \App\Domain\Schedule\AppointmentStatus::InProgress)
                                    <a href="{{ route('atendimentos.show', $atendimento) }}"
                                       class="mt-2 inline-flex min-h-[44px] items-center text-sm font-medium text-primary hover:underline">
                                        Abrir atendimento em andamento
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
