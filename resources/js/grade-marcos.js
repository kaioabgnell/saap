/**
 * Teclado e toque da grade de lançamento retroativo (F10).
 *
 * O tooltip não está aqui: ele é o `graficoMarcos` do relatório, num escopo
 * Alpine acima deste. São a mesma informação na mesma disposição, e manter
 * dois seria manter dois.
 *
 * O que este componente resolve são as duas formas de marcar que o clique por
 * metade não atende:
 *
 *   teclado — transcrever 45 valores de um formulário de papel no mouse é
 *   lento e, pior, erra de linha. Com as setas e as teclas 1/5/0 a mão não
 *   sai do teclado e o foco anda sozinho.
 *
 *   toque — meia célula tem 24px de altura, longe dos 44px do design system.
 *   Em ponteiro grosso o toque abre um popover com três alvos de verdade.
 */
export function registrarGradeMarcos(Alpine) {
    Alpine.data('gradeMarcos', () => ({
        // Avaliado uma vez: o tipo de ponteiro não muda no meio da digitação,
        // e consultar matchMedia a cada clique seria trabalho por nada.
        toque: window.matchMedia('(pointer: coarse)').matches,
        popover: null,

        aoClicar(evento, itemId, metade, temMeioPonto) {
            if (this.toque) {
                this.abrirPopover(evento, itemId, temMeioPonto);

                return;
            }

            this.$wire.marcar(itemId, metade);
        },

        abrirPopover(evento, itemId, temMeioPonto) {
            const r = evento.currentTarget.getBoundingClientRect();

            this.popover = {
                itemId,
                temMeioPonto,
                x: Math.min(Math.max(r.left + r.width / 2 - 84, 8), window.innerWidth - 176),
                y: r.bottom + 6,
            };
        },

        fecharPopover() {
            this.popover = null;
        },

        escolher(valor) {
            const alvo = this.popover?.itemId;
            this.fecharPopover();

            if (alvo) this.$wire.definir(alvo, valor);
        },

        /**
         * Tecla numérica com a célula em foco. Depois de marcar, o foco sobe
         * um marco — que é a ordem em que a coluna do papel é lida.
         */
        aoDigitar(evento, itemId, temMeioPonto, coluna, marco) {
            const valor = {
                1: 1, 5: 0.5, m: 0.5, M: 0.5, 0: 0,
                Backspace: null, Delete: null,
            };

            if (!(evento.key in valor)) return;

            evento.preventDefault();

            const escolhido = valor[evento.key];

            if (escolhido === 0.5 && !temMeioPonto) return;

            this.$wire.definir(itemId, escolhido).then(() => this.focar(coluna, marco + 1));
        },

        mover(evento, coluna, marco) {
            const passo = {
                ArrowUp: [0, 1], ArrowDown: [0, -1],
                ArrowLeft: [-1, 0], ArrowRight: [1, 0],
            }[evento.key];

            if (!passo) return;

            // Só previne o padrão quando existe destino: numa borda da grade
            // a seta deve continuar rolando a página, como em qualquer tabela.
            if (this.focar(coluna + passo[0], marco + passo[1])) evento.preventDefault();
        },

        focar(coluna, marco) {
            const alvo = this.$el.querySelector(
                `[data-celula][data-coluna="${coluna}"][data-marco="${marco}"]`,
            );

            alvo?.focus();

            return Boolean(alvo);
        },
    }));
}
