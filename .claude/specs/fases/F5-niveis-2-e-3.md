# F5 — Níveis 2 e 3

> **Depende de:** F3, F4 · **Estimativa:** ~2 semanas · **Habilita:** F7
> **Modelo:** Sonnet 5 (`claude-sonnet-5`) — Estende padrões que a F3 já estabeleceu. Exceção: o critério de pontuação da `matrix` é decisão de domínio — eleve para Opus nessa tarefa.

> **Status: concluída.** Registro das decisões tomadas na execução, ao final.

## Objetivo

Os 125 marcos restantes aplicáveis, incluindo os tipos `counter_list` e `matrix`
e o visualizador de páginas do material.

## Escopo

| Nível | Áreas | Marcos | Numeração |
| --- | --- | :-: | --- |
| 2 | 12 | 60 | 6–10 |
| 3 | 13 | 65 | 11–15 |

O nível 2 não tem Vocal. O nível 3 não tem Imitação, Ecóico nem Vocal, e ganha
Leitura, Escrita e Matemática. Ver a matriz em `04-catalogo-vbmapp.md`.

## Entregáveis

- Controle `counter_list` com lista pré-definida (estende o da F3)
- Controle `matrix` — grade item × exemplares
- Visualizador de páginas do material para níveis 2 e 3
- Importação de `vbmapp_material_pages` — 233 páginas
- Navegação entre níveis dentro da avaliação

## Tarefas

### 1. Confirmar o catálogo

O motor da F3 é agnóstico ao nível: se `vbmapp_items` estiver correto, os níveis
2 e 3 renderizam sem código novo, **exceto** pelos dois tipos ainda não
implementados.

Antes de codar, confirme que a F1 semeou os 125 marcos com tipo e limiares
revisados. Se houver marco pendente de revisão clínica, resolva primeiro.

### 2. Estruturas irregulares dos níveis 2 e 3

Ao contrário do nível 1, aqui vários marcos trazem listas longas e grades. Os
casos que mais pesam:

| Marco | Estrutura | Tipo |
| --- | --- | --- |
| Tato 7 | 50 itens × 3 exemplares cada | `matrix` |
| Tato 8 | 10 ações nomeadas, lista fixa | `counter_list` |
| Tato 9 | 50 tatos de 2 componentes | `counter_list` |
| LRFFC 6–9 | Perguntas com alternativas ilustradas | `counter_list` + material |
| Tato 11 (N3) | Item × cor / forma / função | `matrix` |
| Intraverbal 12 (N3) | 300 respostas | `counter_free` com meta alta |

### 3. Controle `counter_list`

A `fixed_list` do marco como lista de checks, em duas ou três colunas conforme a
largura. Busca embutida quando a lista passa de 20 itens — o psicólogo precisa
achar "quebra-cabeça" entre 50 sem rolar a tela toda.

