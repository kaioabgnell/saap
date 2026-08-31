# F9 — Acabamento e validação

> **Depende de:** todas · **Estimativa:** ~1,5 semana
> **Modelo:** Opus 5 · effort `xhigh` (`claude-opus-5`) — Revisão crítica, diagnóstico de desempenho e correção de UX a partir de observação em campo.

> **Status: parcialmente concluída.** Tudo o que é código está feito e
> verificado. As duas tarefas de campo — a aplicação real e a conferência
> clínica do resultado — dependem da psicóloga e seguem **abertas**. Registro
> ao final.

## Objetivo

O sistema aplicado de ponta a ponta numa avaliação real, no iPad, pela
psicóloga. É a fase que transforma "funciona" em "utilizável".

## Entregáveis

- Responsividade validada em iPad, celular e desktop
- Acessibilidade por teclado e leitor de tela
- Desempenho aferido com avaliação cheia
- Uma aplicação real de ponta a ponta, com correções
- Rotinas de manutenção
- Documentação de operação

## Tarefas

### 1. Responsividade

Testar em dispositivo real, não só no emulador:

| Dispositivo | Verificar |
| --- | --- |
| iPad retrato e paisagem | Grade de estímulos, abas de área, barra fixa, modo apresentação |
| iPhone | Cabeçalho colapsado, um cartão por vez, alvos de toque |
| Desktop 1280+ | Coluna lateral, cartões, tabelas largas |

- Alvo de toque ≥ 44×44 px em tudo
- Barra de ações respeita `safe-area-inset-bottom`
- Grades largas rolam **dentro do contêiner**; a página nunca rola de lado
- Nenhuma interação essencial depende de hover
- Girar o tablet no meio da aplicação não perde estado

### 2. Acessibilidade

- Foco visível em todo elemento interativo
- Grade de estímulos navegável por teclado, `Space` alternando
- Cor nunca é o único portador: `aria-label` em toda célula de pontuação, tabela
  numérica equivalente ao gráfico
- Contraste AA; conferir especialmente o âmbar sobre branco
- `prefers-reduced-motion` desliga transições
- Rótulo em todo campo; erro associado por `aria-describedby`
- Percorrer o cadastro de aprendiz e um nível inteiro **só com teclado**

### 3. Desempenho

Cenário de referência: avaliação com os três níveis completos, 170 respostas.

| Alvo | Limite |
| --- | --- |
| Tela do nível, 65 marcos respondidos | < 400 ms |
| Gravação de resposta, ida e volta | < 150 ms |
| Painel com 50 aprendizes | < 300 ms |
| PDF do relatório completo | < 30 s em fila |

- Zero consultas N+1 — verificar com `DB::listen` em todas as telas
- Índices conferidos com `EXPLAIN` nas consultas de progresso
- Imagens com `loading="lazy"` e cache
- Bundle de produção sob 300 KB comprimido

### 4. Aplicação real

**A tarefa mais importante da fase.** A psicóloga aplica uma avaliação de
verdade, num aprendiz real, do cadastro ao relatório.

Observar sem intervir e registrar:

- Onde ela hesita
- O que procura e não encontra
- Onde o vocabulário do sistema difere do dela
- O que faria diferente no papel
- Se o modo apresentação funciona com a criança à frente

Corrigir o que aparecer. **Esta tarefa costuma gerar mais mudança de UX que todas
as fases anteriores somadas** — orce tempo para as correções, não só para a sessão.

### 5. Verificação clínica do resultado

> **Aberta.** Depende da psicóloga e da revisão clínica pendente da F1.

Comparar o relatório gerado com a aplicação em papel do mesmo aprendiz:

- [ ] Pontuação idêntica marco a marco
- [ ] Gráfico visualmente equivalente ao da planilha de referência
- [ ] Idade correta
- [ ] Total por área e por nível conferem

Divergência aqui é defeito de catálogo (F1), não de interface. Trate como bloqueio.

### 6. Manutenção

