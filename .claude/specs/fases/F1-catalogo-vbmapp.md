# F1 — Catálogo VB-MAPP

> **Depende de:** F0 · **Estimativa:** ~2 semanas · **Habilita:** F3
> **Caminho crítico.** Tudo depende deste dado estar certo.
> **Modelo:** Opus 5 · effort `max` (`claude-opus-5`) — Caminho crítico. Parser de 265 páginas, inferência de limiares, conflito entre fontes. Erro aqui é silencioso e contamina todo laudo. **Não use Sonnet nesta fase.**

> **Status: implementada, revisão clínica pendente.** Registro ao final.

## Objetivo

Os 170 marcos no banco, com enunciado, critérios, tipo de resposta e limiares
corretos, congelados em seeder versionado e revisados clinicamente.

## Por que é o caminho crítico

O limiar determina se a criança pontua ½ ou 1. Um erro aqui não gera exceção,
não quebra teste de integração e não aparece na interface — **contamina
silenciosamente todo laudo emitido**. A revisão clínica desta fase não é
burocracia: é o controle de qualidade do produto inteiro.

## Pré-requisitos

- F0 concluída
- `docs/vmmapp/Vb-mapp traduzido .pdf` — 265 páginas
- `docs/testes/vbmapp-nivel-{1,2,3}-registro.pdf`
- Leitura de `04-catalogo-vbmapp.md`
- **Disponibilidade da psicóloga** para a revisão

## Entregáveis

- `app/Support/Content/ManualImporter.php`
- `app/Support/Content/ThresholdInferrer.php`
- Comando `php artisan vbmapp:import-manual`
- `storage/app/vbmapp/catalogo.json` — saída intermediária, revisável
- `database/seeders/VbmappAreaSeeder.php` — 16 áreas
- `database/seeders/VbmappItemSeeder.php` — 170 marcos
- Migrations das tabelas `vbmapp_*`
- Relatório de revisão em `.claude/specs/04-catalogo-vbmapp.md`, atualizado

## Tarefas

### 1. Migrations

Crie `vbmapp_areas`, `vbmapp_items`, `vbmapp_stimuli` e `vbmapp_material_pages`
conforme `02-modelo-de-dados.md`.

Atenção a duas colunas:

- `threshold_half` **nullable** — há marco sem meio ponto
- `criteria_half` **nullable** — mesma razão

### 2. Semear as áreas

As 16 áreas com `code`, `name`, `short_name` e `position`, exatamente como na
matriz de `04-catalogo-vbmapp.md`. `short_name` é o rótulo do gráfico e precisa
bater com a planilha de referência (`VP/MTS`, não `Percepção Visual`).

### 3. Extrair o texto do manual

O PDF é digital, com texto embutido — não precisa de OCR.

```bash
python3 -m venv .venv-tools
.venv-tools/bin/pip install pymupdf
```

Script em `tools/extract_manual.py`: percorre as 265 páginas, emite texto com
marcador de página, grava em `storage/app/vbmapp/manual.txt`.

> A ferramenta de extração é Python porque `pymupdf` é o que lê este PDF de forma
> confiável. É utilitário de build, roda uma vez e não vira dependência de runtime.

### 4. Segmentar por marco

Cada marco aparece no manual como:

```
MANDO
3-M
Generaliza 6 mandos entre 2 pessoas, 2 ambientes e para 2 tipos
diferentes de reforçador (...). (O/T)
OBJETIVO
...
MATERIAIS
...
EXEMPLOS
...
1 PONTO
Dê 1 ponto à criança se ...
½ PONTO
Dê 1/2 ponto se ...
```

O `ManualImporter` reconhece: nome da área em linha isolada e maiúsculas,
seguida do código `N-M`, seguido do enunciado até `OBJETIVO`.

**Cobertura medida na sondagem:** 152 dos 170 códigos e ~155 blocos de critério
são reconhecidos automaticamente — cerca de **90%**. Os ~18 restantes têm quebra
de linha irregular e **exigem transcrição manual**. Isso é esperado; orce por isso.

Emita um relatório de lacunas ao fim da importação:

```
php artisan vbmapp:import-manual --report
→ 152/170 marcos reconhecidos
→ Faltando: TATO 5-M, ECÓICO 3-M, ...
```

### 5. Inferir tipo e limiares

`ThresholdInferrer` lê `criteria_full` e `criteria_half` e extrai o número:

```
"Dê 1 ponto à criança se ela emitir mandos para 4 reforçadores diferentes"  → 4
"Dê 1/2 ponto se criança que emitir 3 mandos deste tipo."                   → 3
"Não há ½ ponto para esta habilidade."                                      → null
```

Inferência de `response_type`:

| Sinal | Tipo |
| --- | --- |
| Registro traz dois critérios por extenso e o manual mede tempo ou frequência | `binary_criteria` |
| Registro marca "Tem no material de aplicação" | `counter_stimuli` |
| Manual traz lista enumerada de estímulos | `counter_list` |
| Registro traz grade de item × exemplares | `matrix` |
| Restante | `counter_free` |

