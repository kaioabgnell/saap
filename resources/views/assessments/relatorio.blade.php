@php $d = $payload->toArray(); @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-ink">Relatório VB-MAPP</h2>
            <div class="flex items-center gap-3">
                @if ($snapshot->pdf_path)
                    <a href="{{ route('avaliacoes.relatorio.pdf', $assessment) }}"
                       class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">
                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                        Baixar PDF
                    </a>
                @else
                    <span class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-canvas px-4 py-2 text-sm text-ink-muted">
                        <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                        Gerando PDF…
                    </span>
                @endif
            </div>
        </div>
    </x-slot>

    @include('pdf.relatorio._estilo')

    {{-- Ajustes que valem SÓ na tela. Ficam aqui, e não em _estilo, porque
         aquele arquivo é compartilhado com o dompdf, onde largura fluida e
         tooltip não existem — e onde o gráfico é dimensionado para o A4. --}}
    <style>
        .grafico-tela .grafico-marcos { width: 100%; table-layout: fixed; min-width: 520px; }
        .grafico-tela .grafico-marcos .celula { width: auto; height: 13px; }
        .grafico-tela .grafico-marcos .eixo { width: 26px; font-size: 10px; }
        .grafico-tela .grafico-marcos .rotulo-area { font-size: 10px; padding-top: 7px; }

        /* O alvo do mouse é a célula; o realce precisa aparecer por cima da
           cor de preenchimento, então usa sombra interna em vez de fundo. */
        .grafico-tela .grafico-marcos .celula[tabindex],
        .grafico-tela .grafico-marcos .celula[x-on\:mouseenter] { cursor: help; }
        .grafico-tela .grafico-marcos .celula:hover,
        .grafico-tela .grafico-marcos .celula:focus-visible {
            outline: none;
            box-shadow: inset 0 0 0 2px #4338CA;
        }
    </style>

    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Identificação --}}
            <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-lg font-semibold text-ink">{{ $d['aprendiz']['nome'] }}</p>
                        <p class="text-sm text-ink-muted">{{ $d['aprendiz']['idade_na_aplicacao'] }} na data da aplicação</p>
                    </div>
                    @if (($d['aplicacao']['modo'] ?? 'aplicacao') === 'transcricao')
                        <span class="rounded-full bg-warning-soft px-3 py-1 text-xs font-semibold text-warning-ink">
                            <i class="fa-solid fa-file-pen" aria-hidden="true"></i> Transcrição — somente leitura
                        </span>
                    @else
                        <span class="rounded-full bg-success-soft px-3 py-1 text-xs font-semibold text-success-ink">
                            <i class="fa-solid fa-lock" aria-hidden="true"></i> Concluída — somente leitura
                        </span>
                    @endif
                </div>

                <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-3 border-t border-line pt-4 sm:grid-cols-4">
                    @foreach ([
                        'Aplicação' => \Carbon\Carbon::parse($d['aplicacao']['data'])->format('d/m/Y'),
                        'Aplicador' => $d['aplicador']['nome'],
                        'Registro' => $d['aplicador']['registro'] ?? '—',
                        'Clínica' => $d['aplicador']['clinica']['nome'] ?? '—',
                    ] as $rotulo => $valor)
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-ink-subtle">{{ $rotulo }}</dt>
                            <dd class="mt-0.5 text-sm font-medium text-ink">{{ $valor }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Resumo --}}
            <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                <h3 class="text-base font-semibold text-ink">Resumo</h3>
                <table class="mt-3 w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-xs uppercase tracking-wider text-ink-subtle">
                            <th class="py-2">Nível</th>
                            <th class="py-2 text-right">Marcos</th>
                            <th class="py-2 text-right">Respondidos</th>
                            <th class="py-2 text-right">Pontuação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($d['niveis'] as $nivel)
                            <tr class="border-b border-line">
                                <td class="py-2 text-ink">Nível {{ $nivel['nivel'] }}</td>
                                <td class="py-2 text-right tabular-nums text-ink-muted">{{ $nivel['total'] }}</td>
                                <td class="py-2 text-right tabular-nums text-ink-muted">{{ $nivel['respondidos'] }}</td>
                                <td class="py-2 text-right font-semibold tabular-nums text-ink">{{ str_replace('.', ',', (string) $nivel['pontuacao']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="font-semibold">
                            <td class="py-2 text-ink">Total</td>
                            <td class="py-2 text-right tabular-nums text-ink">{{ $d['total']['marcos'] }}</td>
                            <td class="py-2 text-right tabular-nums text-ink">{{ $d['total']['respondidos'] }}</td>
                            <td class="py-2 text-right tabular-nums text-ink">{{ str_replace('.', ',', (string) $d['total']['pontuacao']) }}</td>
                        </tr>
                    </tbody>
                </table>

                {{-- De onde vieram os dados. Um laudo transcrito não pode ser
                     indistinguível de um aplicado no sistema: a ausência de
                     exemplares por marco pareceria omissão do aplicador, e não
                     o que é — detalhe que ficou no formulário de papel. --}}
                @if (($d['aplicacao']['modo'] ?? 'aplicacao') === 'transcricao')
                    <p class="mt-4 rounded-md border-l-4 border-warning bg-warning-soft px-4 py-3 text-sm text-warning-ink">
                        <strong>Resultados transcritos</strong> de aplicação em papel realizada em
                        {{ \Carbon\Carbon::parse($d['aplicacao']['data'])->format('d/m/Y') }}.
                        O registro de exemplares por marco permaneceu no formulário original.
                    </p>
                @endif
            </div>

            {{-- Gráfico por nível --}}
            @foreach ($d['niveis'] as $nivel)
                @php $chart = $payload->chartFor($nivel['nivel']); @endphp
                <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-ink">Nível {{ $nivel['nivel'] }} — gráfico de marcos</h3>

                    <div class="grafico-tela relative mt-3" x-data="graficoMarcos">
                        <div class="overflow-x-auto">
                            <x-vbmapp.milestone-chart :chart="$chart" interativo />
                        </div>

                        {{-- Um tooltip por gráfico, movido até o cursor — e não
                             um por célula, que seriam até 65 elementos parados
                             no DOM só esperando um hover. --}}
                        <div x-show="aberto" x-cloak
                             x-bind:style="`left:${x}px; top:${y}px`"
                             class="pointer-events-none fixed z-50 w-[19rem] max-w-[calc(100vw-2rem)] rounded-lg border border-line bg-surface p-3 text-left shadow-xl"
                             role="tooltip">
                            <p class="text-[11px] font-medium uppercase tracking-wider text-ink-subtle">
                                <span x-text="dados.area"></span>
                            </p>
                            <p class="mt-0.5 text-[11px] text-ink-muted">
                                Marco <span x-text="dados.position"></span> ·
                                <span class="font-mono text-primary" x-text="dados.code"></span>
                            </p>
                            <p class="mt-1.5 text-sm leading-snug text-ink" x-text="dados.statement"></p>
                            <p class="mt-2 border-t border-line pt-2 text-xs">
                                <span class="font-semibold text-ink">Pontuação:</span>
                                <span class="text-ink-muted" x-text="dados.score"></span>
                            </p>
                            <p class="mt-1 text-xs" x-show="dados.answer">
                                <span class="font-semibold text-ink">Registrado:</span>
                                <span class="text-ink-muted" x-text="dados.answer"></span>
                            </p>
                        </div>
                    </div>

                    <div class="legenda mt-2">
                        <span><i class="cheia"></i>1 ponto</span>
                        <span><i class="meia"><b></b></i>½ ponto</span>
                        <span><i class="vazia"></i>0 ponto</span>
                        <span><i class="pendente"></i>não respondido</span>
                    </div>

                    <h4 class="mt-5 text-sm font-semibold text-ink">Pontuação por área</h4>
                    <table class="mt-2 w-full text-sm">
                        <tbody>
                            @foreach ($nivel['areas'] as $area)
                                <tr class="border-b border-line">
                                    <td class="py-1.5 text-ink">{{ $area['name'] }}</td>
                                    <td class="py-1.5 text-right tabular-nums text-ink">
                                        {{ str_replace('.', ',', (string) $area['pontuacao']) }} <span class="text-ink-subtle">de 5</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach

            {{-- Detalhamento --}}
            @foreach ($d['niveis'] as $nivel)
                <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-ink">Nível {{ $nivel['nivel'] }} — detalhamento</h3>

                    @foreach ($nivel['areas'] as $area)
                        <h4 class="mt-5 text-sm font-semibold text-primary">{{ $area['name'] }}</h4>
                        <ul class="mt-2 divide-y divide-line">
                            @foreach ($area['marcos'] as $marco)
                                <li class="py-2 text-sm">
                                    <div class="flex items-start gap-2">
                                        <span @class([
                                            'flex h-6 w-6 flex-none items-center justify-center rounded-full text-xs font-semibold',
                                            'bg-success-soft text-success-ink' => $marco['respondido'] && $marco['score'] >= 1,
                                            'bg-warning-soft text-warning-ink' => $marco['respondido'] && $marco['score'] >= 0.5 && $marco['score'] < 1,
                                            'bg-canvas text-ink-subtle' => ! $marco['respondido'] || $marco['score'] < 0.5,
                                        ])>
                                            {{ $marco['respondido'] ? ($marco['score'] >= 1 ? '1' : ($marco['score'] >= 0.5 ? '½' : '0')) : '—' }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-ink"><span class="font-mono text-xs text-primary">{{ $marco['codigo'] }}</span> {{ $marco['enunciado'] }}</p>
                                            @if ($marco['sobrescrito'])
                                                <p class="mt-1 inline-block rounded bg-warning-soft px-2 py-0.5 text-[11px] text-warning-ink">
                                                    pontuação ajustada pelo aplicador{{ $marco['motivo_sobrescrita'] ? ': '.$marco['motivo_sobrescrita'] : '' }}
                                                </p>
                                            @endif
                                            @if ($marco['exemplares'] !== [])
                                                <p class="mt-0.5 text-xs text-ink-muted">Registrado: {{ implode(', ', $marco['exemplares']) }}</p>
                                            @endif
                                            @if ($marco['observacoes'])
                                                <p class="mt-0.5 text-xs text-ink-muted">Obs.: {{ $marco['observacoes'] }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>
            @endforeach

            @if ($d['observacoes'])
                <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-ink">Observações do aplicador</h3>
                    <p class="mt-2 text-sm text-ink-muted">{{ $d['observacoes'] }}</p>
                </div>
            @endif

            {{-- Carimbo de integridade --}}
            <div class="rounded-lg border border-line bg-canvas p-4 text-xs text-ink-muted">
                <p>Relatório gerado em {{ $snapshot->generated_at->format('d/m/Y H:i') }}.</p>
                <p class="mt-1">Verificação de integridade (SHA-256):</p>
                <p class="mt-0.5 break-all font-mono text-[10px]">{{ $snapshot->content_hash }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
