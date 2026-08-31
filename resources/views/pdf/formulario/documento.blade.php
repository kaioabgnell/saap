{{-- Formulário impresso — folha de estilo própria, independente do Tailwind.
     dompdf implementa CSS 2.1: layout em table/float, cores literais em hex,
     sem Font Awesome (glifos de texto), imagens por caminho de arquivo. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Formulário — {{ $payload->learnerName }} — Nível {{ $payload->level }}</title>
<style>
    @page {
        margin: 90px 36px 60px 36px;
    }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10px;
        color: #0F172A;
        margin: 0;
    }

    /* Cabeçalho e rodapé fixos, repetidos em toda página — recurso do dompdf. */
    header {
        position: fixed;
        top: -80px;
        left: 0;
        right: 0;
        height: 70px;
    }

    footer {
        position: fixed;
        bottom: -50px;
        left: 0;
        right: 0;
        height: 40px;
        font-size: 8px;
        color: #64748B;
        border-top: 1px solid #E2E8F0;
        padding-top: 6px;
    }

    .marca-dagua {
        position: fixed;
        top: 260px;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 60px;
        color: #F1F5F9;
        font-weight: bold;
        z-index: -1;
        letter-spacing: 4px;
    }

    table.cabecalho { width: 100%; border-collapse: collapse; }
    table.cabecalho td { vertical-align: top; padding: 0; }

    .marca { font-size: 14px; font-weight: bold; color: #4338CA; letter-spacing: 2px; }
    .marca-sub { font-size: 8px; color: #94A3B8; }

    .rascunho {
        display: inline-block;
        background: #FFFBEB;
        border: 1px solid #D97706;
        color: #92400E;
        font-weight: bold;
        font-size: 9px;
        padding: 3px 8px;
        letter-spacing: 1px;
    }

    .info-linha { font-size: 9px; color: #475569; margin: 1px 0; }
    .info-linha b { color: #0F172A; }

    h2.area {
        font-size: 12px;
        color: #4338CA;
        background: #EEF2FF;
        padding: 5px 8px;
        margin: 14px 0 6px 0;
        border-left: 3px solid #4338CA;
    }

    table.marco {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
        border: 1px solid #E2E8F0;
    }

    table.marco td { padding: 6px 8px; vertical-align: top; }

    td.codigo {
        width: 46px;
        font-weight: bold;
        color: #4338CA;
        background: #F8FAFC;
        border-right: 1px solid #E2E8F0;
    }

    td.pontuacao {
        width: 34px;
        text-align: center;
        font-weight: bold;
        font-size: 13px;
        border-left: 1px solid #E2E8F0;
    }

    .pontuacao-cheia { background: #ECFDF5; color: #059669; }
    .pontuacao-meia { background: #FFFBEB; color: #D97706; }
    .pontuacao-zero { background: #F8FAFC; color: #94A3B8; }
    .pontuacao-vazia { background: #FFFFFF; color: #CBD5E1; border: 1px dashed #CBD5E1; }

    .enunciado { font-size: 10px; line-height: 1.4; }
    .selo-obs { font-size: 8px; color: #92400E; background: #FFFBEB; padding: 1px 5px; }

    .criterios { font-size: 8.5px; color: #475569; margin-top: 4px; }
    .criterios b { color: #059669; }
    .criterios .meio b { color: #D97706; }

    .exemplares { font-size: 9px; color: #334155; margin-top: 4px; }
    .exemplares b { color: #0F172A; }

    .checklist-item {
        display: inline-block;
        font-size: 8.5px;
        border: 1px solid #CBD5E1;
        padding: 2px 6px;
        margin: 2px 3px 0 0;
    }
    .checklist-item .caixa { border: 1px solid #94A3B8; width: 8px; height: 8px; display: inline-block; margin-right: 3px; }

    .observacoes { font-size: 8.5px; color: #64748B; margin-top: 4px; border-top: 1px dashed #E2E8F0; padding-top: 3px; }
    .linha-vazia { border-bottom: 1px solid #CBD5E1; display: block; height: 12px; margin-top: 4px; }
</style>
</head>
<body>

<div class="marca-dagua">RASCUNHO</div>

<header>
    <table class="cabecalho">
        <tr>
            <td style="width: 40%;">
                <span class="marca">SAAP</span><br>
                <span class="marca-sub">Sistema de Avaliação de Aprendiz</span>
                @if ($payload->clinicName)
                    <br><span class="info-linha">{{ $payload->clinicName }}</span>
                @endif
            </td>
            <td style="width: 40%;">
                <div class="info-linha"><b>{{ $payload->learnerName }}</b> — {{ $payload->ageAtApplication }}</div>
                <div class="info-linha">Aplicação em {{ $payload->appliedOn }} · {{ $payload->applicatorName }}</div>
                <div class="info-linha">Nível {{ $payload->level }} — {{ $payload->progress->format() }} respondidos</div>
            </td>
            <td style="width: 20%; text-align: right;">
                <span class="rascunho">RASCUNHO — EM ANDAMENTO</span>
            </td>
        </tr>
    </table>
</header>

<footer>
    <table style="width: 100%;"><tr>
        <td>Gerado em {{ $payload->generatedAt }} — conteúdo licenciado do instrumento VB-MAPP</td>
        {{-- A paginação em si é desenhada pelo Job via Canvas::page_text() —
             o total de páginas só existe depois do render, a view não sabe. --}}
        <td style="text-align: right;"></td>
    </tr></table>
</footer>

@foreach ($payload->areas as $area)
    <h2 class="area">{{ $area->shortName }} — {{ $area->name }}</h2>

    @foreach ($area->items as $marco)
        <table class="marco">
            <tr>
                <td class="codigo">{{ $marco->code }}</td>
                <td>
                    <div class="enunciado">
                        {{ $marco->statement }}
                        @if ($marco->observationMinutes)
                            <span class="selo-obs">{{ $marco->observationMinutes }} min de observação</span>
                        @endif
                    </div>

                    @if ($payload->includeCriteria)
                        <div class="criterios">
                            <b>1 ponto:</b> {{ $marco->criteriaFull }}
                            @if ($marco->criteriaHalf)
                                <br><span class="meio"><b>½ ponto:</b> {{ $marco->criteriaHalf }}</span>
                            @else
                                <br><span class="meio">Este marco não admite meio ponto.</span>
                            @endif
                        </div>
                    @endif

                    @if ($marco->answered)
                        @if ($marco->exemplaresSummary !== '')
                            <div class="exemplares"><b>Registrado:</b> {{ $marco->exemplaresSummary }}</div>
                        @endif
                    @else
                        @if ($marco->checklist !== [])
                            <div class="exemplares">
                                @foreach ($marco->checklist as $opcao)
                                    <span class="checklist-item"><span class="caixa"></span>{{ $opcao }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="linha-vazia"></span>
                        @endif
                    @endif

                    @if ($marco->notes)
                        <div class="observacoes">Obs.: {{ $marco->notes }}</div>
                    @endif
                </td>
                <td class="pontuacao @if(!$marco->answered) pontuacao-vazia @elseif($marco->scoreLabel === '1') pontuacao-cheia @elseif($marco->scoreLabel === '½') pontuacao-meia @else pontuacao-zero @endif">
                    {{ $marco->answered ? $marco->scoreLabel : '' }}
                </td>
            </tr>
        </table>
    @endforeach
@endforeach

</body>
</html>
