{{-- Sete colunas sobre a trilha de horas. --}}
<div class="overflow-x-auto">
    <div class="min-w-[44rem]">
        <div class="grid grid-cols-[3.5rem_repeat(7,minmax(0,1fr))] border-b border-line bg-canvas">
            <div></div>
            @foreach ($this->diasDaSemana as $dia)
                <button type="button"
                        wire:key="cab-{{ $dia->toDateString() }}"
                        wire:click="abrirDia('{{ $dia->toDateString() }}')"
                        class="min-h-[44px] border-l border-line px-1 py-2 text-center hover:bg-primary-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary">
                    <div class="text-xs uppercase tracking-wide text-ink-subtle">
                        {{ \App\Domain\Schedule\CalendarLabels::diaCurto($dia) }}
                    </div>
                    <div class="mx-auto mt-0.5 flex h-7 w-7 items-center justify-center rounded-full text-sm tabular-nums
                                {{ $dia->isToday() ? 'bg-primary font-semibold text-white' : 'text-ink' }}">
                        {{ $dia->day }}
                    </div>
                </button>
            @endforeach
        </div>

        @foreach ($this->horas as $hora)
            <div wire:key="hora-{{ $hora }}" class="grid grid-cols-[3.5rem_repeat(7,minmax(0,1fr))] border-b border-line">
                <div class="px-2 py-1 text-right text-xs tabular-nums text-ink-subtle">{{ $hora }}h</div>

                @foreach ($this->diasDaSemana as $dia)
                    @php
                        $fichas = $this->agendamentosDe($dia)
                            ->filter(fn ($a) => (int) $a->starts_at->format('G') === $hora);
                    @endphp
                    <div wire:key="cel-{{ $dia->toDateString() }}-{{ $hora }}"
                         class="flex min-h-[3rem] flex-col gap-0.5 border-l border-line p-0.5">
                        @foreach ($fichas as $agendamento)
                            @include('livewire.agenda._ficha', ['agendamento' => $agendamento, 'compacta' => true])
                        @endforeach

                        {{-- Sem faixa de "marcar" onde já há atendimento: a
                             sobra seria alvo de toque menor que o mínimo. Numa
                             hora ocupada, marca-se pelo botão do cabeçalho. --}}
                        @if ($fichas->isEmpty())
                            <button type="button"
                                    wire:click="novo('{{ $dia->toDateString() }}', {{ $hora }})"
                                    aria-label="Novo agendamento em {{ $dia->format('d/m') }} às {{ $hora }}h"
                                    class="min-h-[44px] flex-1 rounded hover:bg-primary-soft/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"></button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
