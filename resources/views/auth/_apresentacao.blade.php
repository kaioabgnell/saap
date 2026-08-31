@php
    /**
     * Perfil ILUSTRATIVO de marcos, para o gráfico do painel.
     *
     * Não é dado de ninguém: é o desenho que um perfil real tende a ter —
     * nível 1 dominado, nível 2 em curso, nível 3 mal começado. Cada valor é
     * a pontuação de uma área naquele nível (0 a 5, com meio ponto).
     *
     * O gráfico está aqui, e não um mockup de dashboard, porque ELE é o que a
     * psicóloga reconhece: as colunas por área e a meia célula âmbar do ½
     * ponto não existem em nenhum outro instrumento.
     *
     * Sem rótulo de área de propósito. O número de áreas muda de um nível
     * para outro (9, 12 e 13), e rotular nove colunas iguais nos três níveis
     * afirmaria algo falso sobre o instrumento. As faixas de nível, que são
     * verdadeiras, ficam.
     */
    $niveis = [
        ['Nível 3', [1.5, 1, 2, 1, 0, 0.5, 0, 1, 0.5]],
        ['Nível 2', [4, 3.5, 4, 3, 2.5, 2, 3, 1.5, 2.5]],
        ['Nível 1', [5, 5, 5, 5, 4.5, 4, 5, 4, 3.5]],
    ];
@endphp

<div class="flex h-full flex-col justify-between gap-6 p-9 [@media(max-height:880px)]:gap-4 [@media(max-height:880px)]:p-7 xl:gap-9 xl:p-14">

    <a href="/" class="inline-block shrink-0 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:ring-offset-4 focus-visible:ring-offset-[#0B1026]">
        <x-application-logo variante="clara" class="h-8 xl:h-9" />
        <span class="sr-only">SAAP — página inicial</span>
    </a>

    <div>
        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-[#8FA0D8]">VB-MAPP</p>

        <h1 class="mt-3 max-w-lg text-[2.15rem] font-semibold leading-[1.1] tracking-[-0.03em] text-white [@media(max-height:880px)]:text-[1.8rem] xl:text-[2.6rem]">
            Do primeiro marco<br>ao laudo assinado.
        </h1>

        <p class="mt-3.5 max-w-md text-[14.5px] leading-relaxed text-[#A9B4DA] [@media(max-height:880px)]:hidden">
            A avaliação inteira conduzida na tela: pontuação por marco, o material
            de aplicação à mão e o gráfico final montado sozinho.
        </p>

        {{-- O gráfico de marcos — a assinatura do instrumento --}}
        <figure class="mt-6 max-w-lg rounded-xl border border-white/10 bg-white/[0.04] p-4 [@media(max-height:880px)]:mt-4 xl:p-5">
            <div role="img" aria-label="Exemplo ilustrativo do gráfico de marcos do VB-MAPP: três faixas de nível, colunas por área, células cheias de 1 ponto e meias células de meio ponto.">
                @foreach ($niveis as [$rotulo, $pontuacoes])
                    <div class="flex items-center gap-3 @if (! $loop->first) mt-1.5 border-t border-white/10 pt-1.5 @endif">
                        <span class="w-[46px] shrink-0 text-[10px] font-medium tracking-wide text-[#7E8CBB]">{{ $rotulo }}</span>

                        <div class="flex flex-1 items-end gap-[5px]">
                            @foreach ($pontuacoes as $pontos)
                                <div class="flex flex-1 flex-col-reverse gap-[3px]">
                                    @for ($marco = 1; $marco <= 5; $marco++)
                                        @php
                                            $cheia = $pontos >= $marco;
                                            $meia = ! $cheia && $pontos >= $marco - 0.5;
                                        @endphp
                                        <span @class([
                                            'block h-3 [@media(max-height:880px)]:h-2.5 rounded-[2px]',
                                            'bg-[#10B981]' => $cheia,
                                            'bg-white/[0.07]' => ! $cheia,
                                        ])
                                        @if ($meia) style="background:linear-gradient(to top,#10B981 0,#10B981 50%,rgba(255,255,255,.07) 50%)" @endif></span>
                                    @endfor
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <figcaption class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-white/10 pt-3 text-[11px] text-[#8FA0D8]">
                <span class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-[2px] bg-[#10B981]"></span> 1 ponto
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-[2px]" style="background:linear-gradient(to top,#10B981 0,#10B981 50%,rgba(255,255,255,.12) 50%)"></span> meio ponto
                </span>
                <span class="ml-auto">Exemplo ilustrativo</span>
            </figcaption>
        </figure>
    </div>

    {{-- Funcionalidades e diferenciais --}}
    <ul class="grid max-w-xl gap-x-8 gap-y-4 [@media(max-height:880px)]:gap-y-3 sm:grid-cols-2">
        @foreach ([
            ['fa-list-check', '170 marcos, três níveis', 'As dezesseis áreas, com o critério do manual dentro de cada marco.'],
            ['fa-cloud-arrow-up', 'Grava enquanto você observa', 'Cada resposta é salva na hora. Não há botão de enviar.'],
            ['fa-images', 'O material na tela', 'As figuras aparecem no marco, para mostrar ao aprendiz ali mesmo.'],
            ['fa-lock', 'Laudo que não muda', 'Concluída a avaliação, o laudo é congelado.'],
        ] as [$icone, $titulo, $texto])
            <li class="flex gap-3">
                <i class="fa-solid {{ $icone }} mt-[3px] text-[13px] text-[#7C8CFF]" aria-hidden="true"></i>
                <div>
                    <p class="text-[13px] font-semibold leading-snug text-white">{{ $titulo }}</p>
                    <p class="mt-0.5 text-[12.5px] leading-snug text-[#A9B4DA]">{{ $texto }}</p>
                </div>
            </li>
        @endforeach
    </ul>

    <p class="shrink-0 text-[11px] leading-relaxed text-[#6F7CAB]">
        VB-MAPP® é obra de Mark L. Sundberg. Conteúdo do instrumento usado internamente pela licenciada.
    </p>
</div>
