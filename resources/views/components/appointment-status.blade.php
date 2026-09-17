@props(['status', 'compacto' => false])

@php
    // Ícone por estado — a forma distingue mesmo sem a cor, que é o que
    // permite a ficha compacta do mês não depender só do tom. Ver 03-design-system.md.
    $icones = [
        'scheduled' => 'fa-regular fa-clock',
        'in_progress' => 'fa-solid fa-play',
        'completed' => 'fa-solid fa-check',
        'no_show' => 'fa-solid fa-user-slash',
        'cancelled' => 'fa-solid fa-ban',
    ];
    $tons = [
        'neutral' => 'bg-gray-100 text-gray-600 border-gray-200',
        'primary' => 'bg-primary-soft text-primary border-primary/20',
        'success' => 'bg-success-soft text-success-ink border-success/20',
        'warning' => 'bg-warning-soft text-warning-ink border-warning/20',
        'muted' => 'bg-gray-50 text-gray-400 border-gray-200',
    ];
    $classe = $tons[$status->tone()] ?? $tons['neutral'];
    $icone = $icones[$status->value] ?? 'fa-regular fa-clock';
@endphp

@if ($compacto)
    <i class="{{ $icone }} text-[10px]" aria-hidden="true"></i>
    <span class="sr-only">{{ $status->label() }}</span>
@else
    <span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium $classe"]) }}>
        <i class="{{ $icone }}" aria-hidden="true"></i>
        {{ $status->label() }}
    </span>
@endif
