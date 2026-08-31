<x-guest-layout>
    <x-slot name="titulo">Criar conta</x-slot>

    <header>
        <h2 class="text-[1.75rem] font-semibold leading-tight tracking-[-0.02em] text-ink">
            Criar sua conta
        </h2>
        <p class="mt-2 text-sm text-ink-muted">
            Leva um minuto. Depois é só cadastrar o primeiro aprendiz.
        </p>
    </header>

    <form class="mt-8 space-y-5" method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="'Nome'" />
            <x-text-input id="name" class="mt-1.5 block h-11 w-full px-3.5 text-sm" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error for="name" :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="'E-mail'" />
            <x-text-input id="email" class="mt-1.5 block h-11 w-full px-3.5 text-sm" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error for="email" :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Telefone -->
        <div>
            <x-input-label for="phone" value="Telefone" />
            <x-text-input id="phone" class="mt-1.5 block h-11 w-full px-3.5 text-sm" type="tel" name="phone" :value="old('phone')" required autocomplete="tel" placeholder="(00) 00000-0000" />
            <x-input-error for="phone" :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="'Senha'" />

            <x-text-input id="password" class="mt-1.5 block h-11 w-full px-3.5 text-sm"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error for="password" :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div>
            <x-input-label for="password_confirmation" :value="'Confirmação de senha'" />

            <x-text-input id="password_confirmation" class="mt-1.5 block h-11 w-full px-3.5 text-sm"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error for="password_confirmation" :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">
            Criar conta
            <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
        </x-primary-button>
    </form>

    <p class="mt-8 text-sm text-ink-muted">
        Já tem conta?
        <a href="{{ route('login') }}"
           class="rounded font-semibold text-primary hover:text-primary-hover hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
            Entrar
        </a>
    </p>
</x-guest-layout>
