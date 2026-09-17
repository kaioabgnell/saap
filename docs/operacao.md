# Operação do SAAP

Como subir, manter e socorrer o sistema. Escrito para quem vai operar meses
depois, sem o contexto de quem construiu.

## Ambiente

Três PHPs convivem nesta máquina e **só um serve**:

| PHP | Onde | Serve? |
| --- | --- | --- |
| 8.1 | `php` do PATH (Homebrew) | Não — é o padrão do sistema, outros projetos em `htdocs` dependem dele |
| 8.0 | Apache do XAMPP | Não |
| **8.3** | `/usr/local/opt/php@8.3/bin/php` | **Sim** |

```bash
export PATH="/usr/local/opt/php@8.3/bin:$PATH"
```

Não rode `brew link php@8.3` — quebraria os outros projetos.

O cliente `mysql` do PATH também é o do Homebrew e falha por plugin de
autenticação. Use sempre o binário do XAMPP:

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -h 127.0.0.1 -u root saap_local
```

### Subir do zero

```bash
export PATH="/usr/local/opt/php@8.3/bin:$PATH"

composer install
npm install
cp .env.example .env && php artisan key:generate

php artisan migrate --seed        # schema + catálogo VB-MAPP
php artisan storage:link          # fotos de perfil (as de aprendiz NÃO passam por aqui)

npm run build                     # ou `npm run dev` em desenvolvimento
php artisan serve                 # http://127.0.0.1:8000
```

Em outra janela, sempre:

```bash
php artisan queue:work            # PDFs de formulário e de relatório
```

**Sem worker, nenhum PDF sai** e a tela fica em "Gerando…" para sempre.

## Catálogo VB-MAPP

```bash
php artisan migrate:fresh --seed   # recria schema e catálogo
php artisan vbmapp:cache-clear     # depois de QUALQUER mudança no catálogo
```

O catálogo fica em cache "para sempre" (`Cache::rememberForever`) e em memória
dentro de cada requisição. Alterar `vbmapp_items` no banco sem rodar
`vbmapp:cache-clear` **não muda nada na tela** — é a primeira coisa a conferir
quando uma correção de enunciado "não pegou".

### Reprocessar imagens do material

O acervo de estímulos não está no repositório (é conteúdo licenciado, ~85 MB).
Reproduza a partir dos PDFs em `docs/material-aplicacao/`:

```bash
# 1. fatiar as figuras das páginas do material (nível 1)
.venv-tools/bin/python tools/slice_material.py --level 1

# 2. importar os recortes como estímulos
php artisan vbmapp:import-material --level 1

# 3. importar as páginas inteiras (conferência e fallback da grade)
php artisan vbmapp:import-pages --levels=1
```

Depois, confira os rótulos em `/admin/estimulos` — a inferência acerta a maior
parte, não todos.

> Os níveis 2 e 3 têm 233 páginas e **não foram curados na v1** pelo pipeline
> acima. O que existe do nível 3 veio pela curadoria à mão, abaixo.

### Acervo curado à mão (nível 3)

Cinco marcos do nível 3 usam figuras escolhidas uma a uma, e não recortes do
PDF: Tato 11 (5 objetos), 12 (preposições), 13 (adjetivos e advérbios), 14 (20
sentenças) e Ouvinte 11 (34 figuras para seleção por cor ou forma). As imagens
ficam em `docs/material-aplicacao/nivel3/<area><posicao>/` e os rótulos, em
`database/data/vbmapp-estimulos-nivel-3.json` — este arquivo é a fonte da
verdade.

O campo opcional `instrucao` de cada marco vira `vbmapp_items.stimulus_prompt`:
a frase que a psicóloga lê acima da grade ("Solicitar algum desses falando cor
ou forma", no Ouvinte 11). Ela não aparece na tela de apresentação, que é a que
a criança olha.

Marco com acervo próprio **deixa de mostrar os botões "Página N do material"** —
a figura recortada já está na tela, e a página inteira do PDF ao lado seria a
mesma informação, pior.

```bash
php artisan vbmapp:import-stimuli --level=3 --dry-run   # confere sem gravar
php artisan vbmapp:import-stimuli --level=3
```

Rodar de novo reconstrói o mesmo acervo: não duplica estímulo nem deixa
arquivo órfão no disco. Figura opaca é convertida para JPEG (o acervo cai de
8,6 MB para 1,3 MB); só o que tem transparência de verdade continua PNG.

**Tato 11 é matriz, não grade.** As 5 figuras são as *linhas* — cada objeto
recebe três perguntas (cor, forma, função), que são as 15 tentativas do
manual. Por isso os rótulos do JSON têm de ser exatamente as linhas de
`fixed_list` do catálogo; o comando recusa a importação se divergirem, porque
uma figura órfã sumiria da grade sem erro nenhum.

## Manutenção automática

Agendada em `routes/console.php`. Em produção, um único cron:

```cron
* * * * * cd /caminho/do/saap && /usr/local/opt/php@8.3/bin/php artisan schedule:run >> /dev/null 2>&1
```

| Quando | Comando | Por quê |
| --- | --- | --- |
| de hora em hora | `vbmapp:prune-form-pdfs` | O rascunho de ontem não serve hoje |
| 03:20 | `saap:prune-orphan-uploads` | Foto de criança sem dono é retenção sem base legal |
| diário | `queue:prune-batches --hours=48` | `job_batches` só cresce |
| semanal | `queue:prune-failed --hours=168` | idem para `failed_jobs` |

`saap:prune-orphan-uploads` aceita `--dry-run`. **Use antes da primeira
execução real** — ele apaga arquivo.

Conferir a agenda: `php artisan schedule:list`.

## Fila travada

Sintoma: o botão de PDF fica em "Gerando formulário…" e não sai disso.

```bash
# 1. tem worker rodando?
ps aux | grep "queue:work"

