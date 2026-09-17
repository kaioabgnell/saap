# F11 — Agenda e atendimentos

> **Depende de:** F2 (aprendizes) · **Estimativa:** ~2 semanas · **Habilita:** —
> **Modelo:** Opus 5 · effort `xhigh` (`claude-opus-5`) — o que se escreve aqui
> vira prontuário: registro clínico com retenção de 20 anos que, uma vez
> fechado, não pode ser reescrito. E a regra de conflito de horário é decisão
> de domínio, não de tela.

## Objetivo

O psicólogo marca os atendimentos numa agenda, faz check-in quando a criança
chega, registra por escrito o que aconteceu e faz check-out. Tudo isso vai se
acumulando no prontuário do aprendiz.

O atendimento **não é necessariamente aplicação do VB-MAPP**. Pode ser a
anamnese inicial, uma devolutiva aos pais, uma sessão de intervenção ou uma
consulta qualquer. A agenda é da prática toda, não do teste.

## Motivação

Hoje o SAAP sabe avaliar, mas não sabe **quando** o aprendiz vem. A agenda
vive fora do sistema, e o registro da sessão — que é o documento que a
resolução do CFP exige guardar por 20 anos — vive em caderno ou em arquivo
solto. Trazer as duas coisas para dentro fecha o ciclo: o mesmo lugar que
guarda o laudo passa a guardar a evolução.

## O que NÃO muda

Módulo **novo e lateral**. Não altera avaliação, pontuação, laudo nem catálogo:

- `SaveResponse`, `CompleteAssessment`, `ReportPayload`, `CatalogCache` — intactos.
- `learners` **não muda**: nem coluna nova, nem alteração. `birth_date`
  continua obrigatória — ver Decisão 3.
- A API v1 (F8) não ganha superfície nesta fase.

Se a implementação precisar mudar assinatura de qualquer classe de
`app/Application/Assessment/`, pare: o desenho está errado.

## Pré-requisitos

- F2 concluída (aprendizes com `contact_phone`, que é o telefone dos responsáveis).

---

## Decisões confirmadas (10/09/2026)

Cinco pontos foram levados à psicóloga antes de escrever o código:

1. **Prontuário é do aprendiz, cumulativo.** Não é um documento por sessão: é
   a tela que reúne cadastro, avaliações/laudos e a linha do tempo de todos os
   atendimentos. É o sentido que a Resolução CFP 001/2009 dá à palavra, e é o
   que a política de retenção do `docs/operacao.md` já assume.
2. **Repetição simples, sem série.** "Repetir por N semanas" cria N
   agendamentos independentes de uma vez. Remarcar um não afeta os outros —
   não existe o conceito de série, nem exceção de ocorrência.
3. **Data de nascimento é obrigatória também no cadastro rápido.** A ideia
   inicial era deixá-la opcional; ao ver que um aprendiz sem nascimento não
   pode ser avaliado (o laudo imprime a idade **na data da aplicação**), a
   decisão mudou. Consequência boa: `learners.birth_date` continua `NOT NULL`
   e nada no resto do sistema precisa aprender a lidar com nulo.
4. **O registro escrito é um resumo livre da sessão, não um anexo da
   avaliação.** Pode falar do teste aplicado ou não falar: anamnese inicial,
   devolutiva, intervenção, consulta. Um campo só, sem categoria e sem
   vínculo com `assessments` — ver o fluxo 6.
5. **A agenda abre no mês.** É a visão de chegada, no formato que o Google
   Calendar consagrou. Dia e semana continuam existindo, um clique adiante.

---

## Conceitos

| Termo na tela | No código | O que é |
| --- | --- | --- |
| Agendamento | `Appointment` | Um horário marcado para um aprendiz |
| Atendimento | o mesmo `Appointment`, depois do check-in | A sessão que de fato aconteceu |
| Aditamento | `AppointmentAddendum` | Correção ou acréscimo ao registro já fechado |
| Prontuário | — | Tela que agrega tudo do aprendiz. Não é tabela |

**Agendamento e atendimento são a mesma linha.** Não há tabela separada de
sessão: o atendimento é o agendamento depois que alguém apareceu. Duas tabelas
1:1 só criariam a chance de discordarem.

---

## Modelo de dados

