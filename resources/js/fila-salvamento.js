/**
 * Fila de reenvio das gravações que falharam.
 *
 * O psicólogo aplica o teste com a criança à frente e a atenção dividida. Uma
 * resposta perdida por oscilação de rede não será refeita — por isso a fila é
 * requisito, não refinamento.
 *
 * Como funciona: cada componente de marco mantém o que o psicólogo digitou no
 * próprio estado do cliente. Quando um commit do Livewire falha, guardamos o
 * id do componente e reenviamos a ação `salvar`, que reenvia o estado atual.
 * A gravação no servidor é idempotente — índice único (assessment_id, item_id)
 * — então repetir converge no mesmo resultado em vez de duplicar.
 *
 * Limite conhecido: se a aba for fechada com itens na fila, o que foi digitado
 * e não gravou se perde. Persistimos a contagem para avisar no retorno, mas
 * restaurar o texto exigiria reidratar o estado do Livewire — fica para a F9.
 */

const CHAVE = 'saap.fila-salvamento';
const ESPERA_INICIAL = 2000;
const ESPERA_MAXIMA = 30000;

function lerPersistido() {
    try {
        return JSON.parse(localStorage.getItem(CHAVE)) ?? {};
    } catch {
        return {};
    }
}

function persistir(estado) {
    try {
        localStorage.setItem(CHAVE, JSON.stringify(estado));
    } catch {
        // Modo privado ou cota estourada: a fila em memória continua valendo.
    }
}

export function registrarFilaSalvamento(Alpine, Livewire) {
    // Componentes cujo último commit falhou, por id.
    const pendentes = new Set();
    let espera = ESPERA_INICIAL;
    let agendado = null;

    const notificar = () => {
        persistir({ pendentes: pendentes.size, em: Date.now() });
        window.dispatchEvent(
            new CustomEvent('saap:fila-alterada', { detail: { pendentes: pendentes.size } }),
        );
    };

    const reenviar = () => {
        agendado = null;

        if (pendentes.size === 0) return;

        for (const id of [...pendentes]) {
            const componente = Livewire.find(id);

            // O componente saiu da tela (troca de área, por exemplo).
            if (!componente) {
                pendentes.delete(id);
                continue;
            }

            componente.call('salvar');
        }

        notificar();
        agendar();
    };

    const agendar = () => {
        if (agendado !== null || pendentes.size === 0) return;

        agendado = setTimeout(reenviar, espera);
        espera = Math.min(espera * 2, ESPERA_MAXIMA);
    };

    Livewire.hook('commit', ({ component, succeed, fail }) => {
        succeed(() => {
            if (pendentes.delete(component.id)) {
                notificar();
            }
            espera = ESPERA_INICIAL;
        });

        fail(() => {
            pendentes.add(component.id);
            notificar();
            agendar();
        });
    });

    // Voltou a conexão: tenta na hora, sem esperar o backoff.
    window.addEventListener('online', () => {
        espera = ESPERA_INICIAL;
        if (agendado !== null) {
            clearTimeout(agendado);
            agendado = null;
        }
        reenviar();
    });

    Alpine.data('filaSalvamento', () => ({
        pendentes: lerPersistido().pendentes ?? 0,

        init() {
            // A contagem restaurada do armazenamento é só um aviso do que ficou
            // para trás; a fila viva começa zerada nesta aba.
            window.addEventListener('saap:fila-alterada', (evento) => {
                this.pendentes = evento.detail.pendentes;
            });
        },
    }));
}