# 2. o job está na fila ou já falhou?
php artisan queue:monitor default
php artisan queue:failed

# 3. ler o erro de um job que falhou
php artisan queue:failed | head

# 4. reenfileirar
php artisan queue:retry all

# 5. depois de QUALQUER deploy que mude código de job
php artisan queue:restart
```

> O `queue:restart` é o passo mais esquecido. O worker carrega o código na
> memória quando sobe: sem reiniciar, ele continua rodando a versão antiga
> indefinidamente, e o sintoma é "corrigi o bug e não mudou nada".

Em desenvolvimento a fila pode rodar como `sync` (`QUEUE_CONNECTION=sync`) —
o job executa na própria requisição, sem worker. **Não use em produção**: o
PDF do relatório completo leva ~9 s e estouraria o tempo da requisição.

## "Sem conexão" na aplicação de um nível

Sintoma: durante a aplicação, o rodapé mostra "Sem conexão — tentando salvar
de novo" e, se persistir, escala para "Não foi possível salvar. Recarregue a
página." com um botão de recarregar. É `resources/js/fila-salvamento.js` —
não tem relação com a fila de PDF acima.

**Por que acontece.** O commit de um marco falhou — quase sempre porque a
sessão do psicólogo venceu no meio da observação (marcos exigem 30-60 min
antes de qualquer salvamento; ver regra de domínio 7 do CLAUDE.md), e o
navegador recebeu 419. A tela reage sozinha: reenvia a ação exata que falhou
(não um "salvar" genérico) por até 6 tentativas (~90 s de backoff). Se a causa
for mesmo sessão vencida, reenviar com o mesmo token nunca converge — por
isso, esgotadas as tentativas, a fila desiste de insistir sozinha e pede
recarregamento em vez de ficar presa num "Sem conexão" que nunca se resolve.

**A correção estrutural já é `SESSION_LIFETIME=720`** (12 h, não os 120 min
padrão do Laravel) em `.env` — evita que a sessão vença durante uma aplicação
normal. Se o sintoma voltar a aparecer com frequência, é sinal de que 12 h não
bastam para o uso real, e o valor deve subir, não a explicação ser descartada.

**Ao investigar um relato:**

```bash
# a sessão do psicólogo ainda existe e com que TTL?
/Applications/XAMPP/xamppfiles/bin/mysql -h 127.0.0.1 -u root saap_local -e "
  SELECT id, user_id, last_activity, FROM_UNIXTIME(last_activity) AS quando
  FROM sessions ORDER BY last_activity DESC LIMIT 5;"