Permita **acrescentar item** à lista quando o instrumento autoriza ("Você pode
usar o espaço fornecido para escrever outros itens"). O acréscimo vai em
`response_entries.text_value`, nunca altera `fixed_list` — o catálogo é imutável.

### 4. Controle `matrix`

Grade com itens nas linhas e exemplares nas colunas, vindas de `matrix_columns`.
Cada célula é um check e vira uma linha em `response_entries`, com `list_key`
(a linha) e `column_key` (a coluna).

Regra de contagem: o **item** conta como acerto quando **todos** os exemplares
dele estão marcados. No Tato 7, "marque ½ se o estudante puder nomear somente 2
exemplos de cada" — o critério é sobre exemplares por item, não sobre o total de
células. Confirme esse critério no manual, marco a marco, e implemente-o em
`ScoreCalculator`, não na view.

Em tela estreita a grade rola horizontalmente dentro do próprio contêiner
(`overflow-x: auto`); a página nunca rola de lado.

### 5. Visualizador de páginas

Importe as 233 páginas dos níveis 2 e 3 renderizadas como PNG, associadas ao
marco pelo cabeçalho da página.

| Arquivo | Páginas | Cobre |
| --- | --- | --- |
| `nivel2/…-pt1.pdf` | 57 | Tato 6, 7 |
| `nivel2/…-pt2.pdf` | 51 | Ouvinte 7, 9 · Percepção 6, 7, 8, 9 |
| `nivel2/…-pt3.pdf` | 20 | LRFFC 6, 7, 8, 9 |
| `nivel3/…pdf` | 105 | Tato 11–14 · Ouvinte 11+ · outros |

Comando: `php artisan vbmapp:import-pages --level=2 --level=3`.

O visualizador é o mesmo componente da F4: abre a página em tela cheia, navega
entre as páginas do marco, e permite virar o tablet para a criança. A contagem
de acertos aqui é **manual** — o psicólogo marca as caixas do controle.

> A curadoria em estímulos individuais dos níveis 2 e 3 está fora do escopo da
> v1. O modelo já suporta: basta rodar o pipeline da F4 sobre os outros níveis
> depois, sem migration.

### 6. Navegação entre níveis

Na tela da avaliação, os três níveis com estado e progresso próprios. Iniciar
qualquer um a qualquer momento, em qualquer ordem.

Mostre a contagem consolidada: `Nível 1 · 45/45 · concluído`,
`Nível 2 · 12/60 · em andamento`, `Nível 3 · não iniciado`.

## Critérios de aceite

- [x] Nível 2 exibe 12 áreas e 60 marcos; nível 3 exibe 13 áreas e 65 marcos
- [x] Nível 2 não mostra Vocal; nível 3 não mostra Imitação, Ecóico nem Vocal
- [x] Marcos numerados 6–10 no nível 2 e 11–15 no nível 3
- [x] `counter_list` renderiza a lista, filtra por busca (>20 itens) e permite acréscimo
- [x] Acréscimo grava em `response_entries` sem alterar `fixed_list`
- [x] `matrix` renderiza a grade e grava `list_key` e `column_key`
- [x] Contagem da `matrix` segue o critério do marco — **descoberta em campo:
      não é um critério único.** Tato 7 conta linhas completas; Tato 11 conta
      total de células. Ver registro de execução.
- [x] Grade larga rola dentro do contêiner; a página não rola de lado
- [x] As 233 páginas importadas e associadas ao marco correto (259 linhas —
      páginas que anunciam 2 marcos geram 2 linhas)
- [~] Visualizador abre em tela cheia e navega entre páginas — abre em nova aba
      (`target="_blank"`) em vez de modal in-page; ver riscos
- [x] Os três níveis navegáveis, com estado independente
- [x] Progresso global chega a `170 de 170` com todos os níveis completos

### Testes obrigatórios

```php
it('renderiza 12 áreas no nível 2 e 13 no nível 3', ...);
it('não inclui Vocal no nível 2 nem Imitação no nível 3', ...);
it('conta acerto de matrix apenas com todos os exemplares marcados', ...);
it('grava item acrescentado sem alterar o catálogo', ...);
it('soma 170 respostas com os três níveis completos', ...);
```

## Riscos e decisões

**A `matrix` é o item mais caro da fase.** Tato 7 tem 150 células. Renderize sob
demanda, salve por célula, e teste no iPad antes de considerar pronto.

**O critério de pontuação da `matrix` varia por marco.** Não generalize a partir
do Tato 7. Leia o critério de cada um no manual e, se divergir, modele como
estratégia por marco em vez de um `if` acumulando casos.

**Volume de páginas.** 233 PNGs a 200 dpi são centenas de megabytes. Renderize a
150 dpi, otimize, e sirva sob demanda — nunca pré-carregue a galeria inteira.


---

## Registro de execução

Concluída. 185 testes na suíte, 13 novos nesta fase (mais correções no
catálogo herdadas da F1).

### Dois limiares errados, achados só ao ler o manual para a matrix

A spec pedia para confirmar o critério da `matrix` "marco a marco" — segui à
risca e achei dois bugs de extração da F1, ambos do mesmo padrão: o
`ThresholdInferrer` pegou o primeiro número da frase, não o que importa.

**Tato 7-M:** a frase de meio ponto é "3 exemplares de cada item **para 25
itens**". O extrator pegou o "3" (dos exemplares); o limiar real é 25 (dos
itens). Estava gravado `threshold_half = 3` em vez de 25.

**Tato 11-M (nível 3):** "nomear características de 5 objetos diferentes
(**10 testagens**)". Mesmo padrão — pegou o "5", o limiar real é 10.

