# F10 — Lançamento retroativo (preenchimento direto do gráfico)

> **Depende de:** F7 · **Estimativa:** ~1 semana · **Habilita:** —
> **Modelo:** Opus 5 · effort `xhigh` (`claude-opus-5`) — mexe em pontuação, em
> imutabilidade e no que o laudo afirma sobre a própria origem. Nenhuma dessas
> três coisas admite erro silencioso.

> **Status: concluída.** Registro do que divergiu da spec, ao final.

## Objetivo

O psicólogo lança no sistema o resultado de uma aplicação **já feita em papel**
— clicando direto nas células do gráfico de marcos — e obtém o mesmo laudo em
PDF, com as respostas vazias, porque não há respostas a registrar: só houve
pontuação.

## Motivação

Há anos de aplicações em papel arquivadas. O valor delas não está nos
exemplares registrados marco a marco — esse detalhe ficou no formulário de
papel, e não vale o tempo de redigitar. O valor está no **perfil**: o gráfico,
a pontuação por área e a comparação com a aplicação seguinte. É isso que o
lançamento retroativo traz para dentro do sistema.

## O que NÃO muda

Esta fase é **aditiva**. Não altera comportamento de nada que já existe:

- `LevelBoard`, `ItemCard`, `SaveResponse`, `EntryTally`, `ScoreCalculator` —
  intactos.
- Impressão do formulário (F6), API (F8), acervo de imagens (F4) — intactos.
- A tela de relatório e o PDF ganham **uma** marcação de procedência; o resto
  do documento é o mesmo, gerado pelo mesmo caminho.

Se a implementação precisar mudar a assinatura de qualquer classe da lista
acima, pare: o desenho está errado.

## Pré-requisitos

- F7 concluída (snapshot, `content_hash`, PDF).
- Revisão clínica dos limiares **não** é pré-requisito: no lançamento
  retroativo nenhum limiar é consultado para pontuar. O catálogo entra só para
  saber quais marcos existem e quais admitem meio ponto.


## Decisões confirmadas (08/09/2026)

Quatro pontos foram levados à psicóloga antes de escrever o código. As
respostas estão incorporadas no texto abaixo; ficam registradas aqui porque
mudar qualquer uma delas depois muda o laudo:

1. **Célula em branco vira 0 na conclusão**, com o número informado no modal.
2. **Selo `RELATÓRIO — TRANSCRIÇÃO` no cabeçalho e faixa de procedência** sob o
   resumo, na tela e no PDF.
3. **O aplicador é sempre o usuário logado** — não há campo "aplicado
   originalmente por". Se algum dia houver transcrição de aplicação de
   terceiro, isso volta à mesa.
4. **Os níveis são escolhidos no formulário de abertura.** Nível não escolhido
   não existe na avaliação nem no laudo.

---

## Conceito central: modo de lançamento

Uma avaliação nasce em um de dois modos, e **nunca troca**:

| `entry_mode` | Significado | Como se pontua |
| --- | --- | --- |
| `guided` (padrão) | Aplicação conduzida na tela | Exemplares registrados → `EntryTally` conta → `ScoreCalculator` decide |
| `chart` | Transcrição de aplicação em papel | O psicólogo informa a pontuação direto na célula |

Os modos são **exclusivos por avaliação**, e isso não é purismo: uma avaliação
`chart` aberta no `LevelBoard` seria destruída pelo primeiro toque. O
`ItemCard` gravaria via `SaveResponse` com `entries = []`, o `ScoreCalculator`
devolveria 0 e o marco transcrito como 1 ponto viraria 0 — sem aviso, porque
do ponto de vista do fluxo guiado nada de errado aconteceu. A trava tem de
existir na rota, na policy e no caso de uso.

Uma reavaliação futura do mesmo aprendiz é uma **nova** avaliação `guided`.
Nenhuma avaliação transcrita é "continuada" no fluxo normal.

---

## Modelo de dados

### 1. `assessments.entry_mode`

```php
$table->string('entry_mode', 16)->default('guided')->after('instrument');
```

