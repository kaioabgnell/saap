{{-- Laudo definitivo. Sem marca de rascunho — este é o documento final. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Relatório VB-MAPP — {{ $payload->toArray()['aprendiz']['nome'] }}</title>
<style>
    @page { margin: 92px 36px 60px 36px; }

    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #0F172A; margin: 0; }

    header { position: fixed; top: -82px; left: 0; right: 0; height: 72px; }
    footer {
        position: fixed; bottom: -50px; left: 0; right: 0; height: 40px;
        font-size: 7.5px; color: #64748B;
        border-top: 1px solid #E2E8F0; padding-top: 6px;
    }

    table.cabecalho { width: 100%; border-collapse: collapse; }
    table.cabecalho td { vertical-align: top; padding: 0; }

    .marca { font-size: 14px; font-weight: bold; color: #4338CA; letter-spacing: 2px; }
    .marca-sub { font-size: 8px; color: #94A3B8; }
    .selo-final {
        display: inline-block; background: #ECFDF5; border: 1px solid #059669;
        color: #065F46; font-weight: bold; font-size: 9px; padding: 3px 8px; letter-spacing: 1px;
    }
    .selo-transcricao {
        display: inline-block; background: #FFFBEB; border: 1px solid #D97706;
        color: #92400E; font-weight: bold; font-size: 8px; padding: 3px 6px;
        letter-spacing: 0.5px; line-height: 1.3; text-align: center;
    }

    .info-linha { font-size: 9px; color: #475569; margin: 1px 0; }
    .info-linha b { color: #0F172A; }

    h1.secao {
        font-size: 12px; color: #4338CA; background: #EEF2FF;
        padding: 5px 8px; margin: 16px 0 8px 0; border-left: 3px solid #4338CA;
    }
    h2.subsecao { font-size: 11px; color: #0F172A; margin: 12px 0 4px 0; }

    table.dados { width: 100%; border-collapse: collapse; font-size: 9px; }
    table.dados th, table.dados td {
        text-align: left; padding: 4px 6px; border-bottom: 1px solid #E2E8F0;
    }
    table.dados th { background: #F8FAFC; color: #475569; font-weight: bold; }
    table.dados td.num { text-align: right; }

    .marco-linha { font-size: 9px; padding: 4px 6px; border-bottom: 1px solid #F1F5F9; }
    .marco-codigo { font-weight: bold; color: #4338CA; }
    .marco-score { font-weight: bold; }
    .score-1 { color: #059669; }
    .score-meio { color: #D97706; }
    .score-0 { color: #94A3B8; }
    .exemplares { color: #475569; font-size: 8.5px; }
    .ajustado { color: #92400E; background: #FFFBEB; font-size: 8px; padding: 1px 4px; }

    .procedencia {
        margin: 10px 0 0 0; padding: 6px 8px;
        background: #FFFBEB; border-left: 3px solid #D97706;
        font-size: 8.5px; color: #92400E;
    }

    .carimbo {
        margin-top: 20px; padding: 8px; background: #F8FAFC;
        border: 1px solid #E2E8F0; font-size: 8px; color: #64748B;
    }
    .hash { font-family: 'DejaVu Sans Mono', monospace; font-size: 7px; word-break: break-all; }
</style>
@include('pdf.relatorio._estilo')
</head>
<body>
@php $d = $payload->toArray(); @endphp

<header>
    <table class="cabecalho">
        <tr>
            <td style="width: 38%;">
                <span class="marca">SAAP</span><br>
                <span class="marca-sub">Sistema de Avaliação de Aprendiz</span>
                @if ($d['aplicador']['clinica']['nome'])
                    <br><span class="info-linha">{{ $d['aplicador']['clinica']['nome'] }}</span>
                @endif
            </td>
            <td style="width: 42%;">
                <div class="info-linha"><b>{{ $d['aprendiz']['nome'] }}</b> — {{ $d['aprendiz']['idade_na_aplicacao'] }}</div>
                <div class="info-linha">Aplicação em {{ \Carbon\Carbon::parse($d['aplicacao']['data'])->format('d/m/Y') }}</div>
                <div class="info-linha">{{ $d['aplicador']['nome'] }}@if ($d['aplicador']['registro']) · {{ $d['aplicador']['registro'] }}@endif</div>
            </td>
            <td style="width: 20%; text-align: right;">
                {{-- Um laudo transcrito não pode se apresentar como aplicado
                     no sistema. Quem lê precisa saber, já no cabeçalho de
                     todas as páginas, de onde os dados vieram. --}}
                @if (($d['aplicacao']['modo'] ?? 'aplicacao') === 'transcricao')
                    {{-- Duas linhas de propósito: a célula do cabeçalho tem 20%
                         da largura e "RELATÓRIO — TRANSCRIÇÃO" numa linha só
                         quebrava com o travessão pendurado no fim. --}}
                    <span class="selo-transcricao">RELATÓRIO<br>TRANSCRIÇÃO</span>
                @else
                    <span class="selo-final">RELATÓRIO FINAL</span>
                @endif
            </td>
        </tr>
    </table>
</header>

<footer>
    Relatório VB-MAPP gerado em {{ $geradoEm }} — conteúdo licenciado do instrumento
</footer>

<h1 class="secao">Resumo</h1>

<table class="dados">
    <tr><th>Nível</th><th class="num">Marcos</th><th class="num">Respondidos</th><th class="num">Pontuação</th></tr>
    @foreach ($d['niveis'] as $nivel)
        <tr>
            <td>Nível {{ $nivel['nivel'] }}</td>
            <td class="num">{{ $nivel['total'] }}</td>
            <td class="num">{{ $nivel['respondidos'] }}</td>
            <td class="num"><b>{{ str_replace('.', ',', (string) $nivel['pontuacao']) }}</b></td>
        </tr>
    @endforeach
    <tr>
        <th>Total</th>
        <th class="num">{{ $d['total']['marcos'] }}</th>
        <th class="num">{{ $d['total']['respondidos'] }}</th>
        <th class="num">{{ str_replace('.', ',', (string) $d['total']['pontuacao']) }}</th>
    </tr>
</table>

@if (($d['aplicacao']['modo'] ?? 'aplicacao') === 'transcricao')
    <p class="procedencia">
        <b>Resultados transcritos</b> de aplicação em papel realizada em
        {{ \Carbon\Carbon::parse($d['aplicacao']['data'])->format('d/m/Y') }}.
        O registro de exemplares por marco permaneceu no formulário original.
    </p>
@endif

@foreach ($d['niveis'] as $nivel)
    @php $chart = $payload->chartFor($nivel['nivel']); @endphp

    <h1 class="secao">Nível {{ $nivel['nivel'] }} — gráfico de marcos</h1>

    <x-vbmapp.milestone-chart :chart="$chart" />

    <div class="legenda">
        <span><i class="cheia"></i>1 ponto</span>
        <span><i class="meia"><b></b></i>½ ponto</span>
        <span><i class="vazia"></i>0 ponto</span>
        <span><i class="pendente"></i>não respondido</span>
    </div>

    {{-- Tabela numérica equivalente ao gráfico: cor nunca é o único
         portador de informação. --}}
    <h2 class="subsecao">Pontuação por área — nível {{ $nivel['nivel'] }}</h2>
    <table class="dados">
        <tr><th>Área</th><th class="num">Pontuação</th><th class="num">de</th></tr>
        @foreach ($nivel['areas'] as $area)
            <tr>
                <td>{{ $area['name'] }}</td>
                <td class="num">{{ str_replace('.', ',', (string) $area['pontuacao']) }}</td>
                <td class="num">5</td>
            </tr>
        @endforeach
    </table>
@endforeach

@foreach ($d['niveis'] as $nivel)
    <h1 class="secao">Nível {{ $nivel['nivel'] }} — detalhamento</h1>

    @foreach ($nivel['areas'] as $area)
        <h2 class="subsecao">{{ $area['name'] }}</h2>

        @foreach ($area['marcos'] as $marco)
            <div class="marco-linha">
                <span class="marco-codigo">{{ $marco['codigo'] }}</span>
                <span class="marco-score {{ $marco['score'] >= 1 ? 'score-1' : ($marco['score'] >= 0.5 ? 'score-meio' : 'score-0') }}">
                    [{{ $marco['respondido'] ? ($marco['score'] >= 1 ? '1' : ($marco['score'] >= 0.5 ? '½' : '0')) : '—' }}]
                </span>
                {{ $marco['enunciado'] }}
                @if ($marco['sobrescrito'])
                    <span class="ajustado">pontuação ajustada pelo aplicador{{ $marco['motivo_sobrescrita'] ? ': '.$marco['motivo_sobrescrita'] : '' }}</span>
                @endif
                @if ($marco['exemplares'] !== [])
                    <br><span class="exemplares">Registrado: {{ implode(', ', $marco['exemplares']) }}</span>
                @endif
                @if ($marco['observacoes'])
                    <br><span class="exemplares">Obs.: {{ $marco['observacoes'] }}</span>
                @endif
            </div>
        @endforeach
    @endforeach
@endforeach

@if ($d['observacoes'])
    <h1 class="secao">Observações do aplicador</h1>
    <p style="font-size: 9.5px; color: #334155;">{{ $d['observacoes'] }}</p>
@endif

<div class="carimbo">
    Relatório gerado em {{ $geradoEm }}.<br>
    Verificação de integridade (SHA-256):<br>
    <span class="hash">{{ $hash }}</span>
</div>

</body>
</html>
