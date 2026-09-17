@php $a = $this->atendimento; @endphp

<div class="py-8">
    <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('agenda') }}" wire:navigate
               class="inline-flex min-h-[44px] items-center gap-2 rounded-md px-2 text-sm font-medium text-ink-muted hover:bg-canvas hover:text-ink">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Voltar para a agenda
            </a>
            <a href="{{ route('aprendizes.prontuario', $a->learner) }}" wire:navigate
               class="inline-flex min-h-[44px] items-center gap-2 rounded-md px-2 text-sm font-medium text-primary hover:bg-primary-soft">
                <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                Prontuário
            </a>
        </div>

        {{-- Cabeçalho do atendimento --}}
        <div class="rounded-lg border border-line bg-surface p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="truncate text-xl font-semibold text-ink">{{ $a->learner->name }}</h2>
                    <p class="mt-1 text-sm text-ink-muted">
                        {{ \App\Domain\Schedule\CalendarLabels::porExtenso($a->starts_at) }} de
                        {{ $a->starts_at->format('Y') }},
                        {{ $a->starts_at->format('H:i') }}–{{ $a->ends_at->format('H:i') }}
                    </p>
                </div>
                <x-appointment-status :status="$a->status" />
            </div>

            <dl class="mt-4 grid gap-4 border-t border-line pt-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-ink-subtle">Check-in</dt>
                    <dd class="mt-0.5 font-medium tabular-nums text-ink">
                        {{ $a->checked_in_at?->format('H:i') ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-ink-subtle">Check-out</dt>
                    <dd class="mt-0.5 font-medium tabular-nums text-ink">
                        {{ $a->checked_out_at?->format('H:i') ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-ink-subtle">Duração real</dt>
                    <dd class="mt-0.5 font-medium tabular-nums text-ink">
                        {{ $a->duracaoReal() !== null ? $a->duracaoReal().' min' : '—' }}
                    </dd>
                </div>
            </dl>

            @if ($a->booking_note)
                <p class="mt-4 rounded-md bg-canvas px-3 py-2 text-sm text-ink-muted">
                    <span class="font-medium text-ink">Recado da marcação:</span> {{ $a->booking_note }}
                </p>
            @endif
        </div>

        @if ($erro)
            <p class="flex items-start gap-2 rounded-md border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">
                <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
                <span>{{ $erro }}</span>
            </p>
        @endif

        {{-- O registro escrito --}}
        <div class="rounded-lg border border-line bg-surface p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h3 class="font-semibold text-ink">Registro da sessão</h3>
                @if ($salvoEm)
                    <span class="text-xs text-ink-subtle" aria-live="polite">Salvo às {{ $salvoEm }}</span>
                @endif
            </div>

            @if ($emAndamento)
                <p class="mt-1 text-sm text-ink-muted">
                    Resumo livre do que foi conversado. Pode ser anamnese, devolutiva, intervenção
                    ou a aplicação do VB-MAPP — o campo é o mesmo.
                </p>

                <textarea wire:model.live.debounce.1000ms="registro" wire:blur="salvar"
                          rows="12" aria-label="Registro da sessão"
                          class="mt-3 block w-full rounded-md border-line text-sm leading-relaxed shadow-sm focus:border-primary focus:ring-primary"></textarea>

                <p class="mt-2 text-xs text-ink-subtle">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    No check-out este texto é fechado. Depois disso, a correção se faz por aditamento datado.
                </p>
            @else
                <div class="mt-3 whitespace-pre-wrap rounded-md bg-canvas px-4 py-3 text-sm leading-relaxed text-ink">
                    {{ $a->notes ?: 'Atendimento concluído sem registro escrito.' }}
                </div>
                @if ($a->notes_locked_at)
                    <p class="mt-2 text-xs text-ink-subtle">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        Fechado em {{ $a->notes_locked_at->format('d/m/Y \à\s H:i') }}.
                    </p>
                @endif
            @endif
        </div>

        {{-- Aditamentos --}}
        @if ($a->addenda->isNotEmpty() || $a->registroFechado())
            <div class="rounded-lg border border-line bg-surface p-5 shadow-sm">
                <h3 class="font-semibold text-ink">Aditamentos</h3>

                @if ($a->addenda->isEmpty())
                    <p class="mt-1 text-sm text-ink-muted">Nenhum acréscimo a este registro.</p>
                @else
                    <ol class="mt-3 space-y-3">
                        @foreach ($a->addenda as $adendo)
                            <li wire:key="ad-{{ $adendo->id }}" class="border-l-2 border-primary/30 ps-3">
                                <p class="text-xs tabular-nums text-ink-subtle">
                                    {{ $adendo->created_at->format('d/m/Y \à\s H:i') }}
                                </p>
                                <p class="mt-0.5 whitespace-pre-wrap text-sm leading-relaxed text-ink">{{ $adendo->body }}</p>
                            </li>
                        @endforeach
                    </ol>
                @endif

                @can('addendum', $a)
                    <div class="mt-4 border-t border-line pt-4">
                        <label for="aditamento" class="block text-sm font-medium text-ink">Acrescentar ao registro</label>
                        <textarea id="aditamento" wire:model="aditamento" rows="3"
                                  placeholder="O texto original não é alterado — este acréscimo entra datado, abaixo dele."
                                  class="mt-1 block w-full rounded-md border-line text-sm shadow-sm focus:border-primary focus:ring-primary"></textarea>
                        <button type="button" wire:click="acrescentarAditamento"
                                class="mt-2 inline-flex min-h-[44px] items-center gap-2 rounded-md border border-line px-4 text-sm font-medium text-ink hover:bg-canvas">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            Acrescentar aditamento
                        </button>
                    </div>
                @endcan
            </div>
        @endif

        {{-- Check-out --}}
        @if ($emAndamento)
            <div class="rounded-lg border border-line bg-surface p-5 shadow-sm">
                @if ($confirmandoCheckout)
                    <p class="text-sm font-medium text-ink">Concluir sem registro escrito?</p>
                    <p class="mt-1 text-sm text-ink-muted">
                        Atendimento sem registro é legítimo — mas depois do check-out a correção
                        só entra como aditamento.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" wire:click="checkOut"
                                class="inline-flex min-h-[44px] items-center rounded-md bg-primary px-4 text-sm font-medium text-white hover:bg-primary-hover">
                            Concluir assim mesmo
                        </button>
                        <button type="button" wire:click="$set('confirmandoCheckout', false)"
                                class="inline-flex min-h-[44px] items-center rounded-md border border-line px-4 text-sm font-medium text-ink hover:bg-canvas">
                            Voltar e escrever
                        </button>
                    </div>
                @else
                    <button type="button" wire:click="pedirConfirmacao"
                            class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-primary px-5 text-sm font-medium text-white hover:bg-primary-hover">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                        Check-out
                    </button>
                    <p class="mt-2 text-xs text-ink-subtle">
                        Fecha o atendimento e o registro, e o lança no prontuário do aprendiz.
                    </p>
                @endif
            </div>
        @endif
    </div>
</div>