Enum `App\Domain\Assessment\EntryMode: string { case Guided = 'guided'; case Chart = 'chart'; }`
com `label()` (`'Aplicação na tela'` / `'Transcrição de papel'`), no padrão de
`AssessmentStatus`.

Nenhuma coluna nova para a data da aplicação em papel: **`applied_on` já é
exatamente isso** — a data em que a avaliação foi aplicada. É ela que o laudo
imprime e é sobre ela que `Learner::ageAt()` calcula a idade. Numa transcrição
`applied_on` fica no passado e `created_at` marca quando foi digitada; as duas
juntas já contam a história inteira.

### 2. `responses.computed_score` passa a ser anulável

```php
$table->decimal('computed_score', 2, 1)->nullable()->change();
```

Na transcrição o sistema **não calculou nada**. Gravar `computed_score = 0`
com `score = 1` faria `is_overridden` virar verdadeiro e o laudo imprimiria
_"pontuação ajustada pelo aplicador"_ em quase todos os 45 marcos — uma
afirmação falsa sobre um ato que nunca houve. Na transcrição:

```
score          = 0 | 0.5 | 1
computed_score = null        (não houve cálculo)
is_overridden  = false       (não houve sobrescrita)
answered_at    = now()       (foi respondido — em papel, há um ano)
entries        = nenhuma     (é o ponto da funcionalidade)
```

Ajuste em `BuildReportPayload::montarMarco()` — hoje `(float) $response->computed_score`
transforma `null` em `0.0`:

```php
'score_calculado' => $response?->computed_score === null
    ? null
    : (float) $response->computed_score,
```

> **Decisão: nada de `responses.source`.** Uma coluna por resposta dizendo de
> onde ela veio seria uma segunda fonte de verdade para um fato que já está em
> `assessments.entry_mode`. Modos são exclusivos por avaliação; enquanto forem,
> a coluna só cria a chance de as duas discordarem.

---

## Fluxo do usuário

### 1. Entrada

Na página do aprendiz (`learners/show.blade.php`), ao lado de **`Nova avaliação`**,
um botão secundário: **`Lançar avaliação em papel`**.

`OpenAssessment` hoje recusa uma segunda avaliação aberta para o mesmo
aprendiz. Essa regra **não pode valer aqui**: transcrever três anos de papel é
o caso normal, e uma aplicação em andamento hoje não pode bloquear o registro
de uma de 2024. Duas mudanças:

- a criação em modo `chart` não passa pelo guarda de "já aberta";
- o guarda do modo `guided` passa a considerar só avaliações `guided`.

### 2. Formulário de abertura

`GET /aprendizes/{learner}/lancamento` → `ChartEntryController::create`

| Campo | Regra |
| --- | --- |
| Data da aplicação | obrigatória, `before_or_equal:today`, `after_or_equal` nascimento do aprendiz |
| Níveis a lançar | ao menos um de 1/2/3 |
| Observações | opcional, pré-preenchida com `Resultado transcrito do formulário em papel aplicado em DD/MM/AAAA.` |

Se o aprendiz já tiver avaliação concluída com a **mesma** `applied_on`, avise
antes de criar: _"Já existe avaliação deste aprendiz aplicada em 12/03/2025.
Deseja lançar outra?"_ — aviso, não bloqueio.

`POST` cria a avaliação com `entry_mode = 'chart'`, `status = 'in_progress'`,
`started_at = now()`, e inicia (via `StartLevel`) apenas os níveis escolhidos.
Nível não escolhido **não ganha linha em `assessment_levels`** e portanto não
aparece no laudo — é a mesma regra do fluxo guiado.

### 3. Tela de lançamento

`GET /avaliacoes/{assessment}/lancamento` → componente Livewire de página
inteira `App\Livewire\Assessment\ChartEntry`.

Um gráfico por nível lançado, na mesma disposição do laudo (coluna por área,
marco 1 embaixo), com:

- eixo com o número do marco e rótulo de área embaixo;
- **tooltip no hover/foco com o enunciado do marco** — indispensável: quem
  transcreve precisa confirmar que a coluna e a linha correspondem ao que está
  no papel. Reaproveita o Alpine `graficoMarcos` da tela de relatório;
