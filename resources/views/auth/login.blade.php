<x-guest-layout>
    <x-slot name="titulo">Entrar</x-slot>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <header>
        <h2 class="text-[1.75rem] font-semibold leading-tight tracking-[-0.02em] text-ink">
            Entrar no SAAP
        </h2>
        <p class="mt-2 text-sm text-ink-muted">
            Use o e-mail cadastrado para continuar de onde parou.
        </p>
    </header>

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="E-mail" class="text-ink" />
            <x-text-input id="email" name="email" type="email" :value="old('email')"
                          required autofocus autocomplete="username"
                          placeholder="voce@clinica.com.br"
                          class="mt-1.5 block h-11 w-full px-3.5 text-sm" />
            <x-input-error for="email" :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div>
            <div class="flex items-baseline justify-between">
                <x-input-label for="password" value="Senha" class="text-ink" />
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}"
                       class="rounded text-[13px] font-medium text-primary hover:text-primary-hover hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        Esqueceu a senha?
                    </a>
                @endif
            </div>

            {{-- O olho de revelar a senha é do Alpine, não de um pacote: são
                 seis linhas e evita uma dependência para um único campo. --}}
            <div class="relative mt-1.5" x-data="{ visivel: false }">
                <x-text-input id="password" name="password"
                              x-bind:type="visivel ? 'text' : 'password'"
                              type="password"
                              required autocomplete="current-password"
                              class="block h-11 w-full px-3.5 pr-11 text-sm" />
                <button type="button" x-on:click="visivel = ! visivel"
                        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-md text-ink-subtle hover:text-ink-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    <i class="fa-solid" x-bind:class="visivel ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                    <span class="sr-only" x-text="visivel ? 'Ocultar senha' : 'Mostrar senha'">Mostrar senha</span>
                </button>
            </div>
            <x-input-error for="password" :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <label for="remember_me" class="flex min-h-[44px] items-center gap-2.5">
            <input id="remember_me" name="remember" type="checkbox"
                   class="h-4 w-4 rounded border-line text-primary focus:ring-primary">
            <span class="text-sm text-ink-muted">Manter conectado neste aparelho</span>
        </label>

        <button type="submit"
                class="flex h-11 w-full items-center justify-center gap-2 rounded-md bg-primary text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
            Entrar
            <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
        </button>
    </form>

    @if (Route::has('register'))
        <p class="mt-8 text-sm text-ink-muted">
            Ainda não tem conta?
            <a href="{{ route('register') }}"
               class="rounded font-semibold text-primary hover:text-primary-hover hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                Criar uma conta
            </a>
        </p>
    @endif
</x-guest-layout>
