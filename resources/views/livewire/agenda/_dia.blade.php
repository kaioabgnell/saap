{{-- A visão de trabalho: a trilha de horas de um dia só. --}}
<div>
    @foreach ($this->horas as $hora)
        @php
            $fichas = $this->agendamentosDe($this->dia)
                ->filter(fn ($a) => (int) $a->starts_at->format('G') === $hora);
        @endphp

        <div wire:key="hora-dia-{{ $hora }}" class="grid grid-cols-[4rem_minmax(0,1fr)] border-b border-line">
            <div class="px-3 py-3 text-right text-sm tabular-nums text-ink-subtle">{{ $hora }}h</div>

            <div class="flex min-h-[3.5rem] flex-col gap-1 border-l border-line p-1">
                @foreach ($fichas as $agendamento)
                    <button type="button"
                            wire:key="dia-ficha-{{ $agendamento->id }}"
                            wire:click="abrirDetalhe({{ $agendamento->id }})"
                            class="flex min-h-[44px] w-full items-center gap-3 rounded-md border border-line bg-surface px-3 py-2 text-left hover:border-primary/40 hover:bg-primary-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary
                                   {{ in_array($agendamento->status->value, ['cancelled', 'no_show'], true) ? 'opacity-60' : '' }}">
                        <span class="font-medium tabular-nums text-ink">
                            {{ $agendamento->starts_at->format('H:i') }}–{{ $agendamento->ends_at->format('H:i') }}
                        </span>
                        <span class="min-w-0 flex-1 truncate text-ink">{{ $agendamento->learner->name }}</span>
                        <x-appointment-status :status="$agendamento->status" />
                    </button>
                @endforeach

                @if ($fichas->isEmpty())
                    <button type="button"
                            wire:click="novo('{{ $this->dia->toDateString() }}', {{ $hora }})"
                            aria-label="Novo agendamento às {{ $hora }}h"
                            class="min-h-[44px] flex-1 rounded hover:bg-primary-soft/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"></button>
                @endif
            </div>
        </div>
    @endforeach
</div>