```

Se `last_activity` está muito no passado, a sessão realmente venceu — o
recarregamento (que pede novo login) é o caminho certo, e o que estava
digitado desde o último salvamento com sucesso se perde. É o mesmo limite já
documentado no cabeçalho de `fila-salvamento.js`: item na fila e aba fechada
também perde o que não gravou. Nenhum dos dois casos é recuperável batendo no
banco — a resposta perdida precisa ser reaplicada com a criança.

## Backup e restauração

O que precisa de backup:

1. **O banco `saap_local`** — avaliações, respostas e laudos.
2. **`storage/app/private/aprendizes/`** — fotos de aprendizes (dado sensível).
3. **`storage/app/private/relatorios/`** — PDFs de laudo emitidos.

O que **não** precisa: `storage/app/private/temp/` (descartável),
`storage/app/public/vbmapp/` (reproduzível pelo pipeline de imagens),
`node_modules`, `vendor`.

```bash
# backup
/Applications/XAMPP/xamppfiles/bin/mysqldump -h 127.0.0.1 -u root \
  --single-transaction --routines saap_local > saap-$(date +%F).sql
tar czf saap-arquivos-$(date +%F).tar.gz storage/app/private/aprendizes storage/app/private/relatorios

# restauração
/Applications/XAMPP/xamppfiles/bin/mysql -h 127.0.0.1 -u root saap_local < saap-2026-08-29.sql
tar xzf saap-arquivos-2026-08-29.tar.gz
php artisan vbmapp:cache-clear
```

`--single-transaction` é obrigatório: sem ele o dump trava as tabelas e a
aplicação para durante o backup.

**Teste a restauração antes de precisar dela.** Um backup nunca restaurado é
uma hipótese, não um backup.

## Antes de pôr em produção

- [ ] `APP_ENV=production` e `APP_DEBUG=false` — com debug ligado, qualquer erro
      expõe caminho de arquivo, trecho de código e variáveis de ambiente
- [ ] `APP_KEY` gerada e guardada; perdê-la torna ilegível o que estiver cifrado
- [ ] HTTPS ativo — o cabeçalho HSTS só é enviado sobre TLS, de propósito
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] `npm run build` (nunca `npm run dev`)
- [ ] Cron da `schedule:run` ativo
- [ ] Worker da fila como serviço (supervisor/launchd), não numa janela de terminal
- [ ] Backup agendado **e uma restauração testada**

## LGPD — o que o sistema trata

| Dado | Base legal | Onde fica | Retenção |
| --- | --- | --- | --- |
| Nome, nascimento, filiação e telefone do aprendiz | Tutela da saúde (art. 11, II, "f") | `learners` | Prontuário: 20 anos (Res. CFP 001/2009) — ver "Exclusão" abaixo |
| Foto do aprendiz | idem, **com termo assinado pelos responsáveis** | `storage/app/private/aprendizes/` | Enquanto houver autorização; revogar apaga |
| Data da autorização de imagem | Comprovação do consentimento | `learners.image_consent_at` | Acompanha o aprendiz |
| Respostas e pontuação | idem | `responses`, `response_entries` | Prontuário: 20 anos (Res. CFP 001/2009) |
| Laudo emitido | idem | `report_snapshots` | idem |
| Cadastro do psicólogo | Execução de contrato | `users` | Enquanto a conta existir |
| Acesso a laudo | Obrigação legal de rastreabilidade | `report_access_logs` | Acompanha o laudo |
| Horário e comparecimento | Tutela da saúde | `appointments` | Prontuário: 20 anos (Res. CFP 001/2009) |
| Registro escrito da sessão | idem | `appointments.notes`, `appointment_addenda` | idem |
| Resumo de nível redigido por IA | Tutela da saúde | `ai_summaries` | Prontuário: 20 anos |
| Pontuação por área enviada ao Google (Gemini) | Tutela da saúde | **sai da plataforma** — ver abaixo | Retenção do provedor |

Garantias implementadas:

- **Foto de aprendiz nunca tem link direto.** Vai para o disco privado, sem
  symlink, servida só por URL assinada de 10 minutos e sob policy
  (`LearnerPhotoController`). Foto de psicólogo, que é dado do próprio titular,
  vai para o disco público.
- **Todo acesso a laudo concluído fica registrado** — tela, download e API —
  com usuário, ação, IP e agente (`report_access_logs`).
- **Nenhum dado de aprendiz vai para log.** A aplicação não faz uma única
  chamada de `Log::` com dado pessoal; as exceções da API respondem com
  mensagem genérica.
- **Registro de sessão fechado só muda por aditamento datado.** O check-out
  carimba `notes_locked_at` e a partir daí `SaveSessionNotes` recusa a escrita;
  a correção entra em `appointment_addenda`, que não tem `updated_at`. O texto
  original nunca é reescrito — mesma lógica do laudo congelado em snapshot.
- **A agenda não sai da plataforma.** O lembrete abre o WhatsApp no navegador
  com a mensagem pronta; nenhum dado é enviado a serviço externo pelo servidor.
  Por isso a coluna se chama `reminder_opened_at`, e não `sent`.
- **O resumo por IA é o ÚNICO ponto em que dado clínico sai do servidor.**
  E sai sem identificação: `App\Domain\Vbmapp\Report\SummaryBriefing` envia
  nível, pontuação total e pontuação por área, com o avaliado chamado apenas
  de `{{APRENDIZ}}`. O nome real entra depois, já dentro do SAAP. Nome do
  aprendiz, dos responsáveis, do psicólogo e da clínica **não são enviados** —
  há teste garantindo isso (`tests/Feature/Ia/ResumoPorIaTest.php`).
- **Avaliação concluída é imutável.** Depois de `locked_at`, nada muda — nem
  pela web, nem pela API.
- **Foto órfã é apagada** diariamente por `saap:prune-orphan-uploads`.

### Autorização de uso de imagem

O termo é assinado pelos responsáveis num **formulário externo à plataforma**.
O sistema não guarda o termo: guarda a **atestação** do psicólogo de que ele
existe, com data, em `learners.image_consent_at`.

- Enviar foto **exige** a caixa marcada — web e API, mesma regra, no mesmo
  `FormRequest`.
- Desmarcar a caixa **revoga** a autorização e **apaga a foto e a miniatura**.
  Guardar imagem de criança depois de revogado o consentimento é exatamente o
  que a revogação proíbe.
- A data original é preservada em reconfirmações — a pergunta que a LGPD faz é
  "desde quando havia autorização", e recarimbar a cada edição apagaria a
  resposta.

### Exclusão: por que não há apagamento definitivo

**É decisão contratual, não limitação técnica.** A clínica precisa manter
registro histórico das avaliações, e a resolução do CFP fixa o prontuário em
20 anos. Por isso:

- Excluir um aprendiz é *soft delete*: sai das listas, permanece no banco.
- A avaliação concluída é imutável e o laudo emitido nunca muda.
- `saap:prune-orphan-uploads` **não** apaga a foto de aprendiz em soft delete —
  ela ainda tem dono.

Um pedido de apagamento pelo titular (art. 18) esbarra na retenção legal do
prontuário, que prevalece. O que se apaga a pedido é a **imagem**, revogando a
autorização — e isso o sistema faz.

Pendente, e é decisão da clínica, não do código:

- Exportação de dados a pedido do titular (art. 18) — CSV/JSON por aprendiz
- Encarregado de dados designado

## Agenda e atendimentos

A agenda vive em `/agenda` e abre no mês. Dia e semana ficam a um clique, e a
escolha fica guardada na sessão do navegador (`saap.agenda-visao`) — quem
trabalha no dia não reencontra o mês a cada volta.

### O ciclo de um atendimento

```
agendar ──> check-in ──> registro escrito ──> check-out ──> prontuário
   │            (a criança chegou)                 (fecha o texto)
   ├──> faltou       (no_show — também é registro)
   └──> cancelado    (com motivo; a linha permanece)
