# 04 — Catálogo VB-MAPP

Referência de dados do instrumento. Consulte ao implementar F1 (seed), F3
(motor) e F5 (níveis 2 e 3).

## Números

**170 marcos**, não 160: 34 pares área/nível × 5 marcos cada.

| Nível | Áreas | Marcos | Numeração |
| --- | --- | --- | --- |
| 1 | 9 | 45 | 1–5 |
| 2 | 12 | 60 | 6–10 |
| 3 | 13 | 65 | 11–15 |
| **Total** | **16 distintas** | **170** | |

A numeração é **contínua por área** através dos níveis: o marco "Mando 7"
pertence ao nível 2. No banco, `position` guarda o número do marco (1–15) e
`level` o nível.

## Matriz de áreas

Confirmada em três fontes independentes: os PDFs de registro
(`docs/testes/`), o manual traduzido (`docs/vmmapp/`) e a planilha de gráfico
(`refs/Grafico VB-MAPP.xlsx`).

| `code` | Nome | Gráfico | N1 | N2 | N3 |
| --- | --- | --- | :-: | :-: | :-: |
| `mando` | Mando | Mando | 1–5 | 6–10 | 11–15 |
| `tato` | Tato | Tato | 1–5 | 6–10 | 11–15 |
| `ouvinte` | Ouvinte | Ouvinte | 1–5 | 6–10 | 11–15 |
| `vpmts` | Percepção Visual e Pareamento ao Modelo | VP/MTS | 1–5 | 6–10 | 11–15 |
| `brincar` | Brincar Independente | Brincar | 1–5 | 6–10 | 11–15 |
| `social` | Comportamento Social e Brincar Social | Social | 1–5 | 6–10 | 11–15 |
| `imitacao` | Imitação Motora | Imitação | 1–5 | 6–10 | — |
| `ecoico` | Ecóico | Ecóico | 1–5 | 6–10 | — |
| `vocal` | Comportamento Vocal | Vocal | 1–5 | — | — |
| `lrffc` | Resposta de Ouvinte por Função, Característica ou Classe | LRFFC | — | 6–10 | 11–15 |
| `intraverbal` | Intraverbal | Intraverbal | — | 6–10 | 11–15 |
| `grupo` | Comportamento em Grupo e Rotinas de Sala | Grupo | — | 6–10 | 11–15 |
| `linguistica` | Estrutura Linguística | Linguística | — | 6–10 | 11–15 |
| `leitura` | Leitura | Leitura | — | — | 11–15 |
| `escrita` | Escrita | Escrita | — | — | 11–15 |
| `matematica` | Matemática | Matemática | — | — | 11–15 |

**Só exiba as áreas que existem no nível.** O nível 3 não tem Imitação, Ecóico
nem Vocal; o nível 2 não tem Vocal.

> Duas equivalências de nomenclatura do briefing original: "Pareamento" é
> `vpmts` e "Resposta" é `lrffc`. A área **Ecóico** não constava da lista do
> briefing, mas existe nos níveis 1 e 2 — 10 marcos.

## Tipos de resposta

`vbmapp_items.response_type`:

| Tipo | Como o psicólogo registra | Exemplo |
| --- | --- | --- |
| `binary_criteria` | Escolhe entre dois critérios textuais | Ouvinte 1 — contato visual *2 vezes* = ½, *5 vezes* = 1 |
| `counter_free` | Preenche N caixas de texto, uma por exemplar | Mando 2 — nomear 4 mandos diferentes |
| `counter_stimuli` | Marca o check de cada acerto na grade de imagens | Tato 1 — nomeia 2 itens com dica |
| `counter_list` | Marca acertos numa lista fixa vinda do instrumento | Ecóico — as 25 palavras do EESA grupo 1 |
| `matrix` | Grade de item × exemplares | N2 Tato 7 — 50 itens × 3 exemplares |

`counter_list` e `matrix` guardam a lista em `fixed_list` (e as colunas em
`matrix_columns`). Só aparecem a partir do nível 2, com uma exceção: **Ecóico do
nível 1 é `counter_list`** sobre a lista de 25 palavras do EESA, compartilhada
pelos 5 marcos com limiares diferentes.

