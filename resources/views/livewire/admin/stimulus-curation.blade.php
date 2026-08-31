<div class="py-8">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">

        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-ink">Curadoria de estímulos</h1>
                <p class="mt-1 max-w-prose text-sm text-ink-muted">
                    Os rótulos vêm pré-preenchidos a partir do recorte automático.
                    Confirme, corrija ou descarte — só o que tiver rótulo entra na
                    aplicação.
                </p>
            </div>
            @if ($salvoEm)
                <p class="text-sm text-ink-muted">
                    <i class="fa-solid fa-check text-success-ink" aria-hidden="true"></i>
                    Salvo às {{ $salvoEm }}
                </p>
            @endif
        </div>

        {{-- Marcos com material --}}
        <nav aria-label="Marcos com material" class="-mx-4 mb-6 overflow-x-auto px-4 sm:mx-0 sm:px-0">
            <ul class="flex gap-2 pb-1">
                @foreach ($this->marcos as $m)
                    @php
                        $rotulados = $m->stimuli->filter(fn ($e) => trim((string) $e->label) !== '')->count();
                        $suficiente = $rotulados >= $m->threshold_full;
                        $ativo = $itemId === $m->id;
                    @endphp
                    <li class="flex-none">
                        <button type="button" wire:click="selecionarMarco({{ $m->id }})"
                                @class([
                                    'flex min-h-[44px] items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium',
                                    'border-primary bg-primary text-white' => $ativo,
                                    'border-line bg-surface text-ink hover:bg-canvas' => ! $ativo,
                                ])>
                            {{ $m->area->short_name }} {{ $m->position }}
                            <span @class([
                                'rounded-full px-1.5 py-0.5 text-[11px] tabular-nums',
                                'bg-white/20 text-white' => $ativo,
                                'bg-success-soft text-success-ink' => ! $ativo && $suficiente,
                                'bg-warning-soft text-warning-ink' => ! $ativo && ! $suficiente,
                            ])>{{ $rotulados }}/{{ $m->threshold_full }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </nav>

        @if ($this->marco)
            <div class="rounded-lg border border-line bg-surface p-5 shadow-sm">
                <p class="text-sm text-ink">{{ $this->marco->statement }}</p>

                @if ($this->paginas->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($this->paginas as $pagina)
                            <a href="{{ Storage::disk('public')->url($pagina->image_path) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 rounded-md border border-line px-2.5 py-1.5 text-xs text-ink-muted hover:bg-canvas">
                                <i class="fa-solid fa-file-image" aria-hidden="true"></i>
                                Página {{ $pagina->page_number }} de origem
                            </a>
                        @endforeach
                    </div>
                @endif

                <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ($this->marco->stimuli as $estimulo)
                        <div wire:key="est-{{ $estimulo->id }}"
                             @class([
                                 'rounded-lg border-2 p-2',
                                 'border-warning bg-warning-soft' => trim((string) $estimulo->label) === '',
                                 'border-line' => trim((string) $estimulo->label) !== '',
                             ])>
                            <img src="{{ Storage::disk('public')->url($estimulo->image_path) }}"
                                 alt="" loading="lazy"
                                 class="aspect-square w-full rounded object-contain">

                            <input type="text"
                                   wire:model.live.debounce.600ms="rotulos.{{ $estimulo->id }}"
                                   placeholder="rótulo"
                                   aria-label="Rótulo do estímulo"
                                   class="mt-2 block min-h-[44px] w-full rounded-md border-line text-sm focus:border-primary focus:ring-primary">

                            <div class="mt-1 flex items-center justify-between text-[11px] text-ink-subtle">
                                <span>{{ $estimulo->source_page > 0 ? 'p. '.$estimulo->source_page : 'enviado à mão' }}</span>
                                <button type="button" wire:click="descartar({{ $estimulo->id }})"
                                        wire:confirm="Descartar este recorte?"
                                        class="rounded px-1.5 py-1 text-danger hover:bg-danger-soft">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                    <span class="sr-only">Descartar</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 border-t border-line pt-4">
                    <label for="novaImagem" class="block text-sm font-medium text-ink">
                        Subir imagem à mão
                    </label>
                    <p class="mt-0.5 text-xs text-ink-muted">
                        Para quando o recorte falhou ou o marco precisa de um estímulo que o material não traz.
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <input type="file" id="novaImagem" wire:model="novaImagem" accept="image/jpeg,image/png,image/webp"
                               class="block text-sm text-ink-muted file:mr-4 file:rounded-md file:border-0 file:bg-primary-soft file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary">
                        <button type="button" wire:click="subirImagem"
                                class="min-h-[44px] rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                            Adicionar
                        </button>
                    </div>
                    @error('novaImagem') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                </div>
            </div>
        @endif
    </div>
</div>