### `appointments`

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `id` | id | |
| `user_id` | fk `users`, cascade | Dono da agenda — a v1 é um psicólogo por conta |
| `learner_id` | fk `learners`, restrict | Só aprendiz cadastrado; nunca texto livre |
| `starts_at` | datetime | |
| `ends_at` | datetime | Guardado, não calculado: mudar a duração padrão não pode reescrever o passado |
| `status` | string(16) | Enum `AppointmentStatus` |
| `checked_in_at` | datetime, nullable | |
| `checked_out_at` | datetime, nullable | |
| `booking_note` | string(255), nullable | Recado da marcação — logística, **não** prontuário |
| `notes` | text, nullable | Resumo livre da sessão — ver o fluxo 6 |
| `notes_locked_at` | datetime, nullable | Preenchido no check-out. Daí em diante, só aditamento |
| `cancel_reason` | string(255), nullable | |
| `reminder_opened_at` | datetime, nullable | Ver "Lembrete" — é *aberto*, não *enviado* |
| `created_at` / `updated_at` | | |
| `deleted_at` | softDeletes | |

Índices: `(user_id, starts_at)` — é a consulta da agenda, e a única que
importa — e `(learner_id, starts_at)` para o prontuário.

> **Por que `booking_note` é coluna separada.** O formulário de agendamento tem
> um campo "Observação" que a spec descreve como *recado da marcação, não
> registro clínico*. Dividir o campo com `notes` faria o check-out trancá-lo
> junto: "vem com a avó" viraria documento de 20 anos por acidente.

> **Sem `series_id`.** A repetição simples cria linhas independentes por
> decisão (Decisão 2). Guardar um identificador de série que nada consome
> seria prometer um agrupamento que a interface não entrega.

**Soft delete com trava:** apagar é permitido só enquanto o agendamento não
virou prontuário — ou seja, `status = scheduled` e sem `notes`. Depois disso o caminho é *cancelar* (que preserva a linha e o motivo), nunca
apagar. Falta é `no_show`, que também é registro: a ausência de uma criança em
tratamento é informação clínica.

### `appointment_addenda`

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `id` | id | |
| `appointment_id` | fk `appointments`, cascade | |
| `body` | text | |
| `created_at` | datetime | Sem `updated_at`: a tabela é somente-acréscimo |

### Máquina de estados

```
                    ┌──> cancelled
                    │
   scheduled ───────┼──> no_show
       │            │
       └──> in_progress ──> completed
```

| Estado | Rótulo | Como se chega |
| --- | --- | --- |
| `scheduled` | Agendado | Criação |
| `in_progress` | Em atendimento | Check-in |
| `completed` | Concluído | Check-out |
| `no_show` | Faltou | Marcado à mão na agenda |
| `cancelled` | Cancelado | Cancelamento, com motivo |

`completed` e `cancelled` são terminais. Reabrir um atendimento concluído não
existe — o que existe é aditamento.

---

## Regra do conflito de horário

Dois agendamentos conflitam quando **se sobrepõem no tempo**, para o mesmo
psicólogo, e ambos estão ativos (`scheduled` ou `in_progress`):

```
existente.starts_at < novo.ends_at  E  existente.ends_at > novo.starts_at
```

As desigualdades são **estritas** de propósito: 15h–16h e 16h–17h *não*
conflitam. Encostar não é sobrepor, e alertar nesse caso treinaria o
psicólogo a ignorar o alerta.

**O conflito avisa, não impede.** Atender dois irmãos na mesma hora é decisão
clínica legítima. O caso de uso recebe `permitirConflito: bool`:

1. Primeira chamada sem a permissão → devolve a lista de conflitos, não grava.
2. A tela mostra quem já está naquele horário e pede confirmação.
3. Segunda chamada com `permitirConflito: true` → grava.

**A verificação roda de novo no servidor, na mesma transação da gravação.**
Uma tela aberta há dez minutos não pode criar um conflito silencioso porque o
alerta foi calculado antes — é a mesma revalidação que `CompleteAssessment` já
faz.

Na repetição por N semanas, a checagem roda para **cada** ocorrência e o
alerta lista todas as datas em conflito de uma vez. Uma confirmação vale pelo
lote inteiro; não se pergunta oito vezes.

---

## Fluxo do usuário