### O critério de pontuação da `matrix` não é único — descoberto na F5

Há dois marcos `matrix` no instrumento, e cada um conta acerto de um jeito
diferente. `matrix_columns` guarda qual: `{"strategy": "rows_complete" |
"total_cells", "columns": [...]}`.

| Marco | Estratégia | Critério (manual) |
| --- | --- | --- |
| Tato 7 (N2) | `rows_complete` | 1 ponto = 50 **itens** com os 3 exemplares cada; ½ ponto = 25 itens com os 3 exemplares cada. Conta **linha completa**. |
| Tato 11 (N3) | `total_cells` | 1 ponto = 15 testagens (grade toda); ½ ponto = 10 testagens, qualquer combinação. Conta **total de células certas**, não linhas. |

Tato 7 tem `fixed_list` com os 50 itens conhecidos (mais acréscimo permitido).
Tato 11 **não tem** `fixed_list` — os 5 objetos são escolhidos livremente pelo
psicólogo durante a aplicação; todas as linhas vêm de acréscimo.

**Dois limiares chegaram errados da inferência da F1**, achados só ao ler o
critério com atenção para decidir a estratégia: Tato 7 tinha
`threshold_half = 3` (deveria ser 25 — o extrator pegou o número dos
exemplares, não o dos itens); Tato 11 tinha `threshold_half = 5` (deveria ser
10, mesmo padrão de erro). Corrigidos via `_pos_inferencia` nas correções —
ver F1 e F5 para o mecanismo.

## Cálculo de pontuação

```
acertos = entradas preenchidas ou marcadas em response_entries

score = acertos >= threshold_full                          -> 1.0
      : threshold_half !== null && acertos >= threshold_half -> 0.5
      : 0.0
```

Duas ressalvas que o algoritmo sozinho não cobre:

**1. `threshold_half` pode ser nulo.** O marco então só admite 0 ou 1.

**2. `scoring_mode = assisted`.** Alguns critérios têm componente qualitativo que
a contagem não resolve. Caso real — **Mando 4**: 1 ponto para 5 mandos
espontâneos *diferentes*, ½ ponto para 5 mandos espontâneos *sempre com a mesma
palavra*. A contagem é idêntica; o que difere é a qualidade. Nesses marcos o
sistema calcula uma sugestão e **exige confirmação explícita** do psicólogo antes
de gravar.

## Confiabilidade das fontes

Isto governa a fase F1 e não é negociável.

| Fonte | Confiável para | Não confiável para |
| --- | --- | --- |
| `docs/vmmapp/Vb-mapp traduzido .pdf` | **Limiares, critérios, objetivos** | — |
| `docs/testes/vbmapp-nivel-N-registro.pdf` | Enunciados, estrutura, critérios de `binary_criteria` (escritos por extenso) | **Limiares dos tipos `counter_*`** |

Nos PDFs de registro, a marcação `½` dos itens de contagem é **posicionamento
gráfico**, sem correspondência com o critério real. Exemplo: Tato 3 desenha 6
caixas com o `½` ao lado da primeira, enquanto o manual determina ½ para 5 itens.

Nos itens `binary_criteria` o registro escreve os dois critérios por extenso
("Faz contato visual 2 vezes / 5 vezes") — aí ele é confiável e bate com o manual.

### Dois formatos de bloco no manual

Descoberto na F1. O manual usa **dois formatos**, e ignorar isso faz Ecóico e
Vocal desaparecerem por inteiro:

- **Formato A** (155 marcos): código `3-M`, marcadores em maiúscula e linha
  própria (`OBJETIVO`, `1 PONTO`, `½ PONTO`).
- **Formato B** (Ecóico e Vocal, 15 marcos): código `1 M` sem hífen, marcadores
  inline e em minúscula (`Objetivo:`, `1 ponto:`).

O separador do código varia mesmo dentro do formato A — `7-M`, `7- M`, `9 –M`
(travessão), `5M`. Qualquer parser precisa tolerar as quatro formas.

### Nome do subteste ecóico: APCE ou EESA

O manual chama de **APCE**; os PDFs de registro chamam de **EESA**. Pelo
contexto são o mesmo instrumento, com traduções diferentes. **A confirmar na
revisão clínica.**

