<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-ink">Lançar avaliação em papel</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">

                <p class="text-sm text-ink-muted">
                    Para aplicações do VB-MAPP que já foram feitas em papel. Você lança
                    a pontuação direto no gráfico de marcos e o sistema gera o relatório —
                    sem os exemplares registrados marco a marco, que ficaram no formulário
                    original.
                </p>

                @if (session('aviso'))
                    <p class="mt-4 rounded-md bg-warning-soft px-4 py-3 text-sm text-warning-ink" role="alert">
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        {{ session('aviso') }}
                    </p>
                @endif

                <form method="POST" action="{{ route('lancamento.store', $learner) }}" class="mt-6 space-y-6">
                    @csrf

                    @if (session('aviso'))
                        <input type="hidden" name="confirmado" value="1">
                    @endif

                    <div>
                        <x-input-label for="applied_on" value="Data da aplicação em papel" />
                        <x-text-input id="applied_on" name="applied_on" type="date" required
                                      :value="old('applied_on')"
                                      max="{{ now()->toDateString() }}"
                                      min="{{ $learner->birth_date->toDateString() }}"
                                      class="mt-1.5 block h-11 w-full max-w-xs px-3.5 text-sm" />
                        <p class="mt-1 text-xs text-ink-subtle">
                            É esta data que o relatório imprime, e é sobre ela que a idade de
                            {{ $learner->name }} é calculada.
                        </p>
                        <x-input-error for="applied_on" :messages="$errors->get('applied_on')" class="mt-1.5" />
                    </div>

                    <fieldset>
                        <legend class="text-sm font-medium text-ink">Níveis presentes no formulário</legend>
                        <p class="mt-1 text-xs text-ink-subtle">
                            Nível não marcado não entra na avaliação nem aparece no relatório.
                        </p>

                        <div class="mt-3 space-y-2">
                            @foreach ([1 => 45, 2 => 60, 3 => 65] as $nivel => $marcos)
                                <label for="nivel-{{ $nivel }}" class="flex min-h-[44px] items-center gap-2.5">
                                    <input id="nivel-{{ $nivel }}" type="checkbox" name="levels[]" value="{{ $nivel }}"
                                           @checked(in_array((string) $nivel, old('levels', []), true))
                                           class="h-4 w-4 rounded border-line text-primary focus:ring-primary">
                                    <span class="text-sm text-ink">Nível {{ $nivel }}</span>
                                    <span class="text-xs text-ink-subtle">{{ $marcos }} marcos</span>
                                </label>
                            @endforeach
                        </div>

                        <x-input-error for="levels" :messages="$errors->get('levels')" class="mt-1.5" />
                    </fieldset>

                    <div>
                        <x-input-label for="observations" value="Observações (opcional)" />
                        <textarea id="observations" name="observations" rows="3"
                                  class="mt-1.5 block w-full rounded-md border-line text-sm focus:border-primary focus:ring-primary"
                                  placeholder="Ex.: transcrito do formulário arquivado na pasta da família.">{{ old('observations') }}</textarea>
                        <x-input-error for="observations" :messages="$errors->get('observations')" class="mt-1.5" />
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-line pt-5">
                        <a href="{{ route('aprendizes.show', $learner) }}"
                           class="min-h-[44px] rounded-md px-4 py-2 text-sm font-medium text-ink-muted hover:text-ink">
                            Voltar
                        </a>
                        <button type="submit"
                                class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                            <i class="fa-solid fa-table-cells" aria-hidden="true"></i>
                            Abrir gráfico para lançamento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