### 1. A agenda

`GET /agenda` → componente Livewire de página inteira `App\Livewire\Agenda\Schedule`.
É **tela própria**, com entrada no menu principal ao lado de Aprendizes e
Avaliações — não um painel embutido em outra página.

Três visões, alternadas por botões no cabeçalho e guardadas na sessão
(`#[Session]`, como o filtro de pendentes do `LevelBoard` já faz). **O padrão é
o mês**: é assim que a agenda abre na primeira visita, e é o formato que o
Google Calendar acostumou todo mundo a ver primeiro.

| Visão | O que mostra |
| --- | --- |
| **Mês** *(padrão)* | Grade do mês inteiro, uma célula por dia com as fichas do dia. É a visão de ocupação, e é onde a agenda abre |
| **Semana** | Sete colunas sobre a trilha de horas. É a visão de planejamento |
| **Dia** | Trilha de horas de um dia só, com os atendimentos posicionados. É a visão de trabalho |

**A grade do mês**, no formato consagrado: seis linhas de sete dias, começando
no domingo, os dias dos meses vizinhos em tom apagado e o dia de hoje
destacado. Cada célula lista as fichas em ordem de horário; quando não cabem,
a última vira `+3 mais`, que abre o dia. Clicar num espaço vazio abre o modal
de novo agendamento já com a data preenchida; clicar no número do dia troca
para a visão de dia.

Navegação: `‹ hoje ›`, andando na unidade da visão atual — mês a mês, semana a
semana, dia a dia. O fuso é `America/Sao_Paulo`, já configurado em
`config/app.php` — os `datetime` são gravados na hora local, sem conversão.

Cada ficha mostra hora, nome do aprendiz e um ponto de cor do estado (nunca
**só** a cor: o rótulo textual acompanha, como manda o `03-design-system.md`).
No mês o espaço é curto: a ficha encolhe para hora e primeiro nome, e o resto
fica no `title` e na visão de dia.

### 2. Novo agendamento

Modal, aberto pelo botão do cabeçalho ou clicando num horário vazio.

| Campo | Regra |
| --- | --- |
| Aprendiz | Busca entre os cadastrados. Obrigatório |
| Data | Obrigatória |
| Hora de início | Obrigatória |
| Duração | Minutos. Padrão 50, editável |
| Repetir | "por N semanas" (1 a 52). Padrão 1, que é "não repetir" |
| Observação | Opcional — recado da marcação, não é registro clínico |

Ao salvar, se houver conflito, o modal se transforma no alerta descrito acima.

### 3. Cadastro rápido do aprendiz

Se o aprendiz não existe, um link no próprio seletor abre um segundo modal,
**sem sair da agenda**:

| Campo | Regra |
| --- | --- |
| Nome do aprendiz | Obrigatório |
| Nome do pai **ou** da mãe | Ao menos um dos dois |
| Telefone de contato | Obrigatório — é por ele que o lembrete vai |
| Data de nascimento | **Obrigatória** (Decisão 3) |

Chama o **mesmo** `App\Application\Learner\CreateLearner` que a tela de
cadastro usa. Nenhuma regra de aprendiz nasce aqui — se nascesse, teríamos
duas verdades sobre o que é um cadastro válido.

Ao concluir, o aprendiz já vem selecionado no agendamento.

### 4. Lembrete por WhatsApp

Botão na ficha do agendamento. Abre `https://wa.me/<E164>?text=<mensagem>` em
nova aba — o WhatsApp Web no desktop, o aplicativo no celular e no iPad.

Mensagem montada no domínio, com nome do aprendiz, data, hora e o nome da
clínica (ou do psicólogo, quando não houver clínica cadastrada):

```
Olá! Lembrando do atendimento de Kaleo na Clínica Passo a Passo,
quinta-feira (11/09) às 15h. Qualquer imprevisto, é só avisar.
```

O telefone vem de `learners.contact_phone` e passa por normalização para
E.164 (`(11) 90000-0000` → `5511900000000`). Sem telefone, ou com telefone que
não normaliza, o botão fica **desabilitado com o motivo à vista** — nunca
abrindo o WhatsApp num número quebrado.

