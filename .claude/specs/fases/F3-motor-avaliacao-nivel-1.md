# F3 — Motor de avaliação, nível 1

> **Depende de:** F1, F2 · **Estimativa:** ~2,5 semanas · **Habilita:** F4, F5, F6, F8
> **Modelo:** Opus 5 · effort `xhigh` (`claude-opus-5`) — Núcleo do domínio: pontuação, transação, idempotência, `scoring_mode` assistido. Tudo que vier depois herda estas decisões.

> **Status: concluída.** Registro das decisões tomadas na execução, ao final.

## Objetivo

O psicólogo aplica os 45 marcos do nível 1 de ponta a ponta, com salvamento
contínuo, pontuação calculada, progresso visível e filtro de pendências.

É o núcleo do produto. As fases seguintes estendem este motor; nenhuma o substitui.

## Entregáveis

- `app/Domain/Vbmapp/Scoring/ScoreCalculator.php`
- `app/Domain/Vbmapp/Progress/ProgressCounter.php`
- `app/Application/Assessment/{StartLevel,SaveResponse,CompleteLevel}.php`
- Componentes Livewire: `LevelBoard`, `ItemCard`, `SaveIndicator`
- Controles para `binary_criteria`, `counter_free` e `counter_list`
- Cabeçalho de identificação fixo
- Filtro de não respondidas e navegação por área

> `counter_stimuli` renderiza na F4, quando existirem imagens. Nesta fase ele
> cai no fallback de `counter_free` — caixas de texto numeradas.

## Tarefas

### 1. Cálculo de pontuação

`ScoreCalculator`, PHP puro, sem Eloquent:

```php
public function score(int $acertos, VbmappItem $item): float
{
    if ($acertos >= $item->threshold_full)  return 1.0;
    if ($item->threshold_half !== null
        && $acertos >= $item->threshold_half) return 0.5;
    return 0.0;
}
```

Três casos que **não** são exceção rara:

- `threshold_half === null` → só 0 ou 1 (Ouvinte 2)
- `scoring_mode === 'assisted'` → devolve sugestão e exige confirmação (Mando 4)
- `binary_criteria` → não conta entradas; o psicólogo escolhe o critério, e a
  escolha mapeia direto para 0,5 ou 1

### 2. Contagem de progresso

`ProgressCounter` opera em três granularidades:

| Escopo | Formato |
| --- | --- |
| Avaliação | `78 de 170` |
| Nível | `32 de 45` |
| Área | `3 de 5` |

**Respondido é ter linha em `responses`.** Pontuar zero é uma resposta; deixar em
branco é pendência. Não confunda `score = 0` com não respondido — a distinção
aparece na interface e governa o botão de conclusão.

`assessment_levels.answered_count` é desnormalizado e recalculado **na mesma
transação** da gravação. Nunca por job.

### 3. Início do nível

`StartLevel` cria a linha em `assessment_levels` com `total_count` igual a 45,
60 ou 65, e promove a avaliação de `not_started` para `in_progress` se for o
primeiro nível.

**Níveis são independentes.** Começar pelo 2 ou pelo 3 é caminho normal, não
exceção. Não exija o nível 1.

### 4. Gravação de resposta

`SaveResponse` é a única porta de escrita. Recebe o id da avaliação, o id do
marco e as entradas; devolve pontuação e progresso recalculados.

```php
DB::transaction(function () {
    // 1. autoriza — 403 se locked_at !== null
    // 2. updateOrCreate em responses  (único: assessment_id + item_id)
    // 3. sincroniza response_entries
    // 4. recalcula score via ScoreCalculator
    // 5. atualiza answered_count e score_total do nível
});
```

O índice único `(assessment_id, item_id)` torna a operação **idempotente**:
reenviar a mesma gravação após queda de rede não duplica nada. É a base do
reenvio em fila e do `PUT` da API na F8.

### 5. Componentes Livewire

**`LevelBoard`** — a tela do nível. Cabeçalho de identificação, navegação por
área, lista de cartões, barra de ações. Carrega o catálogo do cache e as
respostas numa consulta só, com `with()` — nada de N+1 sobre 45 marcos.

