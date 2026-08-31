/**
 * Tooltip do gráfico de marcos do relatório.
 *
 * Um componente por gráfico, não por célula: um nível cheio tem 65 marcos, e
 * pendurar um tooltip em cada um encheria o DOM de elementos parados só
 * esperando um hover.
 *
 * Só existe na tela. O PDF renderiza o mesmo gráfico com `interativo` falso,
 * sem nenhum destes atributos.
 */
export function registrarGraficoMarcos(Alpine) {
    const MARGEM = 14;

    Alpine.data('graficoMarcos', () => ({
        aberto: false,
        x: 0,
        y: 0,
        dados: { area: '', code: '', statement: '', score: '', answer: '', position: null },

        mostrar(evento, dados) {
            this.dados = dados;
            this.aberto = true;
            this.reposicionar(evento);
        },

        esconder() {
            this.aberto = false;
        },

        reposicionar(evento) {
            if (!this.aberto) return;

            // Foco por teclado não traz coordenada de cursor: nesse caso o
            // tooltip ancora na própria célula.
            const temCursor = typeof evento.clientX === 'number' && evento.clientX > 0;
            const base = temCursor
                ? { x: evento.clientX, y: evento.clientY }
                : this.centroDe(evento.target);

            const largura = 304; // w-[19rem]
            const altura = this.$el.querySelector('[role="tooltip"]')?.offsetHeight ?? 150;

            // Vira para o lado de dentro quando encostaria na borda da janela —
            // sem isso o tooltip da última coluna nasce fora da tela.
            let x = base.x + MARGEM;
            if (x + largura > window.innerWidth - 8) x = base.x - largura - MARGEM;
            if (x < 8) x = 8;

            let y = base.y + MARGEM;
            if (y + altura > window.innerHeight - 8) y = base.y - altura - MARGEM;
            if (y < 8) y = 8;

            this.x = x;
            this.y = y;
        },

        centroDe(elemento) {
            const r = elemento.getBoundingClientRect();

            return { x: r.left + r.width / 2, y: r.bottom };
        },
    }));
}
