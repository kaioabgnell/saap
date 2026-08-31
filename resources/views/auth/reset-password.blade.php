<x-guest-layout>
    <x-slot name="titulo">Definir nova senha</x-slot>

    <header class="mb-8">
        <h2 class="text-[1.75rem] font-semibold leading-tight tracking-[-0.02em] text-ink">
            Definir nova senha
        </h2>
        <p class="mt-2 text-sm text-ink-muted">
            Pelo menos 10 caracteres, com letra e número.
        </p>
    </header>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="'E-mail'" />
            <x-text-input id="email" class="mt-1.5 block h-11 w-full px-3.5 text-sm" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error for="email" :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="'Nova senha'" />
            <x-text-input id="password" class="mt-1.5 block h-11 w-full px-3.5 text-sm" type="password" name="password" required autocomplete="new-password" />
            <x-input-error for="password" :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="'Confirmação da nova senha'" />

            <x-text-input id="password_confirmation" class="mt-1.5 block h-11 w-full px-3.5 text-sm"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />

            <x-input-error for="password_confirmation" :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ 'Salvar nova senha' }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