### Conflito conhecido entre as fontes

**Ouvinte 2-M** — "Responde ao ouvir seu próprio nome por 5 vezes".

- Manual (p. 80): *"½ PONTO — Não há ½ ponto para esta habilidade."*
- Registro nível 1 (p. 4): oferece "Responde 2 vezes = ½".

O manual prevalece: `threshold_half = null`, `criteria_half = null`,
`response_type = binary_criteria` degenerado em opção única. **Confirmar com a
psicóloga na F1** e registrar a decisão aqui.

### Vazamento de fronteira entre blocos — achados na F6, pendentes de revisão

Ao inspecionar o formulário impresso pela primeira vez (F6), dois marcos
mostraram texto de outro bloco colado ao seu:

- **`vpmts:3`** — `criteria_half` termina em "...por 15 segundos." e emenda
  direto com "PV-MTS Coloca 3 itens em um recipiente, empilha 3 blocos ou
  coloca 3 anéis", que é o **enunciado de `vpmts:4`**, não parte do critério
  de `vpmts:3`.
- **`brincar:4`** — `statement` começa cortado: "em uma haste. Verificar se a
  criança realiza duas destas atividades ou atividades similares." — é a
  cauda de uma frase cujo início não foi capturado.

Ambos são vazamento de fronteira na extração do manual (F1), no mesmo arquivo
`ManualImporter` que resolveu os outros ~18 casos difíceis. Não corrigidos
aqui: a correção certa exige olhar o texto bruto do manual nesses dois pontos
e decidir o corte, o que é trabalho da revisão clínica, não uma inferência de
código. **Adicionar aos itens a conferir na revisão pendente da F1.**

## Catálogo do nível 1

Ponto de partida do seeder e base dos testes da F1 e F3.

Coluna **Fonte**: `manual` = limiar extraído e verificado no manual;
`registro` = critérios escritos por extenso no PDF de registro;
`confirmar` = **exige revisão clínica antes do congelamento**.

### Mando — `counter_free`

| # | Enunciado | ½ | 1 | Fonte |
| --- | --- | :-: | :-: | --- |
| 1 | Emite 2 palavras, sinais ou ícones selecionados, mas pode necessitar de ajuda ecóica, imitativa ou outro tipo de ajuda, sem ajuda física. | 1 | 2 | manual |
| 2 | Emite 4 diferentes mandos sem ajuda, exceto "O que você quer?". | 3 | 4 | manual |
| 3 | Generaliza 6 mandos entre 2 pessoas, 2 ambientes e 2 exemplos diferentes de um reforçador. | 3 | 6 | manual |
| 4 | Espontaneamente, emite (sem instruções verbais) 5 mandos. | 5¹ | 5 | manual |
| 5 | Emite 10 mandos diferentes sem dica. | 8 | 10 | manual |

¹ `scoring_mode = assisted`. Mesma contagem nos dois critérios; ½ quando os 5
mandos usam sempre a mesma palavra, 1 quando são diferentes.

### Tato

| # | Enunciado | Tipo | ½ | 1 | Material | Fonte |
| --- | --- | --- | :-: | :-: | :-: | --- |
| 1 | Nomeia 2 itens com dica ecóica ou imitativa. | `counter_stimuli` | 1 | 2 | sim | manual |
| 2 | Nomeia 4 itens quaisquer sem dica ecóica ou imitativa. | `counter_stimuli` | 3 | 4 | sim | manual |
| 3 | Nomeia 6 itens não reforçadores. | `counter_stimuli` | 5 | 6 | sim | manual |
| 4 | Nomeia espontaneamente (sem ajuda verbal) 2 itens diferentes. | `counter_free` | 1 | 2 | não | manual |
| 5 | Nomeia 10 itens (objetos comuns, partes do corpo, pessoas, figuras). | `counter_stimuli` | 8 | 10 | sim | manual |

### Ouvinte