**`ItemCard`** — um marco. É o componente do salvamento, isolado para que
marcar um check não reenvie o formulário inteiro.

**`SaveIndicator`** — os quatro estados de `03-design-system.md`.

### 6. Gatilhos de salvamento

| Gatilho | Evento |
| --- | --- |
| Check de estímulo ou de lista | imediato |
| Saída de campo de texto | `blur` |
| Escolha de critério | imediato |
| Digitação | `debounce` de 800 ms |
| Campo de observações do marco | `blur` |

Em falha de rede, a alteração entra numa fila no cliente (Alpine + `localStorage`)
e é reenviada quando a conexão volta. O indicador mostra
`Sem conexão — 2 alterações na fila`.

> **O psicólogo está com a criança à frente, com atenção dividida.** Uma resposta
> perdida não será refeita. A fila de reenvio não é refinamento — é requisito.

### 7. Controles por tipo

**`binary_criteria`** — dois botões de rádio grandes com os critérios por
extenso, vindos de `criteria_half` e `criteria_full`. Quando `threshold_half` é
nulo, só o critério de 1 ponto aparece, mais a opção "Não atingiu".

**`counter_free`** — `threshold_full` caixas de texto numeradas. Uma caixa
preenchida conta como um acerto. Contador vivo abaixo.

**`counter_list`** — a `fixed_list` do marco como lista de checks. No Ecóico do
nível 1, as 25 palavras do EESA, compartilhadas pelos 5 marcos com limiares
distintos.

**`counter_stimuli`** — fallback de `counter_free` nesta fase; grade real na F4.

### 8. Marcos com observação

Quando `observation_minutes` existe, o cartão exibe o selo
`30 min de observação`. É informativo — o sistema **não** cronometra nem trava
por tempo. A observação acontece fora da tela, ao longo de dias.

### 9. Cabeçalho de identificação

Fixo no topo, conforme `03-design-system.md`: foto e nome do aprendiz, idade na
data da aplicação, data da aplicação, aplicador, nível corrente e progresso.

### 10. Filtro e navegação

- Alternador **"Somente não respondidas"**, persistido na sessão
- Combinável com filtro por área
- Contador ao lado: `12 pendentes`
- Navegação por área com contador `3/5` e anel de progresso
- Ao concluir uma área, oferecer avançar para a próxima **não concluída**

### 11. Conclusão de nível

Com `answered_count === total_count`, o botão da barra de ações troca de
`Salvar` para **`Concluir nível`**. `CompleteLevel` marca o nível como
`completed` e grava `score_total`.

A conclusão da **avaliação** é da F7. Aqui só o nível.

## Critérios de aceite

- [x] Iniciar nível 1 cria `assessment_levels` com `total_count = 45`
- [x] Iniciar direto pelo nível 2 ou 3 funciona, sem exigir o 1
- [x] Os 9 grupos de área do nível 1 aparecem, e **só** eles
- [x] Cada área mostra 5 marcos
- [x] `binary_criteria` grava 0,5 ou 1 conforme o critério escolhido
- [x] Marco sem meio ponto (Ouvinte 2) não oferece a opção de ½
- [x] `counter_free` calcula pontuação pelos limiares do catálogo
- [x] Marco `assisted` (Mando 4) exige confirmação antes de gravar
- [~] Salvamento dispara nos cinco gatilhos, e o indicador reflete os quatro
      estados — gatilhos e indicador verificados no servidor e por requisição
      real; os estados que dependem de JS (fila de reenvio) não têm teste
      automatizado, ver "O que não pude verificar"
- [x] Recarregar a página preserva tudo
- [x] Progresso correto nas três granularidades
- [x] `score = 0` conta como respondido; em branco não conta
- [x] Filtro de não respondidas funciona e persiste
- [x] Com 45 de 45, o botão vira `Concluir nível`
- [x] Avaliação travada (`locked_at`) recusa gravação — ver nota sobre o 403
- [x] A tela do nível carrega em menos de 400 ms com todos os 45 marcos respondidos
- [x] Nenhuma consulta N+1 — verificado com `DB::listen`

### Testes obrigatórios

