<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $titulo ?? 'Entrar' }} — {{ config('app.name', 'SAAP') }}</title>

        {{-- Inter, a mesma do layout autenticado. Este arquivo carregava Figtree
             enquanto o tailwind.config declara Inter: a primeira tela do sistema
             renderizava na fonte do sistema operacional, não na do design system. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans text-ink antialiased">
        {{-- No split, a PÁGINA não rola: cada coluna rola por dentro. Sem isto,
             o painel esquerdo estica a linha do grid e a janela inteira ganha
             barra de rolagem por causa de conteúdo que é só do painel. --}}
        <div class="min-h-screen lg:grid lg:h-screen lg:min-h-0 lg:overflow-hidden lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">

            {{-- Apresentação. Escondida no celular: quem entra pelo telefone quer
                 o formulário, não a argumentação. --}}
            <aside class="relative hidden overflow-hidden bg-[#0B1026] lg:block">
                {{-- Uma única aparição do gradiente da marca, difusa atrás do
                     conteúdo. O gradiente nítido fica onde ele nasce: no check
                     da logo. --}}
                <div class="pointer-events-none absolute -left-32 -top-40 h-[36rem] w-[36rem] rounded-full opacity-[0.22] blur-3xl"
                     style="background:radial-gradient(circle,#8000FF 0%,transparent 70%)" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-48 -right-24 h-[32rem] w-[32rem] rounded-full opacity-[0.18] blur-3xl"
                     style="background:radial-gradient(circle,#0080FF 0%,transparent 70%)" aria-hidden="true"></div>

                <div class="relative h-full overflow-y-auto">
                    @include('auth._apresentacao')
                </div>
            </aside>

            {{-- Formulário --}}
            <main class="flex min-h-screen flex-col justify-center bg-surface px-6 py-10 sm:px-10 lg:min-h-0 lg:h-full lg:overflow-y-auto">
                <div class="mx-auto w-full max-w-[400px]">

                    {{-- No celular a logo aparece aqui, já que o painel some --}}
                    <a href="/" class="mb-10 inline-block rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 lg:hidden">
                        <x-application-logo class="h-8" />
                        <span class="sr-only">SAAP — página inicial</span>
                    </a>

                    {{ $slot }}
                </div>

                <p class="mx-auto mt-12 w-full max-w-[400px] text-xs text-ink-subtle">
                    © {{ date('Y') }} SAAP. Sistema de Avaliação de Aprendiz.
                </p>
            </main>
        </div>
        @livewireScripts
    </body>
</html>