> **A coluna chama `reminder_opened_at`, não `reminder_sent_at`.** O sistema
> abre o WhatsApp; quem envia é a pessoa. Registrar "enviado" seria afirmar um
> fato que não temos como conhecer, e é justamente o tipo de afirmação que uma
> agenda clínica não pode inventar.

### 5. Check-in

Botão na ficha. `scheduled` → `in_progress`, carimba `checked_in_at` e leva à
tela do atendimento (`GET /atendimentos/{appointment}`).

Sem janela de horário: dá para fazer check-in de qualquer agendamento
`scheduled`. Travar por horário atrapalharia o dia real — a criança chega
adiantada, o atendimento anterior varou — e não protege nada.

### 6. Descrição do atendimento

Campo de texto livre — **um resumo do que foi conversado na sessão**. Não é um
formulário sobre a avaliação: pode ser a anamnese inicial, uma devolutiva aos
pais, uma sessão de intervenção, a aplicação de marcos do VB-MAPP ou uma
consulta que não se encaixa em nenhum desses rótulos. O campo é um só e não
pergunta qual.

> **Por isso o agendamento não aponta para `assessments`.** Amarrar atendimento
> a avaliação obrigaria a inventar uma avaliação para a primeira consulta, e
> deixaria a anamnese sem lugar. O vínculo entre os dois já existe onde precisa
> existir: os dois são do mesmo aprendiz, e o prontuário mostra os dois lado a
> lado.

Nenhuma categoria de atendimento na v1. Uma lista de tipos é vocabulário
clínico, e vocabulário errado engessa o prontuário — se aparecer necessidade
real de filtrar por tipo, a lista vem da psicóloga, não do código.

Salvo continuamente com indicador `Salvo às HH:MM` — mesma mecânica do
`ItemCard`, e pelo mesmo motivo: o psicólogo está com a criança e não vai
redigitar o que se perdeu.

Editável enquanto `in_progress`. Depois do check-out, fechado.

### 7. Check-out

`in_progress` → `completed`. Em uma transação:

1. Carimba `checked_out_at` e **`notes_locked_at`**.
2. O atendimento passa a aparecer no prontuário.

Se a descrição estiver vazia, confirma antes: *"Concluir sem registro
escrito?"*. Atendimento sem registro é legítimo — a criança não colaborou, a
sessão durou cinco minutos — mas tem de ser ato consciente.

Depois do check-out, corrigir se faz por **aditamento**: uma entrada nova,
datada, que aparece abaixo do registro original. O texto original nunca é
reescrito.

> **Por que fechar.** Prontuário é documento. Um registro que pode ser
> reescrito meses depois, sem deixar rastro, não serve como documento — é a
> mesma razão pela qual o laudo desta plataforma é congelado em snapshot.

---

## O prontuário do aprendiz

`GET /aprendizes/{learner}/prontuario` — somente leitura, sem escrita nenhuma.
Agrega o que já existe:

1. **Identificação** — nome, idade hoje, filiação, contato e a data do
   consentimento de imagem.
2. **Avaliações** — as avaliações do aprendiz e os laudos emitidos, com link.
3. **Linha do tempo de atendimentos** — do mais recente ao mais antigo: data,
   duração real (do check-in ao check-out), o registro escrito e os
   aditamentos. Um aprendiz que só fez anamnese tem prontuário; a seção de
   avaliações simplesmente aparece vazia.

Faltas e cancelamentos **aparecem** na linha do tempo, discretos. Uma
sequência de faltas é informação clínica; escondê-la seria editar a história.

---

## Camada de aplicação

Única porta de escrita, como sempre. Em `app/Application/Schedule/`:

| Caso de uso | Faz |
| --- | --- |
| `ScheduleAppointment` | Cria 1..N ocorrências. Recebe `permitirConflito`; revalida na transação |
| `RescheduleAppointment` | Move um agendamento. Mesma checagem de conflito |
| `CancelAppointment` | Exige motivo. Preserva a linha |
| `MarkNoShow` | Registra a falta |
| `CheckInAppointment` | `scheduled` → `in_progress` |
| `SaveSessionNotes` | Grava a descrição. Recusa se `notes_locked_at` |
| `CheckOutAppointment` | Trava a descrição e conclui |
| `AddSessionAddendum` | Acrescenta ao registro fechado |
| `MarkReminderOpened` | Carimba `reminder_opened_at` |

