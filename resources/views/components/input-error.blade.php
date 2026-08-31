@props(['messages', 'for' => null])

{{-- O `for` dá ao bloco de erro um id previsível ("{campo}-erro"), que é o que
     o x-text-input aponta em aria-describedby. Sem isso o leitor de tela anuncia
     o campo sem dizer o que houve de errado com ele. --}}
@if ($messages)
    <ul @if ($for) id="{{ $for }}-erro" @endif
        {{ $attributes->merge(['class' => 'text-sm text-danger space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
