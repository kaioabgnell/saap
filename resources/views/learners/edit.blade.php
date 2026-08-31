<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-ink">Editar {{ $learner->name }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('aprendizes.update', $learner) }}" enctype="multipart/form-data"
                  class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                @csrf
                @method('PUT')

                @include('learners._form')

                <div class="mt-6 flex items-center gap-3 border-t border-line pt-6">
                    <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                        Salvar alterações
                    </button>
                    <a href="{{ route('aprendizes.show', $learner) }}" class="text-sm font-medium text-ink-muted hover:text-ink">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
