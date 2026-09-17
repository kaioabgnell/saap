/**
 * Fila de reenvio das gravações que falharam.
 *
 * O psicólogo aplica o teste com a criança à frente e a atenção dividida. Uma
 * resposta perdida por oscilação de rede não será refeita — por isso a fila é
 * requisito, não refinamento.
 *
 * Como funciona: o hook `commit` do Livewire dispara para TODO componente da
 * página, não só o do marco — inclusive o `LevelBoard`, que se atualiza
 * sozinho ao ouvir o evento `resposta-salva`. Por isso o reenvio precisa
 * repetir a AÇÃO que de fato falhou (`commit.calls`, com o método e os
 * parâmetros originais), e não uma ação fixa chamada `salvar`: essa suposição
 * quebrava para qualquer componente sem esse método — o `LevelBoard` é um —,
 * e a tentativa de reenvio falhava para sempre, sem nunca sair da fila. Sem
 * ação nenhuma no commit (o caso do `LevelBoard` recebendo o evento), o
 * reenvio é `$wire.commit()`: ressincroniza o estado atual sem inventar um
 * método para chamar.
 *
 * A gravação no servidor é idempotente — índice único (assessment_id, item_id)
 * — então repetir converge no mesmo resultado em vez de duplicar. E o estado
 * reenviado é sempre o ATUAL do componente no navegador, não uma cópia velha
 * do momento da falha: os campos (`wire:model`) continuam vivos ali, então um
 * segundo commit que falhe para o mesmo componente substitui o primeiro pela
 * intenção mais recente — o comportamento certo quando a psicóloga marca uma
 * caixa, ainda offline, e depois marca outra antes do primeiro reenvio.
 *
 * **Por que existe um limite de tempo.** Nem toda falha é rede instável. Uma
 * sessão expirada (419) devolve o mesmo erro para sempre — reenviar com o
 * token velho não converge nunca, e ficar tentando em silêncio é pior do que
 * avisar: é exatamente o que deixava a psicóloga presa em "Sem conexão" sem
 * conseguir concluir a avaliação, porque não havia CAMINHO nenhum de volta que
 * não exigisse mexer no banco. Por isso, depois de `LIMITE_DE_TENTATIVAS`
 * reenvios sem sucesso para o mesmo componente, a fila para de insistir
 * sozinha e passa a pedir para recarregar a página — a única ação que resolve
 * de verdade uma sessão vencida, e que não exige acesso a fila nem a banco.
 *
 * **O indicador mostra a fila VIVA desta aba, e só.** Houve uma versão que
 * semeava o contador a partir do `localStorage`, com a intenção de avisar no
 * retorno o que tinha ficado para trás. O efeito real foi outro: um único
 * incidente passado deixava "Sem conexão — tentando salvar de novo (1
 * alteração)" fixo na tela para sempre. A fila em memória nasce vazia a cada
 * carregamento, então `succeed()` não tinha o que remover e nunca chamava
 * `notificar()` — o número velho ficava lá, imune a recarregamento e imune a
 * salvamentos bem-sucedidos, acusando uma falha que não existia mais.
 *
 * Um indicador de estado precisa ser capaz de voltar a zero sozinho. Este não
 * era, e por isso não persiste mais nada.
 *
 * Limite conhecido: se a aba for fechada com itens na fila, o que foi digitado
 * e não gravou se perde, e agora sem aviso no retorno. Quem responde essa
 * pergunta melhor já está na própria tela do nível — o filtro "Somente não
 * respondidas" mostra exatamente quais marcos ficaram sem resposta, que é o
 * que a psicóloga precisa saber para refazê-los.
 */

const CHAVE = 'saap.fila-salvamento';
const ESPERA_INICIAL = 2000;
const ESPERA_MAXIMA = 30000;

// 6 tentativas somam ~92s de backoff (2+4+8+16+30+30) — o suficiente para
// absorver uma instabilidade de rede real, pouco o bastante para não deixar
// a psicóloga esperando minutos por algo que uma sessão vencida jamais
// resolveria sozinha.
const LIMITE_DE_TENTATIVAS = 6;