O cadastro rápido **não** ganha caso de uso: chama `Learner\CreateLearner`.

## Domínio

Em `app/Domain/Schedule/`, PHP puro e testável sem banco:

- **`AppointmentStatus`** — enum, com `label()` e `tone()`, no padrão de
  `AssessmentStatus`.
- **`TimeSlot`** — objeto de valor com `starts`, `ends`, `overlaps(TimeSlot)`
  e `duration()`. **A regra do conflito mora aqui**, com teste unitário
  próprio, incluindo o caso de encostar sem sobrepor.
- **`ReminderMessage`** — monta o texto do lembrete a partir de nome, data,
  hora e clínica.

Em `app/Domain/Contact/`:

- **`PhoneNumber`** — normaliza para E.164 e responde `paraWhatsApp()`.
  Devolve `null` quando não dá para normalizar, em vez de chutar um número.

## Autorização

`AppointmentPolicy`, no padrão de `AssessmentPolicy`:

- `view` / `update` — `$appointment->user_id === $user->id`.
- `update` devolve `false` quando o estado é terminal (`completed`,
  `cancelled`).
- `delete` só com `scheduled` e sem notas.

## LGPD

Acrescentar em `docs/operacao.md`, na tabela "o que o sistema trata":

| Dado | Base legal | Onde fica | Retenção |
| --- | --- | --- | --- |
| Horário e comparecimento | Tutela da saúde | `appointments` | Prontuário: 20 anos |
| Registro escrito da sessão | idem | `appointments.notes`, `appointment_addenda` | idem |

E registrar as duas garantias: registro fechado no check-out só muda por
aditamento datado; nenhum dado de aprendiz vai para log.

---

## Entregáveis

- Migrations: `appointments`, `appointment_addenda`
- `app/Domain/Schedule/{AppointmentStatus,TimeSlot,ReminderMessage}.php`
- `app/Domain/Contact/PhoneNumber.php`
- `app/Application/Schedule/*.php` (9 casos de uso da tabela acima)
- `app/Models/{Appointment,AppointmentAddendum}.php`
- `app/Policies/AppointmentPolicy.php`
- `app/Livewire/Agenda/Schedule.php` + visões mês (padrão)/semana/dia
- `app/Livewire/Agenda/AppointmentForm.php` (com conflito e cadastro rápido)
- `app/Livewire/Atendimento/SessionBoard.php` (descrição, check-out, aditamento)
- `app/Http/Controllers/ProntuarioController.php`
- Seção de agenda em `docs/operacao.md` + linhas na tabela LGPD
- Testes Pest

## Tarefas

1. Migrations + enum + models + policy.
2. `TimeSlot` e `PhoneNumber` com testes unitários — a regra do conflito e a
   normalização do telefone antes de qualquer tela.
3. `ScheduleAppointment` com conflito e repetição + testes.
4. Agenda como tela do menu: visão de mês (a padrão), depois semana, depois dia.
5. Modal de agendamento, alerta de conflito, cadastro rápido.
6. Lembrete por WhatsApp.
7. Check-in, descrição com salvamento contínuo, check-out, aditamento.
8. Prontuário do aprendiz.
9. `docs/operacao.md`.

## Critérios de aceite

- [x] `/agenda` abre no mês, com a grade do mês corrente e hoje destacado.
- [x] A visão escolhida persiste entre visitas; `‹ hoje ›` anda na unidade da visão atual.
- [x] Clicar num dia vazio do mês abre o agendamento já com aquela data.
- [x] Marcar em horário ocupado mostra quem já está lá e só grava depois de confirmar.
- [x] 15h–16h e 16h–17h **não** disparam conflito.
- [x] O conflito é revalidado no servidor: duas telas abertas não criam sobreposição silenciosa.
- [x] "Repetir por 8 semanas" cria 8 agendamentos, e uma confirmação cobre o lote.
- [x] Remarcar uma ocorrência não afeta as outras.
- [x] Só aprendiz cadastrado entra na agenda.
- [x] O cadastro rápido cria aprendiz válido sem sair da agenda, chamando `CreateLearner`.
- [x] Lembrete abre o WhatsApp no número dos responsáveis, com a mensagem pronta.
- [x] Sem telefone, o botão de lembrete fica desabilitado explicando por quê.
- [x] Check-in leva à tela do atendimento; a descrição salva sozinha.
- [x] Check-out fecha a descrição; a correção seguinte vira aditamento datado.
- [x] Um atendimento de anamnese, sem avaliação nenhuma no aprendiz, fecha e entra no prontuário.
- [x] Atendimento concluído aparece no prontuário do aprendiz, com os aditamentos.
- [x] Falta e cancelamento aparecem na linha do tempo.
- [x] Nenhuma tela rola de lado no iPad; todo alvo de toque tem 44px.
- [x] Toda a suíte anterior passa sem alteração.

