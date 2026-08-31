# 03 — Design system

## Direção

Índigo profundo como cor institucional e de ação; verde e âmbar **reservados à
semântica de pontuação**. Essa separação é funcional: o psicólogo lê o gráfico de
marcos o tempo todo, e se a interface usasse verde como cor de marca a leitura
competiria.

**Nunca use emoji.** Toda iconografia vem do Font Awesome 6.

## Tokens

```css
:root {
  --primary:        #4338CA;  /* indigo-700  — ação principal, nav ativa, foco */
  --primary-hover:  #3730A3;
  --primary-soft:   #EEF2FF;  /* fundo de destaque, badge */

  /* Semânticas de pontuação: o par PREENCHIMENTO/TEXTO é obrigatório.
     Preenchimento é objeto gráfico (mínimo AA 3:1) e é a cor do gráfico de
     marcos, idêntica à do xlsx de referência. Texto exige 4,5:1, e as cores
     de preenchimento reprovam — daí a variante `-ink`. Corrigido na F9. */
  --success:        #059669;  /* emerald-600 — preenchimento: 1 ponto (3,77:1) */
  --success-ink:    #047857;  /* emerald-700 — texto              (5,48:1) */
  --success-soft:   #ECFDF5;

  --warning:        #D97706;  /* amber-600   — pendências, pontuação ajustada (3,19:1) */
  --warning-ink:    #B45309;  /* amber-700   — texto                 (5,02:1) */
  --warning-soft:   #FFFBEB;

  --danger:         #BE123C;  /* rose-700    — cancelar, falha de gravação */
  --danger-soft:    #FFF1F2;

  --ink:            #0F172A;  /* slate-900   — texto */
  --ink-muted:      #475569;  /* slate-600   — texto secundário */
  --ink-subtle:     #64748B;  /* slate-500   — rótulos, placeholders (4,76:1)
                                 era slate-400 #94A3B8: 2,56:1, reprovado */

  --surface:        #FFFFFF;
  --bg:             #F8FAFC;  /* slate-50    — fundo da aplicação */
  --border:         #E2E8F0;  /* slate-200 */
}
```

Registre em `tailwind.config.js` como `colors.primary`, `colors.success` etc.,
para que as classes utilitárias falem a mesma língua dos tokens.

## Hierarquia de botões

Três pesos, e a regra que os governa: **uma tela tem no máximo um botão sólido.**

| Peso | Aparência | Uso |
| --- | --- | --- |
| **Sólido** | Fundo `--primary`, texto branco | A única ação primária da tela |
| **Contornado** | Borda `--border`, texto `--ink` | Ações secundárias |
| **Fantasma** | Sem borda, texto `--ink-muted` | Ações terciárias, cancelar |
| **Destrutivo** | Fundo `--danger` | Só em modal de confirmação |

**Cancelar e concluir nunca dividem o mesmo peso.** Em modal de conclusão:
"Concluir avaliação" sólido, "Voltar" fantasma.

## Semântica de pontuação

Um vocabulário visual único, usado no cartão de item, no painel de área, no
gráfico e no PDF:

| Pontuação | Cor | Forma |
| --- | --- | --- |
| **1 ponto** | `--success` | Célula cheia |
| **½ ponto** | `--success` | Metade inferior preenchida — **mesma cor** do ponto inteiro |
| **0 ponto** | `--surface` com borda | Célula vazia |
| **Não respondido** | `--bg` com borda tracejada | Distinto de zero — zero é uma resposta |

> Distinguir "0 ponto" de "não respondido" é obrigatório. Marcar zero é um ato
> clínico; deixar em branco é uma pendência. O progresso conta apenas respondidos.

## Tipografia

- **Interface:** Inter ou a stack de sistema. Corpo 16px, altura 1.5.
- **Números e contadores:** `font-variant-numeric: tabular-nums`, para que
  `3/5` não dance ao mudar.
- **Enunciado do marco:** 17px, altura 1.6, largura máxima 68 caracteres.
  É o texto que o psicólogo lê com a criança à frente — precisa de folga.

## Componentes-chave

### Cabeçalho de identificação

Fixo no topo durante toda a aplicação. Conteúdo obrigatório:

- Foto e nome do aprendiz
- **Idade calculada** a partir de `birth_date` na data da aplicação, no formato
  do instrumento: `4 anos e 7 meses`