- contador vivo por nível: `32 de 45 marcados · 24,5 pontos`;
- botão `Limpar nível`, com confirmação.

### 4. Regra do clique

Cada célula é composta de duas metades. **Cada metade é um interruptor de meio
ponto** — a de baixo vale o primeiro meio ponto, a de cima o segundo:

| Estado atual | Clique na metade de baixo | Clique na metade de cima |
| --- | --- | --- |
| pendente | ½ ponto | 1 ponto |
| ½ ponto | 0 ponto | 1 ponto |
| 1 ponto | 0 ponto | ½ ponto |
| 0 ponto | ½ ponto | 1 ponto |

Um ponto sem a metade de baixo pintada não é um estado que exista no gráfico —
por isso clicar em cima liga as duas. Voltar a **pendente** não se faz por
clique: é `Backspace`/`Delete` com a célula em foco, ou `Limpar nível`.

**Marcos sem meio ponto** (`threshold_half === null`, caso do Ouvinte 2)
recusam ½: a metade de baixo se comporta como a de cima e a célula recebe
`title`/`aria-description` explicando. É o catálogo mandando na interface, e é
a única regra do manual que sobrevive nesta tela.

**Teclado** — transcrever 45 valores no mouse é lento e erra de linha. Setas
navegam a grade; `1` marca 1 ponto, `5` marca ½, `0` marca 0, `Backspace`
volta a pendente, e o foco avança sozinho para o marco de cima. Ordem de
tabulação: uma parada por célula, nunca duas.

**Toque** — meia célula não chega perto dos 44px do design system. Em
`@media (pointer: coarse)` o toque em qualquer lugar da célula abre um popover
com três botões de 44px (`1` · `½` · `0`); o clique por metade fica para
ponteiro fino. As células do lançamento têm 48px de altura (24 por metade),
contra os 13px do laudo.

### 5. Gravação

Cada clique grava na hora, como no fluxo guiado — a tela não tem botão de
enviar. O indicador `Salvo às HH:MM` do `LevelBoard` se repete aqui.

### 6. Conclusão

Botão **`Concluir e gerar relatório`**. O modal de confirmação diz o que vai
acontecer, sem eufemismo, e **quantos marcos ficarão com 0**:

```
Concluir lançamento

Os 13 marcos ainda não marcados serão registrados como 0 ponto.
Depois de concluída, nenhuma pontuação pode ser alterada.

Aprendiz:   Kaleo — 3 anos e 2 meses na data da aplicação
Aplicação:  12/03/2025 (em papel)
Níveis:     Nível 1 · 32 marcados, 13 em zero
Pontuação:  24,5 de 45

           [ Voltar ]   [ Concluir lançamento ]
```

> **Por que branco vira zero.** No formulário de papel do VB-MAPP a célula em
> branco é zero: a pontuação total é a soma do que foi pintado. Exigir 13
> cliques para dizer "zero" seria transcrever uma informação que o papel já
> deu. Mas zero é pontuação, e pontuação exige ato deliberado — por isso o
> número aparece no modal e a conclusão é o ato. Fora do modal, a interface
> nunca converte branco em zero por conta própria.

---

## Camada de aplicação

### `SaveChartScore` (novo)

```php
final class SaveChartScore
{
    public function handle(int $assessmentId, int $itemId, ?float $score): SaveChartScoreResult
}
```

Em transação, com `lockForUpdate` na avaliação:

1. recusa se `isLocked()`;
2. recusa se `entry_mode !== EntryMode::Chart` — *"Esta avaliação é conduzida
   marco a marco; use a tela do nível."*;
3. recusa `$score` fora de `{null, 0, 0.5, 1}`;
4. recusa `0.5` quando `! $item->hasHalfPoint()`;
5. recusa se o nível do item não estiver iniciado nesta avaliação;
6. `$score === null` → apaga a `Response` (volta a pendente);
7. senão, `updateOrCreate` com `score`, `computed_score = null`,
   `is_overridden = false`, `answered_at = now()`, sem `entries`;
8. recalcula os totais do nível.

### `RecalculateLevelTotals` (extração)