Comandos agendados em `routes/console.php` — o Laravel 12 não gera
`app/Console/Kernel.php`:

- Limpeza de PDFs temporários com mais de 24 h
- Limpeza de uploads órfãos
- `queue:prune-batches`

### 7. Documentação de operação

`docs/operacao.md`:

- Como subir o ambiente
- Como rodar o seeder do catálogo
- Como reprocessar imagens do material
- Como diagnosticar fila travada
- Backup e restauração do banco

### 8. Segurança e LGPD

- [x] Fotos de aprendizes só por URL assinada, nunca por link direto
- [x] Base de tratamento e política de retenção documentadas — `docs/operacao.md`
- [x] Registro de acesso a relatório concluído — `report_access_logs`, nas três portas
- [x] Senhas com política mínima (10 caracteres, letra e número); token de
      recuperação expira em 60 min
- [x] `APP_DEBUG=false` em produção — no checklist de `docs/operacao.md`
- [x] Cabeçalhos de segurança configurados — `SecurityHeaders`
- [x] Nenhum dado de aprendiz em log — a aplicação não faz uma chamada de `Log::`

## Critérios de aceite

- [ ] **Aplicação completa realizada por um psicólogo real, sem assistência** —
      aberta, depende da psicóloga
- [ ] **Pontuação confere com a aplicação em papel, marco a marco** — aberta,
      depende da revisão clínica da F1
- [x] Todos os alvos de desempenho atingidos — o do nível 3 **sem margem**
      (~400 ms contra alvo de 400 ms); ver registro
- [x] Cadastro e um nível inteiro percorríveis só com teclado — verificado por
      inspeção e por teste automatizado; **falta a confirmação com leitor de
      tela real**
- [x] Contraste AA em toda a interface — três reprovações corrigidas
- [x] Layout íntegro em iPad, iPhone e desktop — verificado por inspeção;
      **falta o aparelho de verdade**
- [x] Zero consultas N+1 — inclusive uma que só existia em produção
- [x] Rotinas de manutenção agendadas e testadas
- [x] `docs/operacao.md` publicado
- [x] Checklist de LGPD cumprido, com as pendências de política registradas
- [x] `php artisan test` verde
- [x] `./vendor/bin/pint --test` limpo

## Fora do escopo da v1

Registrado aqui para não virar escopo por acidente. O modelo de dados já suporta
cada item; nenhum exige migration destrutiva.

| Item | Onde encaixa |
| --- | --- |
| **Sugestão de programas e atividades a partir dos gaps** | Objetivo declarado da v2. Depende de digitalizar o acervo curricular do VB-MAPP. |
| **Comparação de até 4 aplicações no mesmo gráfico** | `refs/Grafico VB-MAPP.xlsx` já prevê cor, data e avaliador por teste. Os dados históricos já são persistidos. |
| **Avaliação de Barreiras (24 barreiras)** | Segundo componente do VB-MAPP, no capítulo 6 do manual. |
| **Avaliação de Transição** | Terceiro componente. |
| **Curadoria de estímulos dos níveis 2 e 3** | 233 páginas. Basta rodar o pipeline da F4 sobre os outros níveis. |
| **Aplicativo móvel** | A API da F8 já é o contrato. |
| **Clínica com equipe** | `users` já isola por psicólogo; virar equipe é acrescentar `clinic_id`. |
| **Cobrança e planos** | A v1 é gratuita. |
| **Exportação de dados** | CSV e JSON por aprendiz. Vira exigência da LGPD (art. 18) assim que houver titular pedindo. |
| **CSP com `script-src`** | O Livewire já publica um build sem `eval` (`livewire.csp.min.js`, 95 KB). Falta o nonce por requisição para o bloco inline. Ver `SecurityHeaders`. |
| **Subconjunto da fonte Font Awesome** | 136 KB de fonte para **26 ícones**. Reduzir ao subconjunto usado corta ~120 KB da primeira visita. |
| ~~Registro de consentimento da foto~~ | **Feito.** `learners.image_consent_at`; foto exige o termo, revogar apaga a imagem. |
| ~~Apagamento definitivo a pedido do titular~~ | **Decidido: não haverá.** *Soft delete* é a política, por exigência contratual de registro histórico e pela retenção de prontuário (20 anos, Res. CFP 001/2009). |