**A inferência é sugestão, não verdade.** A saída vai para
`storage/app/vbmapp/catalogo.json` com um campo `confidence` por marco, e é esse
arquivo que a psicóloga revisa — não o banco.

Marque `scoring_mode = assisted` quando `threshold_half === threshold_full`.
É a assinatura do critério qualitativo (caso Mando 4).

### 6. Revisão clínica

**Esta tarefa não é de engenharia.** Gere uma planilha a partir do
`catalogo.json` com uma linha por marco: área, número, enunciado, critério de 1
ponto, critério de ½, limiares inferidos e um campo de confirmação.

A psicóloga percorre os 170 e confirma ou corrige. Priorize:

1. Os ~18 marcos não reconhecidos — precisam de transcrição
2. Os marcados `confirmar` em `04-catalogo-vbmapp.md`
3. Todos os `counter_*` — é onde o registro engana
4. O conflito de **Ouvinte 2** (ver abaixo)

As correções voltam para o `catalogo.json`, que então é congelado no seeder.

### 7. Resolver o conflito de Ouvinte 2

**Ouvinte 2-M** — "Responde ao ouvir seu próprio nome por 5 vezes":

- Manual, p. 80: *"½ PONTO — Não há ½ ponto para esta habilidade."*
- Registro nível 1, p. 4: oferece "Responde 2 vezes = ½"

Decisão default: **o manual prevalece** — `threshold_half = null`.
Confirme com a psicóloga e registre a decisão em `04-catalogo-vbmapp.md`.

### 8. Congelar o seeder

`VbmappItemSeeder` lê o `catalogo.json` revisado e insere os 170 marcos. O JSON
revisado vai para `database/data/vbmapp-catalogo.json`, **versionado no git** —
é o dado mais valioso do projeto.

### 9. Cache

```php
Cache::rememberForever("vbmapp.level.{$level}", fn () => /* áreas + itens */);
```

Comando `php artisan vbmapp:cache-clear`, chamado ao fim do seeder.

## Critérios de aceite

- [ ] `php artisan migrate:fresh --seed` popula sem erro
- [ ] `vbmapp_areas` tem exatamente **16** linhas
- [ ] `vbmapp_items` tem exatamente **170** linhas
- [ ] Contagem por nível: **45**, **60**, **65** — teste automatizado
- [ ] Toda área tem 5 marcos em cada nível onde existe — teste automatizado
- [ ] Todo marco tem `statement`, `criteria_full`, `response_type`, `threshold_full`
- [ ] Nenhum marco tem `criteria_half` nulo com `threshold_half` preenchido, nem o inverso
- [ ] Todo marco com `threshold_half` não nulo satisfaz `threshold_half < threshold_full`
- [ ] Os 45 marcos do nível 1 batem com a tabela de `04-catalogo-vbmapp.md`
- [ ] Nenhum marco permanece marcado `confirmar`
- [ ] `catalogo.json` versionado, com autoria e data da revisão clínica
- [ ] O cache é invalidado pelo seeder

### Testes obrigatórios

```php
it('semeia exatamente 170 marcos', ...);
it('distribui 45, 60 e 65 marcos por nível', ...);
it('dá 5 marcos a cada par área/nível', ...);
it('mantém threshold_half menor que threshold_full quando existe', ...);
it('permite marco sem meio ponto', ...);          // Ouvinte 2
it('marca como assisted quando os limiares coincidem', ...); // Mando 4
```

## Riscos e decisões

**A revisão clínica é o gargalo, não o parser.** Escrever o importador leva
poucos dias; revisar 170 marcos com a psicóloga leva o resto da fase. Agende
antes de começar a codar.

**Não pule direto para o banco.** O `catalogo.json` intermediário existe para
que a revisão aconteça sobre algo legível e versionável. Semear direto do parser
torna a correção de um limiar uma migration.

**O manual tem ruído de extração.** Rodapés como
`Comercialização proibida pelo autor M.L.Sundberg` e marcas `!` aparecem no meio
do texto. Limpe no importador, com lista explícita de padrões — nunca com regex
agressivo que possa comer conteúdo.


---

## Registro de execução

Implementada. O catálogo está no banco e os testes passam, mas a fase **não
está concluída**: falta a revisão clínica, que não é tarefa de engenharia.

### Cobertura: 170 de 170, não 152

A previsão era 152 marcos reconhecidos e ~18 transcritos à mão. O parser chegou
a **170 de 170**, com uma única correção manual. O que fez a diferença foi
descobrir que o manual usa **dois formatos de bloco**:

**Formato A** — 155 marcos. Marcadores em maiúscula, em linha própria:
`OBJETIVO`, `MATERIAIS`, `1 PONTO`, `½ PONTO`.

**Formato B** — Ecóico e Vocal, 15 marcos. Código sem hífen e marcadores inline
em minúscula: `Objetivo:`, `1 ponto:`. Foi por isso que essas duas áreas
apareciam com zero marcos na sondagem inicial.

### Irregularidades do PDF que custaram tempo