| # | Enunciado | Tipo | ½ | 1 | Obs. | Fonte |
| --- | --- | --- | :-: | :-: | :-: | --- |
| 1 | Atende para uma voz falante fazendo contato visual por 5 vezes. | `binary_criteria` | 2 | 5 | 30 min | manual |
| 2 | Responde ao ouvir seu próprio nome por 5 vezes. | `binary_criteria` | **nulo** | 5 | — | manual — ver conflito acima |
| 3 | Olha, toca ou aponta para o membro da família, animal ou reforçador correto num arranjo de 2, para 5 reforçadores. | `counter_stimuli` | 2 | 5 | — | manual |
| 4 | Executa 4 ações motoras diferentes quando solicitado, sem dica visual. | `counter_free` | 2 | 4 | — | manual |
| 5 | Seleciona o item correto num arranjo de 4, para 20 itens ou figuras. | `counter_stimuli` | 15 | 20 | — | manual |

### Percepção Visual e Pareamento (VP/MTS)

| # | Enunciado | Tipo | ½ | 1 | Obs. | Fonte |
| --- | --- | --- | :-: | :-: | :-: | --- |
| 1 | Visualmente rastreia estímulos em movimento por 2 segundos, 5 vezes. | `binary_criteria` | 3 | 5 | 30 min | registro |
| 2 | Pega pequenos objetos com movimento de pinça, 5 vezes. | `counter_free` | ? | 5 | — | **confirmar** |
| 3 | Permanece olhando para um brinquedo ou livro por 30 segundos. | `binary_criteria` | 15 s | 30 s | — | registro |
| 4 | Coloca 3 itens em um recipiente, empilha 3 blocos ou atividade similar. | `counter_free` | ? | 3 | — | **confirmar** |
| 5 | Pareia quaisquer 10 itens idênticos. | `counter_stimuli` | ? | 10 | — | **confirmar** |

### Brincar Independente

| # | Enunciado | Tipo | ½ | 1 | Obs. | Fonte |
| --- | --- | --- | :-: | :-: | :-: | --- |
| 1 | Manipula e explora objetos por 1 minuto. | `binary_criteria` | 30 s | 1 min | 30 min | registro |
| 2 | Mostra variação no brincar, interagindo com 5 itens diferentes. | `counter_free` | ? | 5 | — | **confirmar** |
| 3 | Demonstra generalização em ambiente novo por 2 minutos. | `binary_criteria` | 1 min | 2 min | 30 min | registro |
| 4 | Envolve-se em brincadeiras com movimento por 2 minutos. | `binary_criteria` | 1 min | 2 min | — | registro |
| 5 | Envolve-se em brincadeiras de causa e efeito por 2 minutos. | `binary_criteria` | 1 min | 2 min | 30 min | registro |

### Comportamento Social e Brincar Social

| # | Enunciado | Tipo | ½ | 1 | Obs. | Fonte |
| --- | --- | --- | :-: | :-: | :-: | --- |
| 1 | Faz contato visual como forma de mando, 5 vezes. | `binary_criteria` | 3 | 5 | 30 min | registro |
| 2 | Demonstra que quer ser segurado ou brincar fisicamente, 2 vezes. | `binary_criteria` | 1 | 2 | 60 min | registro |
| 3 | Espontaneamente faz contato visual com outras crianças, 5 vezes. | `counter_free` | ? | 5 | 30 min | **confirmar** |
| 4 | Espontaneamente envolve-se em brincadeira paralela por 2 minutos. | `binary_criteria` | 1 min | 2 min | 30 min | registro |
| 5 | Espontaneamente segue os pares ou imita seus movimentos, 2 vezes. | `binary_criteria` | 1 min | 2 min | 30 min | **confirmar** ² |

² O enunciado fala em "2 vezes" mas o registro oferece critérios em minutos.
Divergência interna do documento — resolver com a psicóloga.

### Imitação Motora

| # | Enunciado | Tipo | ½ | 1 | Fonte |
| --- | --- | --- | :-: | :-: | --- |
| 1 | Imita 2 movimentos motores grossos sob "Faça igual". | `counter_free` | ? | 2 | **confirmar** |
| 2 | Imita 4 movimentos motores sob "Faça igual". | `counter_free` | ? | 4 | **confirmar** |
| 3 | Imita 8 movimentos motores, sendo 2 com objetos. | `counter_free` | ? | 8 | **confirmar** |
| 4 | Imita espontaneamente comportamentos motores de outros em 5 ocasiões. | `binary_criteria` | 2 | 5 | registro |
| 5 | Imita 20 movimentos motores de qualquer tipo. | `counter_free` | ? | 20 | **confirmar** |

