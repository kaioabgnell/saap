{{-- Folha de estilo do gráfico e do laudo. Compartilhada entre a versão HTML
     e a do PDF — o CSS aqui é CSS 2.1 puro, o denominador comum do dompdf. --}}
<style>
    .grafico-marcos { border-collapse: collapse; margin: 8px 0 4px 0; }
    .grafico-marcos .eixo {
        width: 22px; font-size: 8px; color: #94A3B8; font-weight: normal;
        text-align: right; padding-right: 5px; vertical-align: middle;
    }
    .grafico-marcos .celula {
        width: 46px; height: 9px; padding: 0;
        border-left: 1px solid #E2E8F0; border-right: 1px solid #E2E8F0;
    }
    .grafico-marcos .celula.top { border-top: 1px solid #E2E8F0; }
    .grafico-marcos .celula.bottom { border-bottom: 1px solid #E2E8F0; }
    /* Meio ponto e ponto inteiro usam a MESMA cor: o que os separa é a
       altura preenchida — meia célula contra célula inteira, como na planilha
       de referência. Duas cores diziam a mesma coisa duas vezes. */
    .grafico-marcos .celula.cheia { background-color: #059669; }
    .grafico-marcos .celula.meia { background-color: #059669; }
    .grafico-marcos .celula.vazia { background-color: #FFFFFF; }
    .grafico-marcos .celula.pendente { background-color: #F8FAFC; }
    .grafico-marcos .rotulo-area {
        font-size: 7.5px; color: #475569; font-weight: normal;
        padding-top: 5px; text-align: center;
    }

    .legenda { font-size: 8px; color: #64748B; margin-top: 2px; }
    .legenda span { margin-right: 12px; }
    .legenda i {
        display: inline-block; width: 9px; height: 9px;
        border: 1px solid #E2E8F0; vertical-align: middle; margin-right: 3px;
    }
    .legenda i.cheia { background-color: #059669; }
    /* A amostra do ½ precisa MOSTRAR a meia altura, não uma cor diferente:
       é a altura que carrega a informação. Duas caixas empilhadas, só a de
       baixo pintada — o mesmo desenho que aparece no gráfico. */
    .legenda i.meia { background-color: #FFFFFF; padding: 0; }
    .legenda i.meia b {
        display: block; height: 4px; margin-top: 4px;
        background-color: #059669;
    }
    .legenda i.vazia { background-color: #FFFFFF; }
    .legenda i.pendente { background-color: #F8FAFC; }
</style>