```

Depois do check-out o texto não se altera mais: a correção entra como
**aditamento** datado, abaixo do original. Não há como reabrir um atendimento
concluído, e isso é deliberado — prontuário é documento.

### Conflito de horário

Dois atendimentos que se sobrepõem para o mesmo psicólogo geram **aviso, não
bloqueio**: a tela mostra quem já está no horário e pede confirmação. Atender
dois irmãos na mesma hora é decisão clínica legítima.

Encostar não é sobrepor: 15h–16h e 16h–17h não disparam alerta. A regra mora
em `App\Domain\Schedule\TimeSlot::overlaps()` e é revalidada no servidor,
dentro da transação da gravação.

### Lembrete por WhatsApp

O botão abre `wa.me` numa aba nova com a mensagem pronta — **quem envia é a
pessoa**. O telefone vem de `learners.contact_phone` e passa por
`App\Domain\Contact\PhoneNumber`, que normaliza para E.164.

Quando o número não normaliza, o botão fica desabilitado com o motivo à vista.
O caso mais comum é o celular antigo de 8 dígitos: falta o nono, e o sistema
recusa em vez de inventá-lo.

### Conferir a agenda no banco

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -h 127.0.0.1 -u root saap_local -e "
  SELECT a.id, l.name AS aprendiz, a.starts_at, a.ends_at, a.status,
         a.checked_in_at, a.checked_out_at, a.notes_locked_at
  FROM appointments a JOIN learners l ON l.id = a.learner_id
  WHERE a.deleted_at IS NULL AND a.starts_at >= CURDATE()
  ORDER BY a.starts_at LIMIT 20;"
```

