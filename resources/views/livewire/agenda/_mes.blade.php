{{-- A grade do mês: 6 linhas de 7 dias, começando no domingo. --}}
<div class="overflow-x-auto">
    <div class="min-w-[44rem]">
        <div class="grid grid-cols-7 border-b border-line bg-canvas">
            @foreach (\App\Domain\Schedule\CalendarLabels::CABECALHO_DA_SEMANA as $dia)
                <div class="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-ink-subtle">
                    {{ $dia }}
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-7">
            @foreach ($this->semanas as $semana)
                @foreach ($semana as $dia)
                    @php
                        $doMes = $dia->month === $this->dia->month;
                        $hoje = $dia->isToday();
                        $fichas = $this->agendamentosDe($dia);
                        $sobram = $fichas->count() - \App\Livewire\Agenda\Schedule::FICHAS_POR_DIA;
                    @endphp

                    <div wire:key="dia-{{ $dia->toDateString() }}"
                         class="flex min-h-[9.5rem] flex-col border-b border-r border-line
                                {{ $doMes ? 'bg-surface' : 'bg-canvas' }}">

                        <div class="flex items-center justify-end px-1 pt-1">
                            <button type="button"
                                    wire:click="abrirDia('{{ $dia->toDateString() }}')"
                                    aria-label="Abrir {{ $dia->format('d/m/Y') }}"
                                    aria-current="{{ $hoje ? 'date' : 'false' }}"
                                    class="flex h-11 min-w-[2.75rem] items-center justify-center rounded-full px-2 text-sm tabular-nums
                                           hover:bg-primary-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary
                                           {{ $hoje ? 'bg-primary font-semibold text-white hover:bg-primary-hover' : ($doMes ? 'text-ink' : 'text-ink-subtle') }}">
                                {{ $dia->day }}
                            </button>
                        </div>

                        <div class="space-y-0.5 px-1">
                            @foreach ($fichas->take(\App\Livewire\Agenda\Schedule::FICHAS_POR_DIA) as $agendamento)
                                @include('livewire.agenda._ficha', ['agendamento' => $agendamento, 'compacta' => true])
                            @endforeach

                            @if ($sobram > 0)
                                <button type="button"
                                        wire:click="abrirDia('{{ $dia->toDateString() }}')"
                                        class="min-h-[44px] w-full rounded px-1.5 text-left text-xs font-medium text-primary hover:bg-primary-soft">
                                    +{{ $sobram }} mais
                                </button>
                            @endif
                        </div>

                        {{-- O espaço vazio marca um atendimento naquele dia.
                             Some quando a célula enche: a sobra ali seria uma
                             faixa de 12px, alvo de toque menor que o mínimo do
                             sistema. Num dia cheio, marca-se pela visão de dia
                             (o número do dia) ou pelo botão do cabeçalho. --}}
                        @if ($sobram <= 0)
                            <button type="button"
                                    wire:click="novo('{{ $dia->toDateString() }}')"
                                    aria-label="Novo agendamento em {{ $dia->format('d/m/Y') }}"
                                    class="min-h-[44px] flex-1 rounded hover:bg-primary-soft/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"></button>
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
</div>