- Data da aplicação
- Nome do aplicador (usuário logado)
- Nível corrente e barra de progresso

Em telas estreitas colapsa para uma faixa de uma linha — nome, idade, progresso
— expansível ao toque.

### Cartão de marco

```
┌─────────────────────────────────────────────────────┐
│ MANDO 2                          [30 min observação]│
│ Emite 4 diferentes mandos (pedidos) sem ajuda,      │
│ exceto: "O que você quer?"                          │
│                                                     │
│ [ controle conforme response_type ]                 │
│                                                     │
│ 3 de 4 registrados · vale ½                         │
│ ▸ Critérios do manual                               │
└─────────────────────────────────────────────────────┘
```

- Selo de tempo de observação só aparece quando `observation_minutes` existe.
- Contador vivo abaixo do controle, atualizado a cada marcação.
- "Critérios do manual" é painel recolhível com `criteria_full` e `criteria_half`.
- Borda esquerda de 3px codifica o estado: cinza tracejado não respondido,
  âmbar ½, verde 1, cinza sólido zero.

### Indicador de salvamento

Quatro estados, sempre visível na barra de ações:

| Estado | Ícone | Texto |
| --- | --- | --- |
| Ocioso | `fa-check` cinza | `Salvo às 14:32` |
| Gravando | `fa-circle-notch fa-spin` | `Salvando…` |
| Salvo agora | `fa-check` verde | `Salvo agora` — desvanece para ocioso em 2s |
| Falha | `fa-triangle-exclamation` âmbar | `Sem conexão — 2 alterações na fila` |

### Grade de estímulos

Grade de imagens tocável. Cada estímulo é um botão com estado de check.

- Mínimo 96px por lado no iPad, 80px no celular.
- Check no canto superior direito, `fa-circle-check` preenchido em `--success`.
- Rótulo do estímulo abaixo da imagem, 13px.
- Contador vivo: `1 de 2 · vale ½` → `2 de 2 · vale 1`.
- Modo apresentação: botão `fa-expand` abre a grade em tela cheia **sem os
  checks**, para virar o tablet e mostrar à criança. Sair volta ao estado exato.

## Responsividade

O **iPad é o ponto de partida**, não uma adaptação do desktop.

| Faixa | Layout do nível |
| --- | --- |
| `< 768px` (celular) | Abas roláveis de área; um cartão por vez |
| `768–1279px` (iPad) | Abas de área no topo; cartões em coluna única larga |
| `≥ 1280px` (desktop) | Coluna lateral de áreas; cartões à direita |

- Alvo de toque mínimo **44×44px**.
- Barra de ações fixa no rodapé em `< 1280px`, respeitando `safe-area-inset`.
- Nenhuma interação essencial depende de hover.

## O gráfico de marcos

Uma coluna por área, cinco células por nível, o marco de menor número embaixo.
Cada marco é montado como **duas linhas de meia altura** — é o único jeito de a
mesma marcação servir HTML e dompdf, que não tem gradiente nem pseudo-elemento.

**O gráfico tem UMA cor.** Meio ponto e ponto inteiro são o mesmo verde; o que
os separa é a **altura preenchida** — meia célula contra célula inteira, como
na planilha de referência. Duas cores diziam a mesma coisa duas vezes, e a
altura já carrega a informação sozinha.

Isso não enfraquece a acessibilidade: a altura é um canal não-cromático, e cada
célula continua com `aria-label` próprio mais a tabela numérica equivalente
logo abaixo.

Na tela o gráfico ocupa a largura toda (`width:100%`, `table-layout:fixed`,
`min-width:520px` para rolar dentro do contêiner em telas estreitas) e cada
célula abre um **tooltip** com a área, o enunciado do marco e o que foi
registrado. No PDF nada disso existe: o componente recebe `interativo` falso e
mantém a largura fixa, dimensionada para o A4.

O conteúdo do tooltip sai do **snapshot**, nunca do catálogo atual — o gráfico
de um laudo emitido continua mostrando o enunciado que valia no dia da
conclusão.

## Sobreposições: onde um modal NÃO pode morar

