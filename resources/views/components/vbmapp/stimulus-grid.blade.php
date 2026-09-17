@props(['item', 'estimulos', 'paginas' => [], 'imediatas' => true])

{{-- Grade de estímulos do material de aplicação.
     Componente Blade, não Livewire: o ItemCard já é quem grava, e aninhar mais
     um componente por marco multiplicaria os snapshots sem ganho. --}}
<div x-data="{ apresentando: false }">

    {{-- Instrução do acervo: o que fazer com ESTAS figuras.
         Fica só aqui, na tela do psicólogo. Na apresentação abaixo ela seria
         lida pela criança — e dizer "falando cor ou forma" na frente dela
         entrega o critério do que está sendo testado. --}}
    @if ($item->stimulus_prompt)
        <p class="mb-3 text-sm font-medium text-ink-muted">{{ $item->stimulus_prompt }}</p>
    @endif

    <div class="mb-3 flex flex-wrap items-center gap-2">
        <button type="button" x-on:click="apresentando = true"
                class="inline-flex min-h-[44px] items-center gap-2 rounded-md border border-line bg-surface px-3 py-2 text-sm font-medium text-ink hover:bg-canvas">
            <i class="fa-solid fa-expand" aria-hidden="true"></i>
            Mostrar ao aprendiz
        </button>

        {{-- Só quando o marco ainda não tem acervo próprio: com figuras
             curadas na grade abaixo, a página inteira do PDF é referência
             redundante — a mesma imagem, maior e sem check ao lado. --}}
        @if ($item->stimuli->isEmpty())
            @foreach ($paginas as $pagina)
                <a href="{{ Storage::disk('public')->url($pagina->image_path) }}" target="_blank" rel="noopener"
                   class="inline-flex min-h-[44px] items-center gap-2 rounded-md border border-line bg-surface px-3 py-2 text-sm font-medium text-ink-muted hover:bg-canvas">
                    <i class="fa-solid fa-file-image" aria-hidden="true"></i>
                    Página {{ $pagina->page_number }}
                </a>
            @endforeach
        @endif
    </div>

    {{-- Grade de registro: cada imagem é um check.

         `loading` não é detalhe de performance aqui, é de aplicação: com
         `lazy`, a figura só começa a baixar quando a rolagem se aproxima
         dela, e o psicólogo chega no marco antes da imagem — quadrado em
         branco com a criança esperando. As figuras do acervo têm de 800 a
         1600px, então baixar tudo de uma vez só se justifica quando a tela
         mostra uma área; em "todas as áreas" o nível inteiro renderiza junto
         e aí vale esperar a rolagem. Ver ItemCard::$imagensImediatas. --}}
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
                         loading="{{ $imediatas ? 'eager' : 'lazy' }}" decoding="async"
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

    {{-- Modo apresentação: a tela que a criança olha.

         Também registra. O psicólogo pede o tato, a criança responde, e o
         registro é feito ali mesmo, sem sair da apresentação e sem tirar as
         figuras da frente dela.

         Duas escolhas para não contaminar o que a criança vê: nenhum rótulo
         (ler o nome ao lado da figura entregaria a resposta) e a marca de
         seleção discreta, no canto — a criança não deve ler o próprio
         desempenho na tela enquanto ainda está sendo avaliada.

         São os MESMOS arquivos da grade acima, então quando a apresentação
         abre as figuras já estão no cache do navegador. Por isso aqui é
         sempre `lazy`: não há o que adiantar, só o que duplicar. --}}
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
                <label class="relative cursor-pointer">
                    <input type="checkbox"
                           wire:model.live="estimulos.{{ $estimulo->id }}"
                           class="peer sr-only">

                    <span class="block overflow-hidden rounded-xl border-4 border-transparent transition peer-checked:border-success peer-focus-visible:ring-2 peer-focus-visible:ring-primary">
                        <img src="{{ Storage::disk('public')->url($estimulo->image_path) }}"
                             alt="{{ $estimulo->label }}" loading="lazy"
                             class="aspect-square w-full object-contain">
                    </span>

                    <i class="fa-solid fa-circle-check absolute right-2 top-2 text-2xl text-success-ink opacity-0 transition peer-checked:opacity-100"
                       aria-hidden="true"></i>

                    <span class="sr-only">{{ $estimulo->label ?: 'Estímulo sem rótulo' }}</span>
                </label>
            @endforeach
        </div>
    </div>
</div>
