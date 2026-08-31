@props(['status'])

@php
    // App\Domain\Assessment\AssessmentStatus — mapeamento de tom em 03-design-system.md
    $tons = [
        'neutral' => 'bg-gray-100 text-gray-600 border-gray-200',
        'primary' => 'bg-primary-soft text-primary border-primary/20',
        'success' => 'bg-success-soft text-success-ink border-success/20',
        'muted' => 'bg-gray-50 text-gray-400 border-gray-200',
    ];
    $classe = $tons[$status->tone()] ?? $tons['neutral'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium $classe"]) }}>
    {{ $status->label() }}
</span>
