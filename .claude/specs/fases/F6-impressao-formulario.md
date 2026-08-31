# F6 — Impressão do formulário

> **Depende de:** F3 · **Estimativa:** ~1 semana · **Habilita:** F7
> Pode rodar em paralelo com F5.
> **Modelo:** Sonnet 5 (`claude-sonnet-5`) — Blade + dompdf, com as restrições de CSS já documentadas na spec.

> **Status: concluída.** Registro das decisões tomadas na execução, ao final.

## Objetivo

Em qualquer ponto da aplicação, o psicólogo gera um PDF do nível corrente com o
que já foi preenchido — para levar à sessão, anexar ao prontuário ou continuar
em papel.

Distinto do **relatório final** (F7), que só existe depois da conclusão.

## Entregáveis

- `app/Jobs/GenerateFormPdf.php`
- Views em `resources/views/pdf/formulario/`
- Folha de estilo de impressão, independente do Tailwind
- Ação "Imprimir formulário" na barra do nível
- Download com nome previsível

## Tarefas

### 1. Instalar o dompdf

```bash
composer require barryvdh/laravel-dompdf
```

### 2. Restrições de CSS

O dompdf implementa **CSS 2.1**. Não suporta flexbox, grid, `gap`, variáveis CSS
nem gradientes confiáveis.

Consequências:

- Layout em `<table>` e `float`
- Cores literais em hexadecimal, nunca `var(--primary)`
- Sem Font Awesome por webfont — use glifos de texto ou PNG embutido
- Imagens por caminho absoluto de sistema de arquivos, não por URL

`resources/views/pdf/` tem folha própria. **Não tente reaproveitar o CSS da
aplicação.**

### 3. Estrutura do formulário

**Cabeçalho, repetido em toda página:**

- Wordmark SAAP e nome da clínica
- Nome do aprendiz e **idade na data da aplicação**
- Data da aplicação e nome do aplicador
- Nível e progresso: `Nível 1 — 32 de 45 respondidos`
- Marca `RASCUNHO — AVALIAÇÃO EM ANDAMENTO`

> A marca de rascunho é obrigatória. Um formulário parcial não pode ser
> confundido com laudo.

**Corpo, agrupado por área, na ordem do instrumento:**

Para cada marco: número e código, enunciado, pontuação registrada (`1`, `½`, `0`
ou em branco), exemplares registrados, e as observações do marco.

**Marco não respondido sai com campos vazios para preenchimento à mão** — é o
caso de uso principal: levar o formulário à sessão.

**Rodapé:** paginação, data de geração, e o aviso de licença do instrumento.

### 4. Geração assíncrona

Renderizar 45 marcos com imagens estoura o tempo de requisição.

1. Clique enfileira `GenerateFormPdf`
2. Interface mostra "Gerando formulário…" com polling do Livewire
3. Pronto: download automático e toast

PDFs de formulário são temporários — grave em
`storage/app/temp/formularios/` e limpe os com mais de 24 h por comando agendado.

### 5. Nome do arquivo

```
formulario-{aprendiz-slug}-nivel-{n}-{AAAA-MM-DD}.pdf
```

### 6. Opções

Caixas de seleção antes de gerar:

- **Somente não respondidas** — imprime só as pendências
- **Incluir critérios do manual** — anexa `criteria_full` e `criteria_half`
- **Incluir exemplares registrados** — ligado por padrão

## Critérios de aceite