export function registrarFilaSalvamento(Alpine, Livewire) {
    // Componentes cujo último commit falhou, por id → { chamadas, tentativas }.
    // `chamadas` é a ação do commit que falhou ([{method, params}]; vazio
    // quando o componente só estava sincronizando estado por causa de um
    // evento, sem ação própria). `tentativas` conta reenvios sem sucesso.
    const pendentes = new Map();

    // Componentes que passaram do limite: a fila desistiu de tentar sozinha.
    const travados = new Set();

    let espera = ESPERA_INICIAL;
    let agendado = null;

    const notificar = () => {
        window.dispatchEvent(
            new CustomEvent('saap:fila-alterada', {
                detail: { pendentes: pendentes.size, travados: travados.size },
            }),
        );
    };

    const reenviar = () => {
        agendado = null;

        if (pendentes.size === 0) return;

        for (const [id, entrada] of [...pendentes]) {
            const componente = Livewire.find(id);

            // O componente saiu da tela (troca de área, por exemplo).
            if (!componente) {
                pendentes.delete(id);
                continue;
            }

            entrada.tentativas++;

            if (entrada.chamadas.length === 0) {
                // Nenhuma ação própria falhou — o componente só reagia a um
                // evento de outro. Ressincroniza sem inventar um método.
                componente.commit();
                continue;
            }

            for (const { method, params } of entrada.chamadas) {
                componente.call(method, ...(params ?? []));
            }
        }

        notificar();
        agendar();
    };

    const agendar = () => {
        if (agendado !== null || pendentes.size === 0) return;

        agendado = setTimeout(reenviar, espera);
        espera = Math.min(espera * 2, ESPERA_MAXIMA);
    };

    Livewire.hook('commit', ({ component, commit, succeed, fail }) => {
        succeed(() => {
            let mudou = pendentes.delete(component.id);
            mudou = travados.delete(component.id) || mudou;

            if (mudou) {
                notificar();
            }
            espera = ESPERA_INICIAL;
        });

        fail(() => {
            const existente = pendentes.get(component.id);
            const tentativas = existente?.tentativas ?? 0;

            if (tentativas >= LIMITE_DE_TENTATIVAS) {
                // Essa tentativa também falhou — reenviar não vai resolver.
                // A causa mais provável é sessão vencida, e o mesmo token
                // expirado volta a falhar sempre. Sai da fila ativa; só
                // recarregar a página resolve daqui.
                pendentes.delete(component.id);
                travados.add(component.id);
                notificar();

                return;
            }

            // Uma nova falha do mesmo componente reabre a tentativa — a
            // psicóloga pode ter corrigido algo (ex.: recarregou em outra
            // aba) e vale dar mais uma chance antes de travar de novo.
            pendentes.set(component.id, { chamadas: commit?.calls ?? [], tentativas });
            travados.delete(component.id);

            notificar();
            agendar();
        });
    });

    // Voltou a conexão: tenta na hora, sem esperar o backoff, e dá às
    // tentativas travadas mais uma chance — a internet pode ter sido a causa
    // real, não a sessão.
    window.addEventListener('online', () => {
        espera = ESPERA_INICIAL;

        for (const id of travados) {
            pendentes.set(id, { chamadas: [], tentativas: 0 });
        }
        travados.clear();

        if (agendado !== null) {
            clearTimeout(agendado);
            agendado = null;
        }
        reenviar();
    });

    Alpine.data('filaSalvamento', () => ({
        // Os dois começam ZERADOS. O indicador mostra a fila VIVA desta aba e
        // nada mais — ver o cabeçalho do arquivo para o estrago que a versão
        // anterior causava.
        pendentes: 0,
        travados: 0,

        init() {
            // Limpa resíduo de versões anteriores, que semeavam o contador a
            // partir daqui. Sem isso a chave fica no navegador para sempre,
            // sem ninguém para lê-la.
            try {
                localStorage.removeItem(CHAVE);
            } catch {
                // Modo privado: não havia o que limpar.
            }

            window.addEventListener('saap:fila-alterada', (evento) => {
                this.pendentes = evento.detail.pendentes;
                this.travados = evento.detail.travados;
            });
        },

        recarregar() {
            window.location.reload();
        },
    }));
}