Sobreposições já gravadas (todas confirmadas pela psicóloga — o sistema nunca
as cria sozinho):

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -h 127.0.0.1 -u root saap_local -e "
  SELECT a.id, b.id AS conflita_com, a.starts_at, b.starts_at
  FROM appointments a JOIN appointments b
    ON b.user_id = a.user_id AND b.id > a.id
   AND a.starts_at < b.ends_at AND a.ends_at > b.starts_at
  WHERE a.deleted_at IS NULL AND b.deleted_at IS NULL
    AND a.status IN ('scheduled','in_progress')
    AND b.status IN ('scheduled','in_progress');"
```

### Atendimento que ficou aberto

Um `in_progress` de dias atrás é check-out esquecido. Não há varredura
automática que o feche: fechar sozinho carimbaria um `checked_out_at` que
ninguém viveu, num documento de 20 anos. A psicóloga abre o atendimento pela
agenda e faz o check-out — a duração real sai errada, e o aditamento serve
para explicar por quê.

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -h 127.0.0.1 -u root saap_local -e "
  SELECT id, learner_id, starts_at, checked_in_at FROM appointments
  WHERE status = 'in_progress' AND starts_at < CURDATE();"
```

## Resumo de nível por IA

Ao gerar o PDF do formulário, a psicóloga pode marcar "Análise com IA": um
resumo do desempenho no nível, em linguagem de prontuário, impresso antes dos
marcos e com aviso de autoria.

**Quando aparece.** Só com `GEMINI_API_KEY` preenchida E o nível todo
respondido. Sem chave, o checkbox não existe; com nível incompleto, ele aparece
desabilitado explicando por quê. Resumir meia avaliação produziria um texto que
parece completo e não é — e esse texto vai para a família.

**Nunca derruba o PDF.** O resumo é acessório: chave errada, Google fora do ar
ou resposta vazia fazem o bloco sumir e o formulário sair igual. É o
`try/catch` em `GenerateFormPdf::resumo()`, e tem três testes só para isso.

### No laudo final

A tela do laudo (`/avaliacoes/{id}/relatorio`) e o PDF permanente **geram o
resumo sozinhos, sem perguntar** — é a linha de `ai_summaries` com `level`
NULO, que descreve a avaliação inteira em vez de um nível.

Gerado UMA vez e reaproveitado: a tela e o PDF mostram o mesmo texto, porque
um laudo cujo resumo muda a cada visita não é laudo. Para forçar outra
redação, apague a linha:

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -h 127.0.0.1 -u root saap_local -e "
  DELETE FROM ai_summaries WHERE assessment_id = <id> AND level IS NULL;"
```

**O resumo NÃO entra no `report_snapshots`.** O snapshot é o registro congelado
e o `content_hash` existe para detectar adulteração dele; escrever texto novo
lá dentro exigiria recalcular o hash — exatamente o que ele serve para impedir.
O resumo é outro documento, com outra autoria e outra data, guardado ao lado.
Há teste garantindo que o hash continua válido depois de o resumo ser gerado.

**Para o PDF sair com o resumo**, o worker precisa estar com o código atual:
depois de qualquer deploy que mude `GenerateReportPdf`, rode `queue:restart`.
Sem isso o worker segue com a versão velha em memória e o PDF sai sem o bloco.

**O que é gravado.** Cada geração vira uma linha em `ai_summaries` — nunca
substitui a anterior. Um resumo já enviado à família precisa continuar
existindo para responder "o que eu mandei". A coluna `model` registra quem
redigiu; quando o modelo mudar, os textos antigos continuam rastreáveis.

```bash
# resumos gerados, do mais recente
/Applications/XAMPP/xamppfiles/bin/mysql -h 127.0.0.1 -u root saap_local -e "
  SELECT s.id, l.name AS aprendiz, s.level, s.model, s.tokens_used, s.generated_at
  FROM ai_summaries s
  JOIN assessments a ON a.id = s.assessment_id
  JOIN learners l ON l.id = a.learner_id
  ORDER BY s.generated_at DESC LIMIT 10;"