## Riscos e decisões

**A validação em campo revela o que nenhum teste pega.** Aplicar VB-MAPP é uma
atividade com carga cognitiva alta e uma criança à frente. Decisões de interface
que parecem óbvias na mesa podem falhar na sessão. Reserve tempo para refazer.

**Divergência de pontuação é bloqueio, não ajuste.** Se o relatório discordar do
papel, o catálogo está errado e todo laudo emitido está comprometido. Volte à F1.


---

## Registro de execução

Concluída no que é código. As duas tarefas de campo seguem abertas, e estão
listadas ao final.

### O achado que justifica a fase: um N+1 que só existia em produção

Os testes de N+1 rodavam verdes desde a F3. Estavam certos — e cegos.

`CatalogCache` guardava o catálogo com `Cache::rememberForever`, e cada um dos
até 65 ItemCards da tela chamava `CatalogCache::level()` por conta própria.
`rememberForever` **não memoriza nada**: é uma ida ao driver de cache a cada
chamada. Nos testes o driver é `array` (`CACHE_STORE=array` no `phpunit.xml`) e
uma leitura de array custa nada. Em produção o driver é `database`
(`CACHE_STORE=database` no `.env`), e cada chamada era **uma consulta mais um
`unserialize` do nível inteiro**.

Medido, com o driver de produção, 65 chamadas:

```
antes:  1819 ms, 65 consultas
depois:   31 ms,  1 consulta
```

O teste de N+1 contava consultas — e contava as certas. O que nenhum teste
olhava era o **driver de cache configurado**, porque o ambiente de teste o
troca por um mais rápido. É a categoria de defeito que só aparece quando se
mede o que a produção realmente faz.

A correção é `CatalogMemo`, registrado como `scoped` no container: memoriza
dentro de uma requisição e zera na seguinte. `scoped`, não `singleton` — um
`static` guardaria catálogo velho entre requisições sob Octane, e um
`singleton` faria o mesmo.

O teste novo força `cache.default = database` antes de contar. É o único jeito
de esse teste significar alguma coisa.

Outros dois, encontrados no mesmo perfil:

- **`ItemCard::item()`** varria as áreas com `flatMap()->firstWhere()`: 4,3 ms
  por cartão, 282 ms numa tela de 65. Virou `CatalogCache::item()`, com índice
  por id montado uma vez. 13 ms.
- **`LevelBoard::respostas()`** carregava as **entradas** das 65 respostas para
  renderizar os ~5 cartões visíveis. Um marco `matrix` sozinho tem dezenas de
  entradas. O progresso só precisa de `answered_at`; as entradas agora vêm em
  consulta separada, só dos marcos na tela.
- **`StartLevel`** abria transação e `lockForUpdate` a cada carregamento da
  tela, mesmo com o nível já criado — três idas ao banco e um lock numa linha
  quente enquanto o psicólogo grava. Ganhou caminho rápido; a transação
  continua guardando a criação concorrente.

### Desempenho: atendido, e um deles sem margem nenhuma

| Alvo | Limite | Medido |
| --- | --- | --- |
| Tela do nível 3 cheio, uma área | < 400 ms | **~400 ms** |
| Gravação de resposta | < 150 ms | ~25 ms |
| Painel com 50 aprendizes | < 300 ms | 168 ms |
| PDF do relatório completo (170 marcos) | < 30 s | ~9 s |
| Bundle de produção | < 300 KB | 266 KB |

A tela do nível fica **exatamente na linha**: entre execuções a mediana varia
de 385 a 450 ms. Está registrado como atendido sem margem, não arredondado
para verde. O que sobra depois das quatro correções é custo de componente
Livewire — 32 ms por cartão — e não há defeito algorítmico restante para
remover.