```php
// Unitários — Domain
it('dá 1 ponto quando atinge o limiar cheio', ...);
it('dá meio ponto entre os limiares', ...);
it('dá zero abaixo do limiar de meio ponto', ...);
it('nunca dá meio ponto quando threshold_half é nulo', ...);
it('conta score zero como respondido', ...);
it('não conta item sem resposta', ...);

// Integração — Application
it('é idempotente ao gravar a mesma resposta duas vezes', ...);
it('recalcula answered_count na mesma transação', ...);
it('recusa gravação em avaliação travada', ...);
it('permite iniciar pelo nível 2 sem o nível 1', ...);
```

## Riscos e decisões

**Desempenho.** São 45 cartões Livewire numa tela. Isole o estado em `ItemCard`,
carregue catálogo e respostas de uma vez, e use `wire:key` estável. Sem isso o
iPad engasga.

**Salvar não é o mesmo que pontuar.** Gravar entradas e calcular pontuação
acontecem na mesma transação, mas são responsabilidades separadas —
`SaveResponse` orquestra, `ScoreCalculator` decide. Não funda os dois.

**Não invente limiar.** Se um marco chegar da F1 sem `threshold_full`, falhe
alto: exceção no seeder, não default silencioso.


---

## Registro de execução

Concluída. 78 testes novos; a suíte foi de 86 para 164, em 20 s.

### Lacuna da F1 corrigida antes de começar