```

**O modelo é fixado, não `-latest`.** `GEMINI_MODEL=gemini-3.5-flash-lite` no
`.env`. Um alias que se move sozinho trocaria o redator de um documento
clínico sem ninguém decidir nada. Trocar de modelo é edição de `.env`, com o
resumo seguinte já registrando o novo nome na coluna `model`.

**Precisa do worker.** O resumo roda dentro do job do PDF (`queue:work`). Sem
worker, o modal fica em "Gerando formulário…" — ver "Fila travada" acima.

## Lançamento retroativo (avaliação em papel)

Para aplicações do VB-MAPP feitas em papel, antes do sistema. O psicólogo
lança a pontuação direto no gráfico de marcos e o laudo sai pelo mesmo
caminho de sempre.

**Caminho:** página do aprendiz → `Lançar avaliação em papel` → data da
aplicação e níveis presentes no formulário → gráfico clicável →
`Concluir e gerar relatório`.

### O que distingue uma transcrição

| | Aplicação na tela | Transcrição de papel |
| --- | --- | --- |
| `assessments.entry_mode` | `guided` | `chart` |
| Como se pontua | exemplares contados pelo motor | pontuação informada na célula |
| `responses.computed_score` | o que o motor calculou | **nulo** — não houve cálculo |
| `responses.entries` | um registro por exemplar | nenhum |
| Selo do laudo | `RELATÓRIO FINAL` | `RELATÓRIO — TRANSCRIÇÃO` |

O modo nasce com a avaliação e **nunca muda**. Uma transcrição não abre na
tela do nível (403) nem aceita o `PUT` de resposta da API — se abrisse, o
primeiro toque a gravaria com zero exemplares e zeraria o marco.

### Três coisas para saber ao operar

**A data é a do papel.** É ela que o laudo imprime e é sobre ela que a idade
do aprendiz é calculada. Uma aplicação de 2024 lançada hoje sai com a idade
que o aprendiz tinha em 2024.

**Marco em branco vira 0 na conclusão.** No formulário de papel a célula não
pintada é zero. A conversão acontece só na conclusão, e o número aparece na
confirmação antes de o psicólogo aceitar.

**Várias transcrições convivem com uma aplicação em andamento.** A regra de
"uma avaliação aberta por aprendiz" vale só entre as guiadas: arquivar o
papel de 2023, 2024 e 2025 é caminho normal.

### Conferir uma transcrição no banco

```sql
SELECT a.id, a.applied_on, a.entry_mode, a.locked_at,
       COUNT(r.id) AS respostas,
       SUM(r.computed_score IS NULL) AS sem_calculo,
       SUM(r.is_overridden) AS sobrescritas
FROM assessments a
LEFT JOIN responses r ON r.assessment_id = a.id
WHERE a.entry_mode = 'chart'
GROUP BY a.id;
```

Numa transcrição sadia, `sem_calculo` é igual a `respostas` e `sobrescritas`
é zero. Uma linha com `sobrescritas > 0` significa que a avaliação passou
pelo fluxo guiado em algum momento — investigue antes de emitir o laudo.

## Quando a pontuação parecer errada

**Volte para a F1, não mexa na interface.** O laudo é gerado de
`report_snapshots.payload`, que é uma cópia congelada do catálogo no momento
da conclusão. Se o número está errado:

1. Confira o limiar do marco: `SELECT code, position, threshold_full,
   threshold_half FROM vbmapp_items JOIN vbmapp_areas ...`
2. Compare com o **manual** — nunca com o PDF de registro, onde o `½` é
   posicionado graficamente, sem correspondência com o critério real
3. Corrija o catálogo, rode `vbmapp:cache-clear`

Laudos **já emitidos não mudam** com a correção — é o que o snapshot garante.
Se um laudo emitido estiver errado, ele precisa ser reemitido como nova
avaliação, com a divergência registrada no prontuário.

> A revisão clínica dos 170 limiares está **pendente**. O seeder avisa a cada
> execução. Não emita laudo antes de concluí-la.