`backdrop-filter` — e também `filter`, `transform`, `perspective`, `will-change`
— criam **bloco de contenção** para descendentes `position: fixed`. Dentro de um
ancestral assim, `fixed inset-0` deixa de valer contra a janela e passa a valer
contra a caixa do ancestral; e como esses mesmos ancestrais criam stacking
context, o `z-index` do modal fica aprisionado no do ancestral.

O SAAP tem dois elementos com `backdrop-blur`: o cabeçalho fixo e a barra de
ações do rodapé. Um modal renderizado dentro de qualquer um dos dois aparece
espremido na faixa daquela barra e por trás do resto da tela.

**Aumentar o z-index não resolve** — o problema é o bloco de contenção, não a
ordem de empilhamento. A saída é tirar o modal de lá:

```blade
@if ($aberto)
    @teleport('body')
        <div class="fixed inset-0 z-50 ..." role="dialog" aria-modal="true">
        ...
        </div>
    @endteleport
@endif
```

Aconteceu de verdade com o modal de "Imprimir formulário", que vive na barra do
rodapé. Guardado por `tests/Feature/Acessibilidade/SobreposicaoDeModaisTest`.

## Marca

A logo está em `public/images/`, em duas variantes do mesmo desenho:

| Arquivo | Wordmark | Onde usar |
| --- | --- | --- |
| `logo-saap.png` | navy `#0F1330` | fundo claro |
| `logo-saap-claro.png` | branco | fundo navy do painel de entrada |

O check que forma o segundo **A** carrega o gradiente da marca,
**`#8000FF` → `#0080FF`**, e é o único lugar onde ele aparece nítido. No painel
de entrada o mesmo gradiente reaparece **uma vez**, difuso, como brilho de
fundo. Não o espalhe: o gradiente é a assinatura, e assinatura repetida perde
a função.

Use sempre `<x-application-logo variante="clara|escura" />`, nunca o `<img>`
direto — a variante clara é gerada a partir da escura e as duas precisam andar
juntas.

## Telas de entrada

Layout em duas colunas (`layouts/guest.blade.php`), compartilhado por login,
cadastro e recuperação de senha:

- **Esquerda** — apresentação sobre navy `#0B1026`. O visual central é o
  **próprio gráfico de marcos**, não um mockup de painel: as faixas de nível e
  a meia célula âmbar são o que a psicóloga reconhece de imediato, e não
  existem em nenhum outro instrumento. Some abaixo de `lg`.
- **Direita** — o formulário, largura máxima de 400 px.

Duas regras que o layout precisa manter:

1. **A página não rola no split.** `lg:h-screen lg:overflow-hidden` no grid, e
   cada coluna rola por dentro. Sem isso o painel estica a linha do grid e a
   janela inteira ganha barra de rolagem por conteúdo que é só do painel.
2. **O painel encolhe por altura, não rola.** Em `max-height: 880px` (o
   notebook de 1280×800) o parágrafo de apoio some, o título baixa um degrau e
   as células do gráfico afinam. O gráfico nunca é o primeiro a cair.

No painel escuro as semânticas de pontuação usam o degrau mais claro —
`#10B981` e `#F59E0B` em vez de `--success` e `--warning` — pelo mesmo motivo
que qualquer paleta escura clareia: sobre navy, o tom de fundo claro fica
abafado.

## Acessibilidade

- Contraste mínimo AA, e o mínimo **depende do uso**: 3:1 para preenchimento
  (objeto gráfico), 4,5:1 para texto. Por isso `--success`/`--warning` têm par
  `-ink`: `bg-warning` é a célula do gráfico, `text-warning-ink` é o texto.
  **Nunca use `text-success`/`text-warning`** — reprovam em AA, e um teste
  varre as views atrás deles (`tests/Feature/Acessibilidade/ContrasteTest`).
- **Cor nunca é o único portador de informação.** Toda célula de pontuação tem
  `aria-label` (`Marco 2: meio ponto`) e o gráfico tem tabela equivalente.
- Foco visível em tudo: `outline: 2px solid var(--primary); outline-offset: 2px`.
- A grade de estímulos é navegável por teclado, com `Space` alternando o check.
- `prefers-reduced-motion` desliga transições.

## Marca

Sem logo. A marca é o wordmark tipográfico **SAAP**, em maiúsculas, peso 600,
`letter-spacing: 0.2em`, cor `--primary`, acompanhado de "Sistema de Avaliação de
Aprendiz" em 12px `--ink-subtle` quando houver espaço.
