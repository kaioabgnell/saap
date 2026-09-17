{{-- Painel da ficha aberta: o que dá para fazer com este atendimento. --}}
@php $a = $this->detalhe; @endphp

<div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true"
     aria-labelledby="detalhe-titulo">
    <button type="button" wire:click="fecharDetalhe" tabindex="-1" aria-hidden="true"
            class="absolute inset-0 bg-gray-500/75"></button>

    <div class="relative mb-0 w-full max-w-lg rounded-t-lg bg-surface shadow-xl sm:mb-6 sm:rounded-lg">
        <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
            <div class="min-w-0">
                <h3 id="detalhe-titulo" class="truncate text-lg font-semibold text-ink">{{ $a->learner->name }}</h3>
                <p class="mt-0.5 text-sm text-ink-muted">
                    {{ \App\Domain\Schedule\CalendarLabels::porExtenso($a->starts_at) }},
                    {{ $a->starts_at->format('H:i') }}–{{ $a->ends_at->format('H:i') }}
                    <span class="text-ink-subtle">({{ $a->duracaoPrevista() }} min)</span>
                </p>
            </div>
            <button type="button" wire:click="fecharDetalhe" aria-label="Fechar"
                    class="flex h-11 w-11 flex-none items-center justify-center rounded-md text-ink-muted hover:bg-canvas hover:text-ink">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div class="space-y-4 px-5 py-4">
            <div class="flex flex-wrap items-center gap-2">
                <x-appointment-status :status="$a->status" />
                @if ($a->reminder_opened_at)
                    <span class="text-xs text-ink-subtle">
                        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                        Lembrete aberto em {{ $a->reminder_opened_at->format('d/m \à\s H:i') }}
                    </span>
                @endif
            </div>

            @if ($a->booking_note)
                <p class="rounded-md bg-canvas px-3 py-2 text-sm text-ink-muted">
                    <span class="font-medium text-ink">Recado:</span> {{ $a->booking_note }}
                </p>
            @endif

            @if ($a->cancel_reason)
                <p class="rounded-md bg-canvas px-3 py-2 text-sm text-ink-muted">
                    <span class="font-medium text-ink">Motivo do cancelamento:</span> {{ $a->cancel_reason }}
                </p>
            @endif

            @if ($erro)
                <p class="flex items-start gap-2 rounded-md border border-danger/20 bg-danger-soft px-3 py-2 text-sm text-danger" role="alert">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
                    <span>{{ $erro }}</span>
                </p>
            @endif

            @if ($cancelando)
                <div class="rounded-md border border-line p-3">
                    <label for="motivo" class="block text-sm font-medium text-ink">Motivo do cancelamento</label>
                    <input id="motivo" type="text" wire:model="motivoDoCancelamento" maxlength="255"
                           placeholder="Ex.: responsável remarcou"
                           class="mt-1 block min-h-[44px] w-full rounded-md border-line text-sm shadow-sm focus:border-primary focus:ring-primary">
                    <p class="mt-1 text-xs text-ink-subtle">
                        Cancelar preserva o agendamento no prontuário — o horário não desaparece da história.
                    </p>
                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="cancelar({{ $a->id }})"
                                class="inline-flex min-h-[44px] items-center rounded-md bg-danger px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                            Confirmar cancelamento
                        </button>
                        <button type="button" wire:click="$set('cancelando', false)"
                                class="inline-flex min-h-[44px] items-center rounded-md border border-line px-4 py-2 text-sm font-medium text-ink hover:bg-canvas">
                            Voltar
                        </button>
                    </div>
                </div>
            @else
                <div class="flex flex-wrap gap-2">
                    @can('update', $a)
                        @if ($a->status === \App\Domain\Schedule\AppointmentStatus::Scheduled)
                            <button type="button" wire:click="checkIn({{ $a->id }})"
                                    class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                                <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                                Check-in
                            </button>
                        @endif

                        @if ($a->status === \App\Domain\Schedule\AppointmentStatus::InProgress)
                            <a href="{{ route('atendimentos.show', $a) }}" wire:navigate
                               class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                Abrir atendimento
                            </a>
                        @endif

                        <button type="button" wire:click="remarcar({{ $a->id }})"
                                class="inline-flex min-h-[44px] items-center gap-2 rounded-md border border-line px-4 py-2 text-sm font-medium text-ink hover:bg-canvas">
                            <i class="fa-regular fa-calendar-plus" aria-hidden="true"></i>
                            Remarcar
                        </button>

                        @if ($a->status === \App\Domain\Schedule\AppointmentStatus::Scheduled)
                            <button type="button" wire:click="marcarFalta({{ $a->id }})"
                                    class="inline-flex min-h-[44px] items-center gap-2 rounded-md border border-line px-4 py-2 text-sm font-medium text-ink hover:bg-canvas">
                                <i class="fa-solid fa-user-slash" aria-hidden="true"></i>
                                Faltou
                            </button>
                        @endif

                        <button type="button" wire:click="pedirMotivo"
                                class="inline-flex min-h-[44px] items-center gap-2 rounded-md px-4 py-2 text-sm font-medium text-danger hover:bg-danger-soft">
                            <i class="fa-solid fa-ban" aria-hidden="true"></i>
                            Cancelar
                        </button>
                    @endcan
                </div>

                <div class="flex flex-wrap items-center gap-2 border-t border-line pt-3">
                    @if ($a->linkDoLembrete())
                        <button type="button" wire:click="abrirLembrete({{ $a->id }})"
                                class="inline-flex min-h-[44px] items-center gap-2 rounded-md border border-line px-4 py-2 text-sm font-medium text-ink hover:bg-canvas">
                            <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                            Enviar lembrete
                        </button>
                    @else
                        <button type="button" disabled
                                class="inline-flex min-h-[44px] cursor-not-allowed items-center gap-2 rounded-md border border-line px-4 py-2 text-sm font-medium text-ink-subtle">
                            <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                            Enviar lembrete
                        </button>
                        <span class="text-xs text-ink-subtle">
                            {{ $a->learner->contact_phone
                                ? 'O telefone do cadastro não é um número brasileiro válido.'
                                : 'O aprendiz não tem telefone de contato cadastrado.' }}
                        </span>
                    @endif

                    <a href="{{ route('aprendizes.prontuario', $a->learner) }}" wire:navigate
                       class="inline-flex min-h-[44px] items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-primary hover:bg-primary-soft">
                        <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                        Prontuário
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