### Ecóico nível 2 (6–10): um gap de conteúdo genuíno

O manual descreve os marcos 7 a 10 por **pontuação bruta num subteste externo**
("Marca 60 no subteste EESA") e remete aos Grupos 3, 4 e 5 do protocolo
APCE/EESA — que não estão em `docs/` (só o *Milestone Assessment EESA
Protocol*, em avbpress.com, tem essas listas). Só o Grupo 2 (30 palavras)
apareceu no registro do nível 2, suficiente para o marco 6.

Reclassificados de `counter_list` para **`binary_criteria`** na F5: sem lista
de palavras real para os grupos 3-5, fingir um checklist seria pior que admitir
que o psicólogo aplica o subteste externo e escolhe qual critério foi
atingido. Os textos de `criteria_full`/`criteria_half` já são literais do
registro.

### Ecóico — `counter_list`

Os cinco marcos compartilham a mesma `fixed_list`: as **25 palavras do subteste
EESA grupo 1**.

```
Ah, eu, tchau, muu, não, sim, toc toc, mão, pé, um, meu, miau, trim,
auau, oi, ei, sol, lua, trem, có có, bi bi, ai, lá, pó, dá
```

| # | Enunciado | ½ | 1 | Fonte |
| --- | --- | :-: | :-: | --- |
| 1 | Fala 2 palavras do subteste EESA. | 1 | 2 | registro |
| 2 | Fala 5 palavras do subteste EESA. | 3 | 5 | registro |
| 3 | Fala 10 palavras do subteste EESA. | 7 | 10 | registro |
| 4 | Fala 15 palavras do subteste EESA. | 12 | 15 | registro |
| 5 | Fala 25 palavras do subteste EESA. | 20 | 25 | registro |

### Comportamento Vocal — `binary_criteria`, observação de 60 min

| # | Enunciado | ½ | 1 | Fonte |
| --- | --- | :-: | :-: | --- |
| 1 | Espontaneamente emite média de 5 sons por hora. | 2 | 5 | registro |
| 2 | Espontaneamente emite 5 sons diferentes, média total de 10 por hora. | 3 | 5 | registro |
| 3 | Espontaneamente emite 10 sons diferentes com entonações variadas, total de 25 por hora. | 5 | 10 | registro |
| 4 | Espontaneamente emite 5 aproximações de palavras inteiras diferentes. | 2 | 5 | registro |
| 5 | Espontaneamente vocaliza 15 palavras inteiras ou frases com entonação e ritmo apropriados. | 8 | 15 | registro |

## Material de aplicação

`docs/material-aplicacao/` — 261 páginas em 5 PDFs, slides 16:9 (1440×810 pt).

| Arquivo | Páginas | Marcos cobertos |
| --- | --- | --- |
| `nivel1/Material-Aplicacao-nivel-1.pdf` | 28 | Tato 1, 2, 3, 5 · Ouvinte 3, 5 · Percepção 5 |
| `nivel2/…-pt1.pdf` | 57 | Tato 6, 7 |
| `nivel2/…-pt2.pdf` | 51 | Ouvinte 7, 9 · Percepção 6, 7, 8, 9 |
| `nivel2/…-pt3.pdf` | 20 | LRFFC 6, 7, 8, 9 |
| `nivel3/Material-Aplicacao-Nivel-3.pdf` | 105 | Tato 11–14 · Ouvinte 11+ · e outros |

Os cabeçalhos de página (`TATO 1 e 2`, `OUVINTE 3`, `PERCEPÇÃO 5`) mapeiam página
→ marco, e correspondem exatamente aos itens marcados "Tem no material de
aplicação" nos PDFs de registro.

As páginas de conteúdo são grades limpas de estímulos sobre fundo branco, com boa
separação — condição favorável ao recorte automático. Cada página traz tipicamente
4 estímulos. Estimativa de **~96 estímulos no nível 1**.

Na v1 só o nível 1 é recortado em estímulos individuais (F4). Níveis 2 e 3 usam
páginas inteiras num visualizador, com contagem manual (F5).
