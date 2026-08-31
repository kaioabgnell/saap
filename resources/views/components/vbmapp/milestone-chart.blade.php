@props(['chart', 'interativo' => false])

{{-- Gráfico de marcos, padrão de refs/Grafico VB-MAPP.xlsx.

     Montado como <table> com cada marco em DUAS linhas de meia altura: a
     inferior sozinha é ½ ponto, as duas juntas são 1 ponto. É o único jeito de
     a mesma marcação servir HTML e dompdf — o dompdf não tem gradiente,
     pseudo-elemento nem grid. Ver 03-design-system.md.

     `interativo` liga o tooltip e o foco por teclado. Fica DESLIGADO no PDF:
     lá os atributos do Alpine seriam peso morto e `tabindex` não significa
     nada em papel. --}}
<table class="grafico-marcos" role="img"
       aria-label="Gráfico de marcos do nível {{ $chart->level }}">
    <tbody>
        @foreach ($chart->positionsTopDown() as $posicao)
            @foreach (['top', 'bottom'] as $metade)
                <tr>
                    @if ($metade === 'top')
                        <th rowspan="2" class="eixo">{{ $posicao }}</th>
                    @endif

                    @foreach ($chart->columns as $coluna)
                        @php
                            $celula = collect($coluna['cells'])->firstWhere('position', $posicao);
                            $estado = $celula['state'] ?? 'pending';

                            // Metade de cima só pinta quando vale 1 ponto.
                            // Metade de baixo pinta em ½ e em 1 — a diferença
                            // entre meio e inteiro é a ALTURA preenchida, não
                            // a cor.
                            $preenchimento = match (true) {
                                $estado === 'full' => 'cheia',
                                $estado === 'half' && $metade === 'bottom' => 'meia',
                                $estado === 'pending' => 'pendente',
                                default => 'vazia',
                            };

                            // As duas metades do mesmo marco descrevem o mesmo
                            // marco: parar o mouse em qualquer uma abre o mesmo
                            // tooltip. Só a de cima entra na ordem de tabulação,
                            // senão seriam dois paradas de teclado por marco.
                            $dados = $interativo && $celula ? Js::from([
                                'area' => $celula['area'] ?? '',
                                'code' => $celula['code'] ?? '',
                                'statement' => $celula['statement'] ?? '',
                                'score' => $celula['score'] ?? '',
                                'answer' => $celula['answer'] ?? '',
                                'position' => $celula['position'] ?? null,
                            ]) : null;
                        @endphp
                        <td class="celula {{ $preenchimento }} {{ $metade }}"
                            @if ($metade === 'top') aria-label="{{ $celula['label'] ?? '' }}" @endif
                            @if ($dados)
                                x-on:mouseenter="mostrar($event, {{ $dados }})"
                                x-on:mousemove="reposicionar($event)"
                                x-on:mouseleave="esconder()"
                                @if ($metade === 'top')
                                    tabindex="0"
                                    x-on:focus="mostrar($event, {{ $dados }})"
                                    x-on:blur="esconder()"
                                    x-on:keydown.escape="esconder()"
                                @endif
                            @endif></td>
                    @endforeach
                </tr>
            @endforeach
        @endforeach

        <tr class="rodape-areas">
            <th></th>
            @foreach ($chart->columns as $coluna)
                <th class="rotulo-area">{{ $coluna['short_name'] }}</th>
            @endforeach
        </tr>
    </tbody>
</table>
