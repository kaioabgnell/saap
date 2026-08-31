<x-guest-layout>
    <x-slot name="titulo">Confirmar senha</x-slot>
    <div class="mb-4 text-sm text-gray-600">
        {{ 'Esta é uma área protegida. Confirme sua senha para continuar.' }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="'Senha'" />

            <x-text-input id="password" class="mt-1.5 block h-11 w-full px-3.5 text-sm"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error for="password" :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button>
                {{ 'Confirmar' }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