O passo 8 é literalmente `SaveResponse::recalcularNivel()`. **Extraia** para
`App\Application\Assessment\RecalculateLevelTotals`, injetado nos dois casos de
uso. Duas cópias da contagem de progresso é exatamente o que o `01-arquitetura.md`
proíbe — e a que ficasse para trás iria divergir na primeira mudança.

### `CompleteChartAssessment` (novo)

A porta única da conclusão em modo `chart`. Em uma transação:

1. recusa se não for `chart`, se estiver travada ou cancelada;
2. insere `score = 0`, `computed_score = null`, `answered_at = now()` para
   todo marco dos níveis iniciados que ainda não tenha `Response`;
3. recalcula os totais e conclui cada nível via `CompleteLevel`;
4. delega a `CompleteAssessment` — que trava, monta o snapshot, grava o
   `content_hash` e enfileira o PDF.

O passo 4 é delegação, não cópia: imutabilidade e snapshot continuam existindo
em um lugar só.

---

## O laudo diz de onde veio

Um laudo transcrito **não pode ser indistinguível** de um aplicado no sistema.
Quem lê precisa saber que os dados vieram de um formulário de papel de outra
data e que não há exemplares registrados porque nunca houve — não porque o
aplicador os omitiu. Isso é requisito de documento psicológico, não enfeite.

**No payload** (`BuildReportPayload`), dentro de `aplicacao`:

```json
"aplicacao": {
  "data": "2025-03-12",
  "modo": "transcricao",
  "transcrita_em": "2026-09-08T10:14:00-03:00",
  "instrumento": "vbmapp"
}
```

`modo` é `"aplicacao"` no fluxo guiado. Como entra no payload, entra também no
`content_hash` — a procedência fica congelada junto com o resto.

**Na tela e no PDF**, quando `modo === 'transcricao'`:

- o selo do cabeçalho do PDF passa de `RELATÓRIO FINAL` para
  `RELATÓRIO — TRANSCRIÇÃO`;
- logo abaixo do resumo, nos dois meios, uma faixa:
  _"Resultados transcritos de aplicação em papel realizada em 12/03/2025. O
  registro de exemplares por marco permaneceu no formulário original."_
- o detalhamento por marco continua saindo — código, enunciado e pontuação —
  apenas sem a linha `Registrado:`. Isso já funciona: `exemplares` vem vazio e
  tanto o PDF quanto o tooltip omitem a linha (`MilestoneChart::respostaDe()`
  devolve `''`, e o tooltip tem `x-show="dados.answer"`).

---

## Entregáveis

- `database/migrations/*_add_entry_mode_to_assessments_table.php`
- `database/migrations/*_make_computed_score_nullable_on_responses_table.php`
- `app/Domain/Assessment/EntryMode.php`
- `app/Domain/Vbmapp/Chart/ChartGrid.php` — monta a grade editável a partir do
  catálogo e de um mapa `item_id => score`; PHP puro, testável sem banco
- `app/Application/Assessment/{SaveChartScore,SaveChartScoreResult,CompleteChartAssessment,RecalculateLevelTotals}.php`
- `app/Http/Controllers/ChartEntryController.php` (`create`, `store`)
- `app/Livewire/Assessment/ChartEntry.php` + view
- `resources/views/components/vbmapp/milestone-grid.blade.php` — a grade
  clicável; compartilha as classes `.grafico-marcos` de
  `pdf/relatorio/_estilo.blade.php` para não haver dois gráficos diferentes
- `resources/js/grade-marcos.js` — teclado e popover de toque
- Botão na página do aprendiz; marcação de procedência na tela e no PDF
- Testes Pest

## Tarefas

1. `EntryMode` + as duas migrations + ajuste de `BuildReportPayload` para
   `computed_score` nulo.
2. Extrair `RecalculateLevelTotals` de `SaveResponse` e reapontar `SaveResponse`
   para ele. **Rodar a suíte inteira aqui** — é a única tarefa desta fase que
   toca o fluxo guiado.