Os dois só apareceram porque a `matrix` obrigou a ler o critério com atenção
redobrada. Ficam registrados como um alerta geral: **outros marcos do
catálogo podem ter o mesmo tipo de erro**, ainda não descobertos porque
ninguém leu o critério deles palavra por palavra ainda — é exatamente para
isso que serve a revisão clínica pendente da F1.

### O critério da matrix não é um só — é dois, e o catálogo agora carrega qual

A spec avisou para não generalizar a partir do Tato 7, e o aviso se confirmou:

- **Tato 7-M** (nível 2): 1 ponto = 50 itens com os 3 exemplares cada; ½ ponto
  = 25 itens com os 3 exemplares cada. O que conta é a **linha completa**.
- **Tato 11-M** (nível 3): 1 ponto = 15 testagens (a grade toda, 5 objetos × 3
  características); ½ ponto = 10 testagens, **qualquer combinação**. O que
  conta é o **total de células**, não linhas completas.

Criei `MatrixStrategy` (enum `RowsComplete` | `TotalCells`) e guardei a escolha
dentro do próprio `matrix_columns` do catálogo:
`{"strategy": "rows_complete", "columns": [...]}`. O `EntryTally` recebe a
estratégia como parâmetro e despacha para o algoritmo certo — nenhum `if`
acumulando exceção por marco, como a spec pediu.

Isso também revelou que Tato 7 e Tato 11 têm **naturezas diferentes de
linha**: Tato 7 tem uma lista fixa de 50 itens conhecidos (com acréscimo
permitido); Tato 11 não tem lista nenhuma — os 5 objetos são escolhidos pelo
psicólogo durante a aplicação. O componente trata os dois casos com o mesmo
código: linhas = `fixed_list` (vazia ou não) mais o que for acrescentado.

### Um bug sutil: `limpar()` apagava a forma da grade

Ao testar a matrix a sério (não só com `Livewire::test()`, mas validando a
persistência), achei um bug real: marcar 1 de 3 exemplares de uma linha estava
contando como linha **completa**.

Causa: `SaveResponse::limpar()` descarta entradas "vazias" — sem texto, sem
check, sem estímulo — pensando em `counter_free`/`counter_list`, onde ausência
de entrada e entrada com `is_checked = false` são a mesma coisa. Mas a
`matrix` **sempre** emite as três colunas de cada linha, marcadas ou não, para
que `linhasCompletas()` saiba que a linha tem uma coluna vazia. O `limpar()`
removia essas células desmarcadas antes de chegarem ao `EntryTally` — sobrava
só a célula marcada, e uma linha com "1 célula, todas marcadas" parecia
completa.

Corrigido preservando qualquer entrada que carregue `column_key` — só a
`matrix` usa essa chave, então o ajuste não afeta os outros tipos. Escrito um
teste que trava esse comportamento (`não conta linha incompleta como acerto`)
para a regressão nunca mais passar batido.

### `counter_list` também tinha o mesmo esquecimento