### Testes Pest

| Arquivo | Verifica |
| --- | --- |
| `tests/Unit/Domain/Schedule/TimeSlotTest.php` | sobreposição, encostar sem sobrepor, duração |
| `tests/Unit/Domain/Contact/PhoneNumberTest.php` | E.164, celular e fixo, número impossível |
| `tests/Unit/Domain/Schedule/ReminderMessageTest.php` | texto com e sem clínica |
| `tests/Feature/Agenda/VisoesDaAgendaTest.php` | mês é o padrão, troca de visão persiste, navegação por unidade |
| `tests/Feature/Agenda/AgendarAtendimentoTest.php` | criação, conflito, confirmação, repetição |
| `tests/Feature/Agenda/ConflitoDeHorarioTest.php` | revalidação no servidor, cancelado não conflita |
| `tests/Feature/Agenda/CadastroRapidoTest.php` | campos obrigatórios, reuso de `CreateLearner` |
| `tests/Feature/Agenda/LembreteWhatsappTest.php` | link, número normalizado, botão desabilitado |
| `tests/Feature/Atendimento/CheckInCheckOutTest.php` | estados, trava da descrição, aditamento |
| `tests/Feature/Atendimento/ProntuarioTest.php` | agregação, ordem, faltas visíveis |
| `tests/Feature/Seguranca/AcessoAoAtendimentoTest.php` | policy: agenda e prontuário de outro psicólogo |

---

## Riscos e decisões

**O que se escreve aqui é documento.** A descrição do atendimento não é
anotação de trabalho: é prontuário, com retenção de 20 anos. Por isso fecha no
check-out e só muda por aditamento datado — a mesma razão pela qual o laudo
desta plataforma é congelado em snapshot.

**Campo livre é escolha, e tem custo.** Um resumo sem estrutura não dá relatório
nem filtro — em compensação, aceita anamnese, devolutiva e sessão de teste no
mesmo lugar, sem obrigar ninguém a escolher uma etiqueta antes de escrever.
Para a v1 o custo é o certo a pagar: campo estruturado que não serve à prática
é preenchido no automático, e prontuário preenchido no automático não é
prontuário.

**A agenda é a tela mais mexida do sistema.** Três visões, conflito, cadastro
rápido no meio do caminho. Vale construir na ordem mês → semana → dia — o mês
primeiro porque é a tela de chegada, e é o layout mais apertado, onde 44px de
alvo de toque numa célula de dia deixam de ser detalhe. Validar cada visão no
iPad antes de seguir para a próxima.

**Conflito avisa, não impede.** Bloquear obrigaria a psicóloga a mentir para o
sistema — marcar 15h05 para o irmão que também vem às 15h.

**Sem série de recorrência é decisão, não esquecimento.** Se um dia a
repetição precisar de "cancelar todas as futuras", isso volta à mesa junto com
a coluna `series_id`.

**Gravação de áudio saiu de escopo.** Chegou a estar especificada e foi
retirada a pedido da psicóloga: a forma de trazer a fala do atendimento para
o prontuário — gravação, transcrição ou outra — será decidida depois. O que
fica registrado é o que a retirada evitou por ora: contexto seguro (HTTPS),
formato de áudio que muda entre Chrome e Safari, interrupção da gravação
quando o iPad bloqueia a tela, upload em trechos para não perder 50 minutos
numa queda, e o consentimento dos responsáveis para guardar voz de criança.
Quando o assunto voltar, é por aí que ele começa.

**Também fora desta fase:** envio automático pelo WhatsApp Business API,
agenda com mais de um profissional, sala de espera, cobrança, e confirmação
pelo responsável.