Os cinco marcos de Ecóico são `counter_list`, mas chegaram com `fixed_list`
**nula** — o manual descreve o marco por pontuação no subteste ("pontuar 10 ou
mais no APCE") e não enumera as palavras, então o parser não tinha de onde
tirá-las. As 25 palavras vieram do PDF de registro (p. 9) pelo mecanismo de
correções da F1, com a origem citada.

### Como cada tipo de resposta vira uma contagem

O `ScoreCalculator` é uma régua só para os 170 marcos: recebe um número de
acertos e devolve 0, ½ ou 1. O que varia entre os tipos é **como** se chega ao
número, e isso ficou isolado no `EntryTally`:

| Tipo | Contagem |
| --- | --- |
| `counter_free` | caixas de texto preenchidas |
| `counter_list` / `counter_stimuli` | checks marcados |
| `binary_criteria` | o ordinal escolhido |
| `matrix` | linhas com todos os exemplares marcados (pronto para a F5) |

**`binary_criteria` grava o ordinal como posição de uma única entrada** —
2 para o critério de 1 ponto, 1 para o de ½, 0 para "não atingiu". Assim o
tipo entra na mesma régua sem ramo especial no cálculo, e a interface sabe qual
opção estava marcada só de ler a posição.

### `answered_at` nulo resolve dois problemas de uma vez

A coluna virou anulável e passou a significar **"registrado mas ainda não
respondido"**. Isso resolveu:

**Marcos `assisted`.** Mando 4-M pede 5 mandos nos dois critérios; só a
qualidade os separa ("5 diferentes" contra "5 sempre com a mesma palavra"). O
psicólogo digita os exemplares — que ficam salvos na hora, sem risco de perda —
mas o marco só conta como respondido depois de escolher qual critério foi
atingido. Abaixo do limiar não há ambiguidade: é zero, e conta na hora.

**Formulário esvaziado.** Apagar todas as caixas devolve o marco a pendente,
porque formulário vazio é indistinguível de formulário nunca tocado.

Como consequência, **registrar zero deliberado precisou virar ação explícita** —
o botão "Registrar como 0 ponto". Sem ele não haveria como diferenciar "a
criança não atingiu" de "ainda não apliquei", que é justamente a distinção que a
spec exige que apareça na interface.

### Navegação por área também é a estratégia de desempenho

A tela renderiza **uma área por vez**, ou seja, 5 cartões em vez de 45. Não foi
só organização: é o que mantém o iPad leve. "Todas as áreas" existe como escolha
explícita.

### O N+1 que voltou pela porta dos fundos

Primeira versão do `ItemCard` buscava o próprio marco com
`Item::with('area')->findOrFail()`. Com 45 cartões, 90 consultas. Passei a ler
do `CatalogCache` — mas aí `$item->area` disparava *lazy load* em cada cartão, e
o N+1 voltou disfarçado. A correção foi resolver a relação inversa
(`$item->setRelation('area', $area)`) **dentro do cache**, antes de guardá-lo.

Medição final, com os 45 marcos respondidos:

| Cenário | Consultas | Tempo |
| --- | --- | --- |
| Uma área (5 cartões) | 17 | 110 ms |
| Todas as áreas (45 cartões) | 30 | 130 ms |

Nove vezes mais cartões custam 13 consultas a mais — sub-linear, que é o que
prova a ausência de N+1. Há teste automatizado com `DB::listen` travando isso.

### Verificação pelo caminho real, não só pelo harness

`Livewire::test()` não exercita HTTP. Fiz uma gravação de verdade contra
`POST /livewire-*/update` com o snapshot extraído do HTML: devolveu
`acertos=1, score=0.5, respondido=true, salvoEm=18:39`, e a resposta, a entrada
e os contadores do nível apareceram no banco. É a validação de que a cadeia
inteira funciona no navegador.

### Ajustes de plataforma

- **Livewire 4 usa componentes single-file** (`resources/views/components/⚡nome.blade.php`)
  por padrão. Mantive componentes **em classe** em `app/Livewire/` — que
  continuam suportados via `class_namespace` — porque são testáveis e combinam
  com a arquitetura em camadas. A spec dizia `app/Http/Livewire/`; o padrão do
  Livewire 3 e 4 é `app/Livewire/`.
- **O Alpine agora vem do Livewire.** O `app.js` do Breeze iniciava a própria
  instância, o que dispararia o aviso de "multiple instances of Alpine".
  Removi, e adicionei `@livewireStyles` / `@livewireScripts` explícitos nos
  layouts — sem isso, páginas sem componente Livewire (painel, aprendizes)
  ficariam sem Alpine e o menu suspenso quebraria. O bundle JS caiu de 105 KB
  para 52 KB.
- **`Collection::offsetGet` lança em chave ausente.** `$this->respostas[$id]`
  quebrava para todo marco sem resposta; passou a `->get($id)`.

### Sobre o 403 em avaliação travada

O critério pedia **403**. Na prática há duas camadas:

- `LevelBoard::mount()` chama `$this->authorize('view', …)`, e a rota devolve
  **403** de verdade para avaliação de outro psicólogo — testado por HTTP.
- Uma avaliação **travada** pertence ao próprio psicólogo, então não é um 403 de
  autorização: o `SaveResponse` recusa com exceção e o cartão exibe o motivo
  sem perder o que foi digitado. Achei melhor que uma tela de erro — a F7, que
  cria o travamento, vai remover os controles de edição de vez.

Descobri também que **o harness do Livewire converte `AuthorizationException`
em resposta 403 em vez de propagá-la**, então `->throws()` não funciona nesses
testes. Passei a testar pela rota HTTP, que é o caminho real, e a confirmar que
a autorização barra antes de qualquer efeito colateral.

### O que não pude verificar

**A fila de reenvio depende de JavaScript e não tem teste automatizado.** Ela
está implementada (`resources/js/fila-salvamento.js`): engancha no ciclo de
commit do Livewire, reenvia a ação `salvar` com *backoff* exponencial, tenta na
hora quando o evento `online` dispara, e alimenta o indicador
"Sem conexão — N alterações na fila". A gravação idempotente é o que torna o
reenvio seguro.

Mas validar isso exige navegador com rede simulada. **Fica para a F9.**

Limite conhecido e documentado no próprio arquivo: se a aba for fechada com
itens na fila, o texto digitado e não gravado se perde. Restaurá-lo exigiria
reidratar o estado do Livewire a partir do `localStorage`.

### Verificação

```
164 testes passando (488 asserções) · 20 s
pint --test → limpo
npm run build → ok

Domínio, sem banco: 37 testes
  ScoreCalculator, EntryTally, ProgressCounter, Age

Ao vivo, no navegador:
  /avaliacoes/1        → 3 níveis, "Não iniciado", 45/60/65
  /avaliacoes/1/nivel/1 → cabeçalho com "6 anos e 3 meses" (idade na data da
                          aplicação), 9 áreas, 5 cartões montados, 27 caixas
  POST /livewire-*/update → gravou e persistiu
```