| Achado | Ocorrências | Tratamento |
| --- | --- | --- |
| `VP-MTS` contém hífen | 14 | Testar o rótulo inteiro antes de fatiar; hífen deixou de ser separador |
| Variante de digitação `PV-MTS` | 4 | Mapeada para `vpmts` |
| Separador do código varia: `7- M`, `9 –M`, `5M` | 3 | Regex tolerante a espaço, hífen e travessão |
| Marcador com dois-pontos: `1 PONTO:` | 1 | Aceita `:` opcional |
| Marcador inline: `MATERIAIS Materiais básicos...` | vários | Guarda o resto da linha |
| Cabeçalho de seção vazando no último campo | 8 | `podarCauda()` com padrões explícitos |
| `Imita10` sem espaço | 1 | Meio ponto usado como restrição do limiar cheio |

### O único marco transcrito à mão

**Mando 2-M.** O PDF é de duas colunas e, nesse ponto, a ordem de leitura separa
os critérios do enunciado — eles aparecem depois do bloco do Mando 1. O parser,
que delimita o bloco pelo próximo código de marco, não os alcança.

A transcrição está em `database/data/vbmapp-correcoes.json`, com a origem citada
(manual, p. 76) para a revisão poder conferir.

### Validação da atribuição critério ↔ marco

O layout de duas colunas trouxe um risco maior que as lacunas: **critério
atribuído ao marco errado**, que seria silencioso. Validei comparando o número
do enunciado com o do critério de 1 ponto: **157 coerentes, 0 suspeitos**. O
desalinhamento afetou apenas o Mando 2, que perdeu o critério por inteiro em vez
de receber o de outro marco.

### Regras de inferência que emergiram dos dados

1. **O limiar cheio nunca é menor que o de meio ponto.** Usar isso como
   restrição descarta números acessórios que a coincidência com o enunciado não
   filtra — `imitacao:6`, em que "arranjo de 3 itens" concorria com o limiar 10.

2. **Apagar toda menção a "N ponto" é mais robusto que remover um prefixo.**
   A redação varia demais ("Dê 1 ponto à criança se", "Dê à criança ½ ponto
   se", "Dê 1/2 ponto se criança que"). Limiar nunca vem seguido da palavra
   "ponto", então a remoção é segura.

3. **Mencionar minutos não faz o marco deixar de ser contagem.** A primeira
   heurística classificava como `binary_criteria` tudo que citasse tempo, e isso
   destruía marcos como "imita 8 movimentos em 30 min". A regra passou a ser
   por evidência: se sobra número depois de remover tempo e percentual, é
   contagem; senão, é julgamento.

4. **Limiar cheio igual a 1 não é contagem.** Uma caixa só é sim ou não, e o
   caso costuma ser resíduo de critério temporal — "explora objetos por 1
   minuto", em que o número sobrevivente é a duração.

5. **`binary_criteria` usa limiar ordinal 2/1** — 2 significa atingiu o critério
   de 1 ponto, 1 o de ½. Assim o `ScoreCalculator` da F3 fica uniforme para
   todos os tipos, sem ramo especial.

### Divergências ante a análise inicial da spec

**`ouvinte:2` ficou `counter_free` com limiar 5**, não `binary_criteria`. O que
importa — `threshold_half = null`, o conflito documentado — está correto. Contar
5 ocorrências em caixas é defensável e talvez melhor que dois botões. **Ponto
para a revisão.**

**`mando:4` ficou `binary_criteria`**, não `counter_free` + `assisted`. Acabou
melhor: os dois critérios escritos lado a lado resolvem a distinção qualitativa
("5 mandos diferentes" contra "5 sempre com a mesma palavra") sem precisar de
confirmação extra. O `ScoringMode::Assisted` continua em uso por 9 outros marcos.

**O subteste ecóico é chamado de APCE no manual** e de EESA nos PDFs de registro.
Aparentemente o mesmo instrumento com traduções diferentes. **Confirmar.**

### O que ficou pendente

A **revisão clínica** dos 170 marcos. Sem ela, a fase não fecha, e nenhum laudo
pode ser emitido.

```bash
php artisan vbmapp:review-sheet   # storage/app/vbmapp/revisao-clinica.csv
```

A planilha sai ordenada por prioridade: **27 marcos de confiança baixa vêm
primeiro**, depois 107 de confiança média, por fim os 36 de confiança alta como
amostragem.

Depois da revisão, as correções voltam para `vbmapp-correcoes.json` e o catálogo
é recongelado com autoria:

```bash
php artisan vbmapp:import-manual
php artisan vbmapp:freeze --revisor="Nome da psicóloga"
php artisan migrate:fresh --seed
```

Enquanto `revisao_clinica.status` for `pendente`, o seeder emite aviso a cada
execução. **Isso é proposital — não silencie.**

### Verificação

```
170 marcos, 16 áreas, 45/60/65 por nível
0 pares área/nível com contagem diferente de 5
0 marcos com threshold_half > threshold_full
0 marcos com criteria_half e threshold_half divergentes
2 marcos sem meio ponto (ouvinte:2, escrita:15)
9 marcos com scoring_mode assisted
15 limiares conferidos à mão no manual: 0 divergências
pest       → 51 passando (192 asserções)
pint --test → limpo
```
