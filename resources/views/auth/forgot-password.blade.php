<x-guest-layout>
    <x-slot name="titulo">Recuperar senha</x-slot>

    <header>
        <h2 class="text-[1.75rem] font-semibold leading-tight tracking-[-0.02em] text-ink">
            Recuperar a senha
        </h2>
        <p class="mt-2 text-sm text-ink-muted">
            Informe o e-mail da conta. Enviamos um link para você definir uma senha
            nova; ele vale por uma hora.
        </p>
    </header>

    <x-auth-session-status class="mt-6" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" name="email" type="email" :value="old('email')"
                          required autofocus placeholder="voce@clinica.com.br"
                          class="mt-1.5 block h-11 w-full px-3.5 text-sm" />
            <x-input-error for="email" :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <x-primary-button class="w-full">Enviar link de recuperação</x-primary-button>
    </form>

    <p class="mt-8 text-sm text-ink-muted">
        Lembrou a senha?
        <a href="{{ route('login') }}"
           class="rounded font-semibold text-primary hover:text-primary-hover hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
            Voltar para entrar
        </a>
    </p>
</x-guest-layout>
