@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-md border border-success/20 bg-success-soft px-4 py-3 text-sm font-medium text-success-ink']) }}
         role="status">
        {{ $status }}
    </div>
@endif
