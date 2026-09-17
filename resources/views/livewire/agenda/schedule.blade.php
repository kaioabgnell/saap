<div class="py-6" x-data
     x-on:abrir-lembrete.window="window.open($event.detail.url, '_blank', 'noopener')">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        <div class="overflow-hidden rounded-lg border border-line bg-surface shadow-sm">

            {{-- Cabeçalho: título, navegação e troca de visão --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                <div class="flex items-center gap-1">
                    <button type="button" wire:click="anterior" aria-label="Período anterior"
                            class="flex h-11 w-11 items-center justify-center rounded-md text-ink-muted hover:bg-canvas hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                    </button>
                    <button type="button" wire:click="hoje"
                            class="inline-flex min-h-[44px] items-center rounded-md border border-line px-3 text-sm font-medium text-ink hover:bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                        Hoje
                    </button>
                    <button type="button" wire:click="proximo" aria-label="Próximo período"
                            class="flex h-11 w-11 items-center justify-center rounded-md text-ink-muted hover:bg-canvas hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </button>

                    <h2 class="ms-2 text-lg font-semibold text-ink" aria-live="polite">{{ $this->titulo }}</h2>
                </div>

                <div class="flex items-center gap-2">
                    <div class="inline-flex rounded-md border border-line p-0.5" role="group" aria-label="Visão da agenda">
                        @foreach (\App\Domain\Schedule\CalendarView::cases() as $opcao)
                            <button type="button"
                                    wire:key="visao-{{ $opcao->value }}"
                                    wire:click="mudarVisao('{{ $opcao->value }}')"
                                    aria-pressed="{{ $this->visaoAtual === $opcao ? 'true' : 'false' }}"
                                    class="min-h-[44px] rounded px-3 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary
                                           {{ $this->visaoAtual === $opcao ? 'bg-primary text-white' : 'text-ink-muted hover:bg-canvas hover:text-ink' }}">
                                {{ $opcao->label() }}
                            </button>
                        @endforeach
                    </div>

                    <button type="button" wire:click="novo"
                            class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        Novo agendamento
                    </button>
                </div>
            </div>

            @if ($erro && $detalheId === null)
                <p class="flex items-start gap-2 border-b border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
                    <span>{{ $erro }}</span>
                </p>
            @endif

            @switch($this->visaoAtual)
                @case(\App\Domain\Schedule\CalendarView::Mes)
                    @include('livewire.agenda._mes')
                    @break
                @case(\App\Domain\Schedule\CalendarView::Semana)
                    @include('livewire.agenda._semana')
                    @break
                @default
                    @include('livewire.agenda._dia')
            @endswitch
        </div>
    </div>

    @if ($this->detalhe)
        @include('livewire.agenda._detalhe')
    @endif

    <livewire:agenda.appointment-form />
</div>
