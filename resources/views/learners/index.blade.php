<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-ink">Aprendizes</h2>
            <a href="{{ route('aprendizes.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Novo aprendiz
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg border border-line bg-surface shadow-sm">
                <ul class="divide-y divide-line">
                    @forelse ($learners as $learner)
                        <li>
                            <a href="{{ route('aprendizes.show', $learner) }}" class="flex items-center gap-4 px-5 py-4 hover:bg-canvas">
                                @if ($learner->photoUrl('miniatura'))
                                    <img src="{{ $learner->photoUrl('miniatura') }}" alt="" class="h-10 w-10 flex-none rounded-full object-cover">
                                @else
                                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-primary-soft">
                                        <i class="fa-solid fa-child text-primary" aria-hidden="true"></i>
                                    </span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium text-ink">{{ $learner->name }}</p>
                                    <p class="text-sm text-ink-muted">{{ $learner->currentAge()->format() }}</p>
                                </div>
                                <i class="fa-solid fa-chevron-right text-ink-subtle" aria-hidden="true"></i>
                            </a>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-sm text-ink-muted">
                            Nenhum aprendiz cadastrado ainda.
                        </li>
                    @endforelse
                </ul>
            </div>

            <div class="mt-4">
                {{ $learners->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
