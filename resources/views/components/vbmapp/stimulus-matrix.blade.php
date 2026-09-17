@props(['item', 'linhas', 'colunas', 'imediatas' => true])

@php
    // A figura acha a própria linha pelo rótulo. O comando de importação
    // recusa acervo cujo rótulo não seja uma linha da matriz, então aqui a
    // ausência só significa "marco sem acervo" — caso do Tato 7 do nível 2,
    // que tem 50 linhas de texto e nenhuma imagem.
    $figuras = $item->stimuli->keyBy('label');
@endphp

<div x-data="{ apresentando: false, busca: '' }">

    @if ($figuras->isNotEmpty())
        <div class="mb-3">
            <button type="button" x-on:click="apresentando = true"
                    class="inline-flex min-h-[44px] items-center gap-2 rounded-md border border-line bg-surface px-3 py-2 text-sm font-medium text-ink hover:bg-canvas">
                <i class="fa-solid fa-expand" aria-hidden="true"></i>
                Mostrar ao aprendiz
            </button>
        </div>
    @endif

    @if (count($linhas) > 20)
        <div class="relative mb-3">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-ink-subtle" aria-hidden="true"></i>
            <input type="search" x-model="busca" placeholder="Buscar entre {{ count($linhas) }} itens…"
                   class="min-h-[44px] w-full rounded-md border-line pl-8 text-sm focus:border-primary focus:ring-primary">
        </div>
    @endif

    {{-- A grade rola dentro do próprio contêiner — a página nunca rola de lado. --}}
    <div class="overflow-x-auto rounded-md border border-line">
        <table class="w-full min-w-[480px] text-sm">
            <thead>
                <tr class="border-b border-line bg-canvas">
                    <th class="sticky left-0 bg-canvas px-3 py-2 text-left font-medium text-ink-muted">Item</th>
                    @foreach ($colunas as $coluna)
                        <th class="px-3 py-2 text-center font-medium text-ink-muted">{{ $coluna }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($linhas as $linha)
                    <tr x-show="!busca || {{ \Illuminate\Support\Js::from(mb_strtolower($linha)) }}.includes(busca.toLowerCase())"
                        class="border-b border-line last:border-0 odd:bg-surface even:bg-canvas/40">
                        <td class="sticky left-0 bg-inherit px-3 py-2 text-ink">
                            @if ($figura = $figuras->get($linha))
                                <span class="flex items-center gap-2.5">
                                    {{-- A miniatura abre a mesma tela cheia do
                                         "Mostrar ao aprendiz" — é a forma mais
                                         curta de conferir a figura do item
                                         antes de marcar a linha. --}}
                                    <button type="button" x-on:click="apresentando = true"
                                            class="flex-none rounded border border-line bg-surface p-0.5 transition hover:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                            title="Ver em tela cheia">
                                        <img src="{{ Storage::disk('public')->url($figura->image_path) }}"
                                             alt="" loading="{{ $imediatas ? 'eager' : 'lazy' }}" decoding="async"
                                             class="h-10 w-10 object-contain">
                                        <span class="sr-only">Ver {{ $linha }} em tela cheia</span>
                                    </button>
                                    {{ $linha }}
                                </span>
                            @else
                                {{ $linha }}
                            @endif
                        </td>
                        @foreach ($colunas as $coluna)
                            @php $chave = $linha.'::'.$coluna; @endphp
                            <td class="px-3 py-2 text-center">
                                <input type="checkbox"
                                       wire:model.live="matrizMarcadas.{{ $chave }}"
                                       aria-label="{{ $linha }}, {{ $coluna }}"
                                       class="h-5 w-5 rounded border-line text-success-ink focus:ring-success">
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Modo apresentação da matriz.

         Diferente da grade de imagem simples: aqui cada figura tem uma
         pergunta por coluna (cor, forma, função), então cada uma leva os três
         registros junto. Sem isso o psicólogo teria de sair da apresentação a
         cada resposta — três vezes por objeto.

         O nome do objeto não aparece: a criança tem de tatear a figura, não
         ler a legenda. Os nomes das colunas ficam, porque a pergunta é feita
         em voz alta de qualquer forma. --}}
    @if ($figuras->isNotEmpty())
        <div x-show="apresentando" x-cloak
             x-on:keydown.escape.window="apresentando = false"
             class="fixed inset-0 z-40 overflow-auto bg-white p-6"
             role="dialog" aria-modal="true" aria-label="Objetos em tela cheia">

            <button type="button" x-on:click="apresentando = false"
                    class="fixed right-4 top-4 z-50 flex h-12 w-12 items-center justify-center rounded-full border border-line bg-white text-ink-muted shadow-lg hover:text-ink">
                <i class="fa-solid fa-xmark text-xl" aria-hidden="true"></i>
                <span class="sr-only">Sair da apresentação</span>
            </button>

            <div class="mx-auto grid max-w-5xl grid-cols-2 gap-8 pt-16 sm:grid-cols-3">
                @foreach ($linhas as $linha)
                    @php $figura = $figuras->get($linha); @endphp
                    @if ($figura)
                        <div>
                            <img src="{{ Storage::disk('public')->url($figura->image_path) }}"
                                 alt="{{ $linha }}" loading="lazy"
                                 class="aspect-square w-full object-contain">

                            <div class="mt-2 flex flex-wrap justify-center gap-1.5">
                                @foreach ($colunas as $coluna)
                                    <label class="cursor-pointer">
                                        <input type="checkbox"
                                               wire:model.live="matrizMarcadas.{{ $linha.'::'.$coluna }}"
                                               class="peer sr-only">
                                        <span class="block min-h-[36px] rounded-full border border-line px-3 py-1.5 text-xs font-medium text-ink-muted transition
                                                     peer-checked:border-success peer-checked:bg-success-soft peer-checked:text-success-ink
                                                     peer-focus-visible:ring-2 peer-focus-visible:ring-primary">
                                            {{ $coluna }}
                                        </span>
                                        <span class="sr-only">{{ $linha }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>

<div class="mt-3 flex items-center gap-2">
    <input type="text" wire:model="novaLinhaMatrix" wire:keydown.enter="acrescentarLinhaMatriz"
           placeholder="Acrescentar item à grade"
           aria-label="Acrescentar item à grade"
           class="min-h-[44px] flex-1 rounded-md border-line text-sm focus:border-primary focus:ring-primary">
    <button type="button" wire:click="acrescentarLinhaMatriz"
            class="min-h-[44px] rounded-md border border-line px-3 text-sm font-medium text-ink hover:bg-canvas">
        Adicionar
    </button>
</div>
