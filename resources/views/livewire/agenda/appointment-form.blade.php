<div>
@if ($aberto)
    <div class="fixed inset-0 z-[60] flex items-end justify-center overflow-y-auto sm:items-start sm:py-10"
         role="dialog" aria-modal="true" aria-labelledby="agendamento-titulo">
        <button type="button" wire:click="fechar" tabindex="-1" aria-hidden="true"
                class="fixed inset-0 bg-gray-500/75"></button>

        <div class="relative w-full max-w-xl rounded-t-lg bg-surface shadow-xl sm:rounded-lg">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h3 id="agendamento-titulo" class="text-lg font-semibold text-ink">
                    {{ $this->remarcando() ? 'Remarcar atendimento' : 'Novo agendamento' }}
                </h3>
                <button type="button" wire:click="fechar" aria-label="Fechar"
                        class="flex h-11 w-11 items-center justify-center rounded-md text-ink-muted hover:bg-canvas hover:text-ink">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            @if ($cadastroRapido)
                {{-- ------------------------------------------ cadastro rápido --}}
                <form wire:submit="cadastrarRapido" class="space-y-4 px-5 py-4">
                    <p class="text-sm text-ink-muted">
                        Cadastro rápido — os demais dados podem ser completados depois na ficha do aprendiz.
                    </p>

                    <div>
                        <label for="novoNome" class="block text-sm font-medium text-ink">Nome do aprendiz</label>
                        <input id="novoNome" type="text" wire:model="novoNome" autocomplete="off"
                               class="mt-1 block min-h-[44px] w-full rounded-md border-line shadow-sm focus:border-primary focus:ring-primary">
                        @error('novoNome') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="novoPai" class="block text-sm font-medium text-ink">Nome do pai</label>
                            <input id="novoPai" type="text" wire:model="novoPai" autocomplete="off"
                                   class="mt-1 block min-h-[44px] w-full rounded-md border-line shadow-sm focus:border-primary focus:ring-primary">
                            @error('novoPai') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="novaMae" class="block text-sm font-medium text-ink">Nome da mãe</label>
                            <input id="novaMae" type="text" wire:model="novaMae" autocomplete="off"
                                   class="mt-1 block min-h-[44px] w-full rounded-md border-line shadow-sm focus:border-primary focus:ring-primary">
                            @error('novaMae') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="novoTelefone" class="block text-sm font-medium text-ink">Telefone de contato</label>
                            <input id="novoTelefone" type="tel" wire:model="novoTelefone" placeholder="(00) 00000-0000"
                                   class="mt-1 block min-h-[44px] w-full rounded-md border-line shadow-sm focus:border-primary focus:ring-primary">
                            @error('novoTelefone') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="novoNascimento" class="block text-sm font-medium text-ink">Data de nascimento</label>
                            <input id="novoNascimento" type="date" wire:model="novoNascimento"
                                   class="mt-1 block min-h-[44px] w-full rounded-md border-line shadow-sm focus:border-primary focus:ring-primary">
                            @error('novoNascimento') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-line pt-4">
                        <button type="button" wire:click="fecharCadastroRapido"
                                class="inline-flex min-h-[44px] items-center rounded-md border border-line px-4 text-sm font-medium text-ink hover:bg-canvas">
                            Voltar
                        </button>
                        <button type="submit"
                                class="inline-flex min-h-[44px] items-center rounded-md bg-primary px-4 text-sm font-medium text-white hover:bg-primary-hover">
                            Cadastrar e selecionar
                        </button>
                    </div>
                </form>
            @else
                {{-- --------------------------------------------- agendamento --}}
                <form wire:submit="salvar" class="space-y-4 px-5 py-4">

                    <div>
                        <span class="block text-sm font-medium text-ink">Aprendiz</span>

                        @if ($this->selecionado)
                            <div class="mt-1 flex items-center justify-between gap-3 rounded-md border border-line bg-canvas px-3 py-2">
                                <span class="truncate font-medium text-ink">{{ $this->selecionado->name }}</span>
                                @unless ($this->remarcando())
                                    <button type="button" wire:click="$set('learnerId', null)"
                                            class="min-h-[44px] rounded px-2 text-sm font-medium text-primary hover:bg-primary-soft">
                                        Trocar
                                    </button>
                                @endunless
                            </div>
                        @else
                            <input type="search" wire:model.live.debounce.300ms="busca" placeholder="Buscar pelo nome"
                                   aria-label="Buscar aprendiz"
                                   class="mt-1 block min-h-[44px] w-full rounded-md border-line shadow-sm focus:border-primary focus:ring-primary">

                            <ul class="mt-2 max-h-48 divide-y divide-line overflow-y-auto rounded-md border border-line">
                                @forelse ($this->aprendizes as $aprendiz)
                                    <li wire:key="ap-{{ $aprendiz->id }}">
                                        <button type="button" wire:click="selecionar({{ $aprendiz->id }})"
                                                class="flex min-h-[44px] w-full items-center px-3 py-2 text-left text-sm text-ink hover:bg-primary-soft">
                                            {{ $aprendiz->name }}
                                        </button>
                                    </li>
                                @empty
                                    <li class="px-3 py-3 text-sm text-ink-muted">Nenhum aprendiz encontrado.</li>
                                @endforelse
                            </ul>

                            <button type="button" wire:click="abrirCadastroRapido"
                                    class="mt-2 inline-flex min-h-[44px] items-center gap-2 text-sm font-medium text-primary hover:underline">
                                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                                Cadastrar novo aprendiz
                            </button>
                        @endif

                        @error('learnerId') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="data" class="block text-sm font-medium text-ink">Data</label>
                            <input id="data" type="date" wire:model.live="data"
                                   class="mt-1 block min-h-[44px] w-full rounded-md border-line shadow-sm focus:border-primary focus:ring-primary">
                            @error('data') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="hora" class="block text-sm font-medium text-ink">Início</label>
                            <input id="hora" type="time" wire:model.live="hora" step="300"
                                   class="mt-1 block min-h-[44px] w-full rounded-md border-line shadow-sm focus:border-primary focus:ring-primary">
                            @error('hora') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="duracao" class="block text-sm font-medium text-ink">Duração (min)</label>
                            <input id="duracao" type="number" wire:model.live="duracao" min="5" max="480" step="5"
                                   class="mt-1 block min-h-[44px] w-full rounded-md border-line tabular-nums shadow-sm focus:border-primary focus:ring-primary">
                            @error('duracao') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @unless ($this->remarcando())
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="repetir" class="block text-sm font-medium text-ink">Repetir por (semanas)</label>
                                <input id="repetir" type="number" wire:model.live="repetir" min="1" max="52"
                                       aria-describedby="repetir-ajuda"
                                       class="mt-1 block min-h-[44px] w-full rounded-md border-line tabular-nums shadow-sm focus:border-primary focus:ring-primary">
                                <p id="repetir-ajuda" class="mt-1 text-xs text-ink-subtle">1 = não repetir.</p>
                                @error('repetir') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="recado" class="block text-sm font-medium text-ink">Observação</label>
                                <input id="recado" type="text" wire:model="recado" maxlength="255"
                                       placeholder="Recado da marcação — ex.: vem com a avó"
                                       aria-describedby="recado-ajuda"
                                       class="mt-1 block min-h-[44px] w-full rounded-md border-line shadow-sm focus:border-primary focus:ring-primary">
                                <p id="recado-ajuda" class="mt-1 text-xs text-ink-subtle">
                                    Recado de logística. O registro clínico é escrito no atendimento.
                                </p>
                                @error('recado') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endunless

                    @if ($erro)
                        <p class="flex items-start gap-2 rounded-md border border-danger/20 bg-danger-soft px-3 py-2 text-sm text-danger" role="alert">
                            <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
                            <span>{{ $erro }}</span>
                        </p>
                    @endif

                    @if ($conflitos)
                        {{-- O conflito avisa, não impede: mostra quem já está lá e pergunta. --}}
                        <div class="rounded-md border border-warning/30 bg-warning-soft px-4 py-3" role="alert">
                            <p class="flex items-center gap-2 text-sm font-medium text-warning-ink">
                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                {{ count($conflitos) === 1 ? 'Já há atendimento neste horário' : 'Já há atendimento em '.count($conflitos).' destes horários' }}
                            </p>
                            <ul class="mt-2 space-y-1 text-sm text-warning-ink">
                                @foreach ($conflitos as $conflito)
                                    <li wire:key="cf-{{ $loop->index }}" class="tabular-nums">
                                        <span class="font-medium">{{ $conflito['quando'] }}</span> — {{ $conflito['nomes'] }}
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-2 text-sm text-warning-ink">
                                Deixar os dois no mesmo horário é possível. Confirma?
                            </p>
                        </div>
                    @endif

                    <div class="flex justify-end gap-2 border-t border-line pt-4">
                        <button type="button" wire:click="fechar"
                                class="inline-flex min-h-[44px] items-center rounded-md border border-line px-4 text-sm font-medium text-ink hover:bg-canvas">
                            Cancelar
                        </button>

                        @if ($conflitos)
                            <button type="button" wire:click="salvar(true)"
                                    class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-warning px-4 text-sm font-medium text-white hover:opacity-90">
                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                                Marcar mesmo assim
                            </button>
                        @else
                            <button type="submit"
                                    class="inline-flex min-h-[44px] items-center rounded-md bg-primary px-4 text-sm font-medium text-white hover:bg-primary-hover">
                                {{ $this->remarcando() ? 'Remarcar' : 'Agendar' }}
                            </button>
                        @endif
                    </div>
                </form>
            @endif
        </div>
    </div>
@endif
</div>
