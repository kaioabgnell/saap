@props(['item', 'estimulos', 'paginas' => []])

{{-- Grade de estímulos do material de aplicação.
     Componente Blade, não Livewire: o ItemCard já é quem grava, e aninhar mais
     um componente por marco multiplicaria os snapshots sem ganho. --}}
<div x-data="{ apresentando: false }">

    <div class="mb-3 flex flex-wrap items-center gap-2">
        <button type="button" x-on:click="apresentando = true"
                class="inline-flex min-h-[44px] items-center gap-2 rounded-md border border-line bg-surface px-3 py-2 text-sm font-medium text-ink hover:bg-canvas">
            <i class="fa-solid fa-expand" aria-hidden="true"></i>
            Mostrar ao aprendiz
        </button>

        @foreach ($paginas as $pagina)
            <a href="{{ Storage::disk('public')->url($pagina->image_path) }}" target="_blank" rel="noopener"
               class="inline-flex min-h-[44px] items-center gap-2 rounded-md border border-line bg-surface px-3 py-2 text-sm font-medium text-ink-muted hover:bg-canvas">
                <i class="fa-solid fa-file-image" aria-hidden="true"></i>
                Página {{ $pagina->page_number }}
            </a>
        @endforeach
    </div>

    {{-- Grade de registro: cada imagem é um check --}}
    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
        @foreach ($item->stimuli as $estimulo)
            <label class="group relative cursor-pointer">
                <input type="checkbox"
                       wire:model.live="estimulos.{{ $estimulo->id }}"
                       class="peer sr-only">

                <span class="block overflow-hidden rounded-lg border-2 border-line bg-surface transition
                             peer-checked:border-success peer-focus-visible:ring-2 peer-focus-visible:ring-primary peer-focus-visible:ring-offset-2">
                    <img src="{{ Storage::disk('public')->url($estimulo->image_path) }}"
                         alt="{{ $estimulo->label ?: 'Estímulo sem rótulo' }}"
                         loading="lazy" decoding="async"
                         class="aspect-square w-full min-w-[96px] object-contain p-2">
                </span>

                <i class="fa-solid fa-circle-check absolute right-1.5 top-1.5 text-xl text-success-ink opacity-0 transition peer-checked:opacity-100"
                   aria-hidden="true"></i>

                <span class="mt-1 block truncate text-center text-[13px] text-ink-muted">
                    {{ $estimulo->label ?: '—' }}
                </span>
            </label>
        @endforeach
    </div>

    {{-- Modo apresentação: as mesmas figuras, sem nenhum check à vista.
         É a tela que a criança olha enquanto o psicólogo registra. --}}
    <div x-show="apresentando" x-cloak
         x-on:keydown.escape.window="apresentando = false"
         class="fixed inset-0 z-40 overflow-auto bg-white p-6"
         role="dialog" aria-modal="true" aria-label="Estímulos em tela cheia">

        <button type="button" x-on:click="apresentando = false"
                class="fixed right-4 top-4 z-50 flex h-12 w-12 items-center justify-center rounded-full border border-line bg-white text-ink-muted shadow-lg hover:text-ink">
            <i class="fa-solid fa-xmark text-xl" aria-hidden="true"></i>
            <span class="sr-only">Sair da apresentação</span>
        </button>

        <div class="mx-auto grid max-w-5xl grid-cols-2 gap-8 pt-16 sm:grid-cols-3">
            @foreach ($item->stimuli as $estimulo)
                <img src="{{ Storage::disk('public')->url($estimulo->image_path) }}"
                     alt="{{ $estimulo->label }}" loading="lazy"
                     class="aspect-square w-full object-contain">
            @endforeach
        </div>
    </div>
</div>