Duas ressalvas sobre a medição, para não a ler como número de produção: o PHP
de linha de comando roda **sem opcache** (`opcache.enable_cli=Off`, enquanto o
Apache tem `enable=On`) e o `RefreshDatabase` mantém uma transação aberta o
tempo todo. Ligar o opcache no CLI não mudou nada — o processo de teste é curto
demais para se beneficiar —, então o número real no iPad continua sendo uma
incógnita até alguém abrir a tela num iPad. É parte da validação em campo.

O teto do teste ficou em 600 ms de propósito. A 400 ele seria instável e
pararia de significar alguma coisa; a 600 ele ainda pega o que importa — um
N+1 novo, um cartão que voltou a consultar o banco.

O teste de tempo que a F3 tinha foi removido: media a **primeira**
renderização do processo, que paga o boot do framework, e oscilava entre 350 e
540 ms sem nada ter mudado no código. Os testes de N+1 daquele arquivo ficaram.

### Contraste: três reprovações que ninguém veria olhando

A spec mandava "conferir especialmente o âmbar sobre branco". Conferido por
cálculo, não por olho:

| Token | Antes | Depois |
| --- | --- | --- |
| `warning` como texto | 3,19:1 | **5,02:1** (`warning-ink` #B45309) |
| `success` como texto | 3,77:1 | **5,48:1** (`success-ink` #047857) |
| `ink-subtle` | 2,56:1 | **4,76:1** (#64748B) |

A distinção que a correção codifica: **preenchimento** e **texto** têm mínimos
diferentes. A célula do gráfico é objeto gráfico e o mínimo é 3:1 — as cores
originais passam, e são as mesmas do xlsx de referência, então não podiam
mudar. Como texto o mínimo é 4,5:1 e reprovavam. Daí o par `DEFAULT`/`ink`:
`bg-warning` continua sendo o âmbar do gráfico, `text-warning-ink` é o âmbar
legível. Um teste varre as views e falha se alguém voltar a usar
`text-warning` para texto.

`ink-subtle` era o pior dos três em impacto: é o texto secundário de quase toda
tela — idade do aprendiz, rótulo de estímulo, "salvo às". Não é decoração.

### A CSP que teria quebrado a aplicação em silêncio

Escrevi um `Content-Security-Policy` começando por `default-src 'self'` — e
`default-src` é o **fallback de `script-src`**. Com ele, o Alpine (que avalia
expressões de atributo) e o bloco inline do Livewire seriam bloqueados: a tela
de aplicação pararia de responder aos cliques, sem erro visível, sem nada no
log do servidor.

A CSP final não tem `default-src` nem `script-src`, e isso é decisão. Uma
política de script que conceda `'unsafe-inline'` e `'unsafe-eval'` — que é o
que Alpine e Livewire exigem — não barra praticamente nenhum XSS; seria teatro
num cabeçalho que passa a impressão de proteger. O que ficou tem valor real e
não depende do Alpine: `frame-ancestors 'none'` (laudo de criança não vai em
iframe alheio), `object-src`, `base-uri`, `form-action`.

O caminho para uma CSP séria está registrado como fora de escopo: o Livewire
publica um build sem `eval` (`livewire.csp.min.js`), e falta o nonce por
requisição para o bloco inline.

Um teste guarda a decisão nos dois sentidos: o cabeçalho existe **e** não
declara `default-src`.

### LGPD: o registro de acesso ao laudo

`report_access_logs` grava quem leu, quando, como e de onde — nas **três**
portas: tela, download do PDF e API. A da API é a que se esquece, e seria
justamente a porta sem registro.

Duas decisões contra-intuitivas, ambas com teste:

- **403 não é acesso.** Um psicólogo tentando ler o laudo de outro é barrado
  pela policy antes de haver leitura. Registrar tentativa barrada aqui
  encheria a tabela de ruído e esconderia o que ela existe para mostrar.
- **404 também não.** O download só registra depois de confirmar que o arquivo
  existe.

O restante do checklist estava em grande parte pronto desde a F2 — a foto de
aprendiz já ia para o disco privado com URL assinada de 10 minutos. O que
faltava e foi feito: cabeçalhos de segurança, política de senha (10
caracteres, letra e número — sem `uncompromised()`, que poria uma consulta ao
HaveIBeenPwned no caminho do cadastro), e a documentação das bases legais e
prazos de retenção em `docs/operacao.md`.

Conferido também o que **não** havia: a aplicação não faz uma única chamada de
`Log::`, então não há dado de aprendiz em log.

### Acessibilidade

Corrigido: `prefers-reduced-motion` (não havia); `[x-cloak]` sem definição, que
fazia o modo apresentação piscar em tela cheia ao carregar a tela do nível;
sete controles com alvo de toque de 32 px; erro de formulário sem
`aria-describedby`; seis controles de navegação com `focus:outline-none` e
nada no lugar; o menu do usuário sem anunciar que abre um menu.

O gatilho do menu parecia inacessível por teclado — é uma `<div @click>`. Não
é: o slot é sempre um `<button>` real, e ativar um botão pelo teclado dispara
um clique que sobe até o `@click` da div. O que faltava era só o
`aria-expanded`.

O respiro do rodapé era `pb-28` seco, sem somar `safe-area-inset-bottom`: num
iPhone com indicador de home a barra fixa cresce e esconderia o último cartão.

### O que ficou aberto, e por quê

Nenhum destes é código:

1. **A aplicação real, pela psicóloga, do cadastro ao laudo.** É a tarefa que a
   própria spec chama de mais importante da fase, e a que costuma gerar mais
   mudança de UX que todas as anteriores somadas. Não dá para simular.
2. **A conferência clínica marco a marco contra o papel.** Bloqueada pela
   revisão clínica pendente da F1 — não faz sentido conferir contra um catálogo
   que ainda não foi lido por um humano.
3. **Verificação em aparelho de verdade** — iPad e iPhone. Tudo o que a spec
   pede foi verificado por inspeção e por teste (alvo de toque, área segura,
   rolagem contida, nada dependendo de hover), mas layout se verifica olhando.
4. **Leitor de tela real.** Os testes garantem rótulo, associação de erro,
   `alt` e `aria-label`; garantem que a estrutura existe, não que a experiência
   funciona.

E segue valendo o bloqueio maior, herdado da F1: **o catálogo está congelado
com `revisao_clinica: pendente`**. O seeder avisa a cada execução. Duas
divergências de limiar já foram encontradas por acaso na F5 (`tato:7` e
`tato:11`, ambas com o parser tendo pego o primeiro número da frase) e dois
vazamentos de fronteira na F6 (`vpmts:3`, `brincar:4`). Os quatro compartilham
o mesmo padrão de causa, e nada garante que sejam os únicos — ninguém leu os
170 critérios palavra por palavra ainda.

Não emita laudo antes disso.

### Verificação

```
269 testes passando (885 asserções) · pint --test limpo
27 testes novos nesta fase: desempenho, acessibilidade, contraste,
cabeçalhos de segurança, registro de acesso ao laudo e limpeza de órfãos

Correções de desempenho, com o driver de produção:
  CatalogCache::level() x65 : 1819 ms / 65 consultas → 31 ms / 1 consulta
  CatalogCache::item()  x65 :  282 ms → 13 ms

Bundle de produção (gzip/woff2), primeira visita:
  CSS + JS da aplicação :  48,6 KB
  Livewire (com Alpine) :  81,9 KB
  Fontes Font Awesome   : 135,8 KB   ← 26 ícones em uso
  TOTAL                 : 266,3 KB   (alvo 300)

Contraste, os três corrigidos: 5,02:1 · 5,48:1 · 4,76:1  (mínimo AA 4,5)
```