3. Guarda de modo: `OpenAssessment` ignora `chart`; rota
   `avaliacoes.nivel`, `LevelBoard`, `SaveResponse` e o `PUT` da API recusam
   avaliação `chart`; a rota de lançamento recusa avaliação `guided`.
4. `ChartGrid` + testes unitários.
5. `SaveChartScore` + testes.
6. `ChartEntryController` e o formulário de abertura.
7. Componente `milestone-grid` e o Livewire `ChartEntry` (clique, teclado, toque).
8. `CompleteChartAssessment` + modal de confirmação.
9. Procedência no payload, na tela e no PDF.
10. `docs/operacao.md`: uma seção sobre o lançamento retroativo.

## Critérios de aceite

- [x] Lançar nível 1 inteiro por clique produz laudo com o gráfico correto,
      pontuação por área correta e **nenhum** `Registrado:` no detalhamento.
- [x] Nenhum marco transcrito sai como `pontuação ajustada pelo aplicador`.
- [x] Clicar na metade de baixo dá ½; na de cima, 1; a tabela de transições
      acima vale para os quatro estados.
- [x] Marco sem meio ponto recusa ½ pela interface **e** pelo caso de uso.
- [x] Abrir `/avaliacoes/{id}/nivel/1` de uma avaliação `chart` devolve 403 —
      e o `PUT` da API também.
- [x] Concluir com marcos em branco registra 0 neles, e o modal informou o
      número antes.
- [x] `applied_on` no passado faz o laudo imprimir a idade do aprendiz **na
      data da aplicação**, não a de hoje.
- [x] Laudo transcrito traz o selo `RELATÓRIO — TRANSCRIÇÃO` e a faixa de
      procedência; laudo guiado continua exatamente como está hoje.
- [x] Um aprendiz aceita várias transcrições, com uma avaliação guiada aberta
      ao mesmo tempo.
- [x] Depois de concluída, a avaliação transcrita é imutável — `SaveChartScore`
      recusa, a policy recusa.
- [x] Toda a suíte anterior a esta fase passa sem alteração de teste, exceto o
      que a extração de `RecalculateLevelTotals` exigir.

### Testes Pest

| Arquivo | Verifica |
| --- | --- |
| `tests/Unit/Vbmapp/ChartGridTest.php` | montagem da grade, estado por pontuação, marco sem ½ |
| `tests/Feature/Lancamento/SalvarPontuacaoDiretaTest.php` | os 4 estados, `computed_score` nulo, `is_overridden` falso, ½ recusado |
| `tests/Feature/Lancamento/ModosExclusivosTest.php` | 403 no `LevelBoard` e no `PUT` da API; rota de lançamento recusa `guided` |
| `tests/Feature/Lancamento/ConcluirLancamentoTest.php` | branco vira 0, níveis concluídos, snapshot e PDF gerados |
| `tests/Feature/Lancamento/ProcedenciaNoLaudoTest.php` | `modo` no payload e no hash, selo e faixa na tela e no PDF, ausência de `Registrado:` |
| `tests/Feature/Lancamento/VariasTranscricoesTest.php` | várias transcrições e uma guiada aberta convivem |
| `tests/Feature/Assessment/GravarRespostaTest.php` (existente) | continua passando após a extração |

## Riscos e decisões

**O `LevelBoard` apaga transcrição.** O risco maior da fase, e silencioso:
um marco transcrito aberto no fluxo guiado é gravado com `entries = []` e vira
0. Por isso a trava está em três camadas (rota, componente, caso de uso) e tem
teste próprio.

**`is_overridden` é uma afirmação sobre conduta clínica.** Quer dizer "o
aplicador discordou do sistema". Numa transcrição não houve sistema com quem
discordar. Daí `computed_score` anulável em vez do caminho mais curto, que
seria reaproveitar `explicitScore` do `SaveResponse` — ele funcionaria, e
carimbaria o laudo inteiro com um aviso falso.

**Branco = zero é decisão de produto, não de código.** Está registrada no
modal de conclusão e no payload; se um dia mudar, muda nos dois.

**A procedência entra no hash.** Um laudo transcrito não pode ser convertido
em "aplicado no sistema" por edição de banco sem quebrar o `content_hash`.