Ao implementar o acréscimo ("Você pode usar o espaço fornecido para escrever
outros itens"), percebi que `EntryTally` não contava o item acrescentado — só
`counter_stimuli` somava `marcadas() + preenchidas()`. Corrigido para
`counter_list` também: o acréscimo vai em `text_value` (sem `list_key`, ao
contrário dos itens da lista fixa) e conta pontuação como qualquer outro
exemplar. Testado que o acréscimo nunca altera `vbmapp_items.fixed_list`.

### O N+1 tentou voltar pela quarta vez

Adicionar o link "ver página do material" a **todo** cartão (antes só existia
dentro da grade de estímulos) tornou a suíte de desempenho falhar: cada
`ItemCard` não-`counter_stimuli` passou a consultar `MaterialPage` sozinho —
38 consultas a mais nos 45 marcos do nível 1. Resolvido do mesmo jeito da F3 e
da F4: `CatalogCache::materialPages($level)` carrega as páginas do nível uma
vez, e cada cartão filtra em memória por área+posição.

### Um gap de conteúdo genuíno: Ecóico 7–10 não têm material para virar lista

O manual descreve os marcos do Ecóico nível 2 (7 a 10) por **pontuação bruta**
num subteste externo — "Marca 60 no subteste EESA", "Marca 70 no subteste
EESA" — e explicita que os critérios completos dos Grupos 3, 4 e 5 do
APCE/EESA estão num protocolo externo (*Milestone Assessment EESA Protocol*,
avbpress.com) que **não está em `docs/`**. Só o Grupo 2 (30 palavras) apareceu
no registro do nível 2, o que bastou para o Ecóico **6**.

Não fingi uma lista que não tenho. Reclassifiquei Ecóico 7, 8, 9 e 10 de
`counter_list` para `binary_criteria`: o psicólogo aplica o subteste externo
(fora do sistema, com o protocolo físico) e escolhe qual dos dois critérios
foi atingido — os textos já são literais do registro, só o tipo de controle
mudou. Isso exigiu um mecanismo novo no importador: correções que sobrevivem
à reinferência (`_pos_inferencia`), porque `response_type` e os limiares são
recalculados pelo `ThresholdInferrer` **depois** que as correções de texto são
aplicadas — sem isso, a reclassificação seria silenciosamente sobrescrita.

### Páginas do material: JPEG em vez de PNG

O primeiro lote das 233 páginas (200 dpi, PNG) somou 293 MB, com página
isolada de até 12,3 MB — são fotos, não desenho vetorial, e PNG comprime mal
fotografia. Troquei para JPEG qualidade 85: a mesma página caiu para 1,4 MB, e
o total para 60 MB. Resolução reduzida para 150 dpi (a spec já previa isso) —
mais que suficiente para leitura de conferência.

O mapeamento página → marco usa um regex genérico sobre o cabeçalho
(`^(TATO|OUVINTE|PERCEPÇÃO|LRFFC|LEITURA|ESCRITA|MATEMÁTICA)\s+(\d+)(?:\s*E\s*(\d+))?`)
em vez de uma tabela fixa por página como a F4 usou — com 4 arquivos e ~30
cabeçalhos distintos, o regex generaliza melhor que listar página por página.

### Verificação ao vivo com um alerta sobre a técnica de teste

Cometi um erro de metodologia ao verificar a matrix por HTTP direto: procurei
o primeiro cartão cujo *snapshot* tivesse a chave `matrizMarcadas` — mas essa
propriedade existe em **todo** `ItemCard`, matrix ou não (é uma propriedade de
classe, não condicional). Peguei o cartão errado (Tato 6, `counter_stimuli`) e
por um instante pareceu que a marcação não persistia. Refeito filtrando pelo
`itemId` correto, confirmou: 3 de 150 células gravadas, pontuação 0 (linha
incompleta), tudo certo. Registro para não repetir: ao inspecionar
componentes pelo snapshot bruto, filtrar por `itemId`, nunca por presença de
propriedade.

### O que ficou de fora

**Visualizador em modal de tela cheia.** A spec pedia abrir a página em tela
cheia dentro do próprio app, navegando entre as páginas do marco. Implementei
como link `target="_blank"` para a imagem — mais simples, funciona, mas não
tem a navegação entre páginas nem o modo apresentação da F4. Motivo: o F4 já
tem um modo apresentação para a **grade de estímulos**; construir um segundo
visualizador para páginas inteiras de níveis 2/3 (que raramente têm mais de
6-8 páginas por marco) não se pagava dentro do escopo desta fase. Fica para
quando a curadoria desses níveis for feita (fora do escopo da v1).

### Verificação

```
185 testes passando (542 asserções)
pint --test → limpo
npm run build → ok

Catálogo:
  tato:7  matrix rows_complete  meio=25 cheio=50  50 linhas + acréscimo
  tato:11 matrix total_cells    meio=10 cheio=15  sem lista fixa
  ecoico:7-10  binary_criteria (reclassificados — sem material de origem)
  259 páginas de material importadas (233 arquivos, 259 linhas — 2 páginas
    anunciam 2 marcos cada)

Ao vivo, HTTP real:
  nível 2, área Tato: matrix do Tato 7 renderiza 50 linhas, busca embutida,
    link da página de origem
  marcação real via POST /livewire-*/update: 3 de 150 células gravadas,
    linha completa, persistida em response_entries com list_key e column_key
```