- [x] Ação disponível durante a aplicação, em qualquer nível iniciado
- [x] PDF traz cabeçalho completo com identificação e progresso
- [x] Idade calculada na data da aplicação, não na de geração
- [x] Marca `RASCUNHO` visível em todas as páginas (marca d'água + selo no cabeçalho)
- [x] Marcos agrupados por área, na ordem do instrumento
- [x] Respondidos mostram pontuação (cor semântica) e exemplares
- [x] Não respondidos saem com **checklist real** para marcar à mão — não só
      linha em branco, ver "O que ficou melhor que o previsto"
- [x] As três opções funcionam
- [x] Geração em fila, sem travar a interface
- [x] Nome do arquivo segue o padrão
- [x] Formulário de nível cheio (65 marcos) gera em ~5 s
- [x] Legível impresso em A4, retrato
- [x] Temporários com mais de 24 h são removidos

### Testes obrigatórios

```php
it('gera PDF de formulário parcial', ...);
it('marca o PDF como rascunho', ...);
it('inclui apenas não respondidas quando filtrado', ...);
it('usa a idade na data da aplicação', ...);
it('não gera formulário de nível não iniciado', ...);
```

## Riscos e decisões

**dompdf é limitado, e tudo bem.** A alternativa de fidelidade total é o
Browsershot, que exige Chromium no servidor. Para um formulário tabular, o
dompdf entrega. Não introduza Chromium nesta fase.

**Fontes com acento.** A fonte padrão do dompdf falha em alguns glifos. Registre
DejaVu Sans e teste com `ç`, `ã`, `õ`, `é` e o caractere `½`.


---

## Registro de execução

Concluída. 205 testes na suíte, 21 novos nesta fase (11 de `BuildFormPayload`,
8 do job/download, 1 de limpeza, mais o teste de desempenho descartado após
confirmar o número).

### O que ficou melhor que o previsto: checklist real, não linha em branco

A spec pedia "campos vazios para preenchimento à mão" nos marcos não
respondidos. Fui além: o formulário imprime a **mesma estrutura da tela** —
os rótulos dos estímulos (`vbmapp_stimuli.label`), a lista fixa
(`fixed_list`), a grade da matrix (linha × colunas) ou os dois critérios do
`binary_criteria` — cada um como caixa para marcar com caneta. Um formulário
em branco de Tato 1 já mostra "☐ bola ☐ gato", não duas linhas genéricas.

Isso significa que **o catálogo virou o dado de duas telas diferentes** — a
digital e a impressa — sem duplicar nada: `BuildFormPayload` lê exatamente os
mesmos `fixed_list`/`matrix_columns`/`stimuli` que o `ItemCard` usa.

### Um job precisa falhar de propósito, não só deixar a exceção subir

A fila `sync` (testes) não envolve o `handle()` em try/catch nenhum — uma
exceção lançada ali **propaga direto** para quem chamou `dispatch()`, sem
passar pelo `failed()` do job. Isso só aparece testando os dois modos: no
`database` (produção), o *worker* intercepta a exceção e chama `failed()`
sozinho; no `sync`, ninguém intercepta.

Corrigido envolvendo o corpo do `handle()` num try/catch que chama
`$this->fail($e)` explicitamente — funciona nos dois modos, porque não
depende de quem está rodando a fila. Sem isso, gerar o formulário de um nível
não iniciado quebraria o teste (e, pior, quebraria a tela de verdade se
algum dia o app usar fila síncrona por engano).

### Paginação do PDF: a API certa não é a óbvia

Cheguei a tentar HTML com `<span class="topage">` (convenção do mPDF, não do
dompdf) — não fazia nada. A forma certa do dompdf é
`Canvas::page_text($x, $y, 'Página {PAGE_NUM} de {PAGE_COUNT}', ...)`, e ela
só resolve `{PAGE_COUNT}` corretamente se for chamada **depois** de
`$pdf->render()` — chamando antes, o canvas ainda não conhece o total de
páginas e a substituição sai errada (`"de 1"` em vez de `"de 7"`). A sequência
certa: `render()` → `getCanvas()->page_text(...)` → `output()`.

As coordenadas também exigiram ajuste visual: A4 retrato é 595×842 pt, e um
primeiro palpite (`x=756`) caiu fora da página — só apareceu ao renderizar de
verdade e olhar a imagem.

### Dois achados de conteúdo do catálogo, não deste código

Ao revisar visualmente o PDF gerado com dados reais, dois marcos mostraram
texto vazado de outro bloco: `vpmts:3` tem o enunciado de `vpmts:4` colado ao
próprio critério, e `brincar:4` começa cortado no meio de uma frase. São
vazamentos de fronteira do `ManualImporter` (F1) que só ficaram visíveis
porque o formulário mostra o texto inteiro, de uma vez, na ordem de leitura —
diferente da tela, que fatiava por painel recolhível. Registrados em
`04-catalogo-vbmapp.md` para a revisão clínica pendente da F1; **não
corrigidos aqui**, porque a correção certa exige decidir o corte olhando o
manual, não uma inferência de código.

### Verificação

```
205 testes passando (585 asserções)
pint --test → limpo

Formulário do nível 1 (10 de 45 respondidos, critérios ligados): 7 páginas
Formulário do nível 3 cheio (65 marcos): ~5 s — dentro do orçamento de 10 s

Ao vivo, com dados reais:
  cabeçalho: "Kaleo Teste — 6 anos e 3 meses" · "Aplicação em 08/07/2026" ·
    aplicador · "Nível 1 — 10 de 45 respondidos"
  marca RASCUNHO em toda página, marca d'água de fundo
  pontuação com cor semântica: 1 verde, ½ âmbar, 0 cinza
  checklist de estímulos, lista fixa e critérios binários renderizando com
    checkbox real para marcar à mão
  paginação "Página N de 7" correta, canto inferior direito
```