**Duas grades para o mesmo gráfico.** O laudo lê `MilestoneChart` (do
snapshot); o lançamento lê `ChartGrid` (do catálogo). São fontes diferentes de
propósito — o laudo não pode depender do catálogo atual. O que elas
compartilham é o CSS, e é ali que a semelhança visual tem de ser mantida.

**Fora de escopo nesta fase:** lançar pelo API, importar planilha, editar
transcrição depois de concluída (a saída é cancelar e lançar de novo) e
comparação entre avaliações do mesmo aprendiz — esta última é boa candidata a
uma fase própria, e é o que dá sentido a ter o histórico dentro do sistema.


---

## Registro da execução

Doze pontos em que a implementação divergiu da spec, ou a completou.

**`OpenChartAssessment`, e não criação no controller.** A spec mandava o
`ChartEntryController::store` criar a avaliação. Escrita é da camada de
aplicação (`01-arquitetura.md`); o controller valida e delega. É esse caso de
uso que também carrega a justificativa de não passar pelo guarda de "já
aberta".

**`BuildChartGrid` na camada de aplicação.** O `ChartGrid` é PHP puro, como a
spec exige — então alguém precisa ler o catálogo e as respostas e entregar
arrays a ele. Uma consulta de respostas para a avaliação inteira, não uma por
nível.

**Três habilidades na policy, não uma.** `viewLevelBoard` (abrir a tela do
nível — leitura, que continua valendo depois de concluída), `applyGuided`
(gravar marco a marco: `ItemCard` e `PUT` da API) e `transcribe` (gravar na
grade de lançamento). Uma habilidade só teria de escolher entre negar a
leitura de avaliação concluída, que hoje funciona, e deixar a escrita passar.

**A regra do clique é do domínio.** `ChartGrid::nextScore()` — a tabela de
transições está lá, com teste próprio, e não no componente Livewire.

**Voltar a pendente não é clique.** Como a spec definiu: só `Backspace`,
`Delete` ou `Limpar nível`. O `SaveChartScore` recebe `null` e apaga a
`Response`.

**Pendente e zero se distinguem na tela de lançamento.** No laudo os dois são
quase iguais (branco e `#F8FAFC`) e não precisam ser mais que isso, porque
laudo emitido não tem pendente. Na tela de digitação essa é a diferença mais
importante da grade — o pendente ganhou padrão pontilhado. Só na tela; o CSS
compartilhado do PDF não mudou.

**O tooltip não foi duplicado.** O `graficoMarcos` do relatório fica num
escopo Alpine acima do `gradeMarcos`, que cuida só do teclado e do popover de
toque. Alpine resolve `mostrar()` no escopo de fora.

**Selo também na tela, não só no PDF.** A spec pedia o selo no cabeçalho do
PDF e a faixa nos dois meios. A tela ganhou também o distintivo
`Transcrição — somente leitura` no lugar de `Concluída — somente leitura`:
quem abre o laudo na tela merece a mesma informação de quem abre o papel.

**`Response::upsert`, não `insert`, ao zerar os pendentes.** Não existe hoje
`Response` sem `answered_at` numa transcrição, mas um `insert` cru quebraria
com a chave única em vez de convergir — e a gravação idempotente é premissa
do modelo de dados.

**Teste unitário em `tests/Unit/Domain/Vbmapp/`.** A spec escreveu
`tests/Unit/Vbmapp/`; a convenção do projeto tem o `Domain/` no caminho.

**`Queue::fake()` na maioria dos testes de conclusão.** A fila é síncrona nos
testes e cada conclusão renderiza um PDF de 45 marcos. Com todas as conclusões
gerando PDF, a suíte estourava os 128 MB de `memory_limit` — não por
vazamento, mas por volume. Só o teste que **lê** o PDF gera um de verdade.

**Um teste a mais fora da lista da spec:** o modal de conclusão do lançamento
entrou no `SobreposicaoDeModaisTest`. A tela tem dois ancestrais com
`backdrop-blur` e o botão que abre o modal vive dentro de um deles — é
exatamente a armadilha que aquele teste existe para pegar.
