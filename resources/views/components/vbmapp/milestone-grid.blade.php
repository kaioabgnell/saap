@props(['grade', 'travada' => false])

{{-- Grade de lançamento retroativo: o gráfico de marcos do laudo, clicável.

     A marcação é a mesma do `milestone-chart` de propósito — duas linhas de
     meia altura por marco, compartilhando as classes `.grafico-marcos` de
     `pdf/relatorio/_estilo.blade.php`. O psicólogo tem de reconhecer aqui o
     gráfico que vai sair no PDF; se as duas telas divergissem visualmente, a
     conferência contra o papel perderia o sentido.

     O que muda em relação ao laudo:
       · a célula tem 48px em vez de 13px, porque aqui ela é alvo de clique;
       · só a metade de cima entra na ordem de tabulação — uma parada de
         teclado por marco, não duas;
       · pendente e zero se distinguem à distância (ver `.grade-lancamento`),
         o que no laudo não é necessário porque lá não existe pendente. --}}
<table class="grafico-marcos"
       aria-label="Grade de lançamento do nível {{ $grade->level }}">
    <tbody>
        @foreach ($grade->positionsTopDown() as $posicao)
            @foreach (['top', 'bottom'] as $metade)
                <tr>
                    @if ($metade === 'top')
                        <th rowspan="2" class="eixo">{{ $posicao }}</th>
                    @endif

                    @foreach ($grade->columns as $indice => $coluna)
                        @php
                            $celula = collect($coluna['cells'])->firstWhere('position', $posicao);
                            $estado = $celula['state'];
                            $temMeio = $celula['has_half_point'];

                            $preenchimento = match (true) {
                                $estado === 'full' => 'cheia',
                                $estado === 'half' && $metade === 'bottom' => 'meia',
                                $estado === 'pending' => 'pendente',
                                default => 'vazia',
                            };

                            $dados = \Illuminate\Support\Js::from([
                                'area' => $coluna['name'],
                                'code' => $celula['code'],
                                'statement' => $celula['statement'],
                                'score' => \App\Domain\Vbmapp\Chart\ChartGrid::rotuloDoEstado($estado),
                                'answer' => $temMeio ? '' : 'Este marco não admite meio ponto.',
                                'position' => $posicao,
                            ]);
                        @endphp
                        <td class="celula {{ $preenchimento }} {{ $metade }}"
                            x-on:mouseenter="mostrar($event, {{ $dados }})"
                            x-on:mousemove="reposicionar($event)"
                            x-on:mouseleave="esconder()"
                            @if ($metade === 'top')
                                data-celula
                                data-coluna="{{ $indice }}"
                                data-marco="{{ $posicao }}"
                                tabindex="0"
                                aria-label="{{ $celula['label'] }}{{ $temMeio ? '' : ' (sem meio ponto)' }}"
                                x-on:focus="mostrar($event, {{ $dados }})"
                                x-on:blur="esconder()"
                                x-on:keydown.escape="esconder(); fecharPopover()"
                            @endif
                            @unless ($travada)
                                x-on:click="aoClicar($event, {{ $celula['item_id'] }}, '{{ $metade }}', {{ $temMeio ? 'true' : 'false' }})"
                                @if ($metade === 'top')
                                    x-on:keydown="aoDigitar($event, {{ $celula['item_id'] }}, {{ $temMeio ? 'true' : 'false' }}, {{ $indice }}, {{ $posicao }}); mover($event, {{ $indice }}, {{ $posicao }})"
                                @endif
                            @endunless></td>
                    @endforeach
                </tr>
            @endforeach
        @endforeach

        <tr class="rodape-areas">
            <th></th>
            @foreach ($grade->columns as $coluna)
                <th class="rotulo-area">{{ $coluna['short_name'] }}</th>
            @endforeach
        </tr>
    </tbody>
</table>
