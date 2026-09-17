{{-- Uma ficha de atendimento. `compacta` é a do mês, onde o espaço é curto. --}}
@php
    $tomDaBorda = [
        'scheduled' => 'border-l-gray-300',
        'in_progress' => 'border-l-primary',
        'completed' => 'border-l-success',
        'no_show' => 'border-l-warning',
        'cancelled' => 'border-l-gray-300',
    ][$agendamento->status->value];

    $apagada = in_array($agendamento->status->value, ['cancelled', 'no_show'], true);
@endphp

<button type="button"
        wire:click.stop="abrirDetalhe({{ $agendamento->id }})"
        wire:key="ficha-{{ $agendamento->id }}"
        title="{{ $agendamento->starts_at->format('H:i') }} — {{ $agendamento->learner->name }} · {{ $agendamento->status->label() }}"
        class="flex w-full items-center gap-1.5 overflow-hidden rounded border-l-[3px] {{ $tomDaBorda }} bg-canvas px-1.5 text-left hover:bg-primary-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary
               {{ $compacta ?? false ? 'min-h-[44px] py-1 text-xs' : 'min-h-[44px] py-2 text-sm' }}
               {{ $apagada ? 'opacity-60' : '' }}">
    <x-appointment-status :status="$agendamento->status" compacto />
    <span class="font-medium tabular-nums text-ink">{{ $agendamento->starts_at->format('H:i') }}</span>
    <span class="truncate {{ $apagada ? 'text-ink-subtle line-through' : 'text-ink-muted' }}">
        {{ $compacta ?? false ? \Illuminate\Support\Str::before($agendamento->learner->name, ' ') : $agendamento->learner->name }}
    </span>
</button>
