# F2 — Perfil, aprendizes e painel

> **Depende de:** F0 · **Estimativa:** ~1,5 semana · **Habilita:** F3
> Pode rodar em paralelo com F1.
> **Modelo:** Sonnet 5 (`claude-sonnet-5`) — CRUD, upload e policies convencionais. Exceção: `Age.php` tem casos de borda — revise com atenção ou eleve para Opus só esse arquivo.

> **Status: concluída.** Registro das decisões tomadas na execução, ao final.

## Objetivo

O psicólogo completa o próprio perfil, cadastra aprendizes e vê o painel de
avaliações com estado e progresso.

## Entregáveis

- Tela de perfil: dados pessoais, WhatsApp, foto, dados da clínica
- CRUD de aprendizes com foto e idade calculada
- `app/Domain/Learner/Age.php`
- Painel com a lista de aprendizes e suas avaliações
- Abertura de avaliação com data de aplicação
- `LearnerPolicy` e `AssessmentPolicy`
- Serviço de upload de imagem com redimensionamento

## Tarefas

### 1. Perfil

`/perfil`, com duas seções:

**Dados pessoais** — nome, e-mail, telefone, WhatsApp, registro profissional
(CRP), foto, senha.

**Dados da clínica** — nome, telefone, e-mail, endereço, cidade, estado, CEP.

Tudo opcional exceto nome e e-mail. O cadastro inicial pede só nome, telefone,
e-mail e senha; o perfil é onde o resto entra.

### 2. Upload de imagem

`app/Support/ImageUploader.php`:

- Aceita JPEG, PNG, WebP; máximo 5 MB
- Redimensiona para no máximo 800×800, mantendo proporção
- Gera miniatura 160×160 para listas e para o cabeçalho de aplicação
- Grava em `perfis/{user_id}/` e `aprendizes/{learner_id}/`
- Remove o arquivo anterior ao substituir

**Fotos de aprendizes são dado sensível de criança.** Sirva por URL assinada com
expiração curta (`URL::temporarySignedRoute`), nunca por link público direto.
Isso vale também para a miniatura.

### 3. Aprendizes

CRUD em `/aprendizes`, campos conforme `02-modelo-de-dados.md`: nome, data de
nascimento, nome do pai, nome da mãe, telefone de contato, foto, observações.

Obrigatórios: **nome** e **data de nascimento** — a idade depende dela.

Validação: `birth_date` não pode ser futura nem anterior a 30 anos atrás.

Exclusão é `softDelete`. Se houver avaliação concluída, **bloqueie a exclusão** e
ofereça arquivar — o laudo emitido precisa continuar rastreável ao aprendiz.

### 4. Cálculo de idade

`app/Domain/Learner/Age.php`, PHP puro:

```php
Age::between(birthDate: '2020-03-15', reference: '2024-10-20')->format();
// "4 anos e 7 meses"
```

Regras:

- Sempre calculada **na data da aplicação** (`assessments.applied_on`), nunca hoje
- Formato do instrumento: anos e meses
- Menos de 1 ano: `"8 meses"`
- Exatamente 1 ano: `"1 ano"`; 1 ano e 1 mês: `"1 ano e 1 mês"` — singular correto
- Zero meses: `"3 anos"`, sem "e 0 meses"

Teste com casos de virada: nascido em 31/01, referência 28/02; ano bissexto.

### 5. Painel

`/painel` lista aprendizes com, para cada um:

- Foto, nome, idade **hoje**
- Avaliações com estado, data de aplicação e progresso
- Ação primária: continuar a avaliação em andamento, ou abrir uma nova

Estados exibidos conforme `03-design-system.md`:

| Estado | Aparência |
| --- | --- |
| Não iniciada | Cinza, ação "Iniciar" |
| Iniciada | Índigo, barra de progresso `x de 170`, ação "Continuar" |
| Concluída | Verde, ação "Ver relatório" |
| Cancelada | Cinza esmaecido, motivo em tooltip |

Numa avaliação iniciada, mostre também o progresso **por nível iniciado**
(`Nível 1 · 32 de 45`) e **há quanto tempo está aberta** — a aplicação é
longitudinal e esse dado importa.

### 6. Abertura de avaliação

`OpenAssessment` cria com `status = not_started`, `instrument = vbmapp` e
`applied_on` informada pelo psicólogo (default hoje, editável — a aplicação pode
ser registrada depois).

Um aprendiz pode ter várias avaliações ao longo do tempo. **Impeça duas
`in_progress` simultâneas** para o mesmo aprendiz.

### 7. Policies

- `LearnerPolicy` — `$learner->user_id === $user->id` em tudo
- `AssessmentPolicy` — mesma regra, e `update()` retorna `false` quando
  `locked_at !== null`

Registre em `AuthServiceProvider` e aplique com `authorize()` em **todo** ponto
de entrada, web e API.

## Critérios de aceite

- [x] Perfil salva dados pessoais e da clínica; foto sobe e aparece no menu
- [x] Aprendiz é criado com todos os campos; foto sobe e redimensiona
- [x] Idade calculada corretamente, com singular e plural certos
- [x] Idade usa a data da aplicação, não a data de hoje
- [x] Painel lista só os aprendizes do usuário logado
- [x] Acessar aprendiz de outro usuário devolve **403** — teste automatizado
- [x] Foto de aprendiz não é acessível por URL direta sem assinatura
- [x] Abertura de avaliação grava `applied_on` e `status = not_started`
- [x] Segunda avaliação `in_progress` para o mesmo aprendiz é bloqueada
- [x] Exclusão de aprendiz com avaliação concluída é bloqueada
- [~] Layout íntegro em iPad e celular — só revisado por classes responsivas,
      sem teste em dispositivo real; teste de verdade fica para a F9

### Testes obrigatórios

```php
it('calcula idade em anos e meses na data da aplicação', ...);
it('usa singular para 1 ano e 1 mês', ...);
it('omite meses quando são zero', ...);
it('impede acesso a aprendiz de outro psicólogo', ...);
it('impede duas avaliações em andamento para o mesmo aprendiz', ...);
it('bloqueia exclusão de aprendiz com avaliação concluída', ...);
```

## Riscos e decisões

**Idade é regra de domínio, não helper de view.** Ela aparece no cabeçalho de
aplicação, no laudo e no gráfico, sempre na data da aplicação. Um `diffForHumans`
espalhado pelas views produz laudos com idades diferentes conforme o dia em que
o PDF foi aberto.

**LGPD.** São dados de crianças: nome, foto, filiação, telefone. Defina base de
tratamento e política de retenção agora, e mantenha as fotos fora de URL pública.


---

## Registro de execução

Concluída. `learners` e `assessments` semeados nesta fase — `assessment_levels`,
`responses` e `response_entries` ficam para a F3, que é quem os usa.

### Adaptações ao Laravel 12

O Laravel 12 mudou convenções desde que `01-arquitetura.md` foi escrito:

- **Sem `AuthServiceProvider`.** O skeleton não gera mais esse provider. As
  policies (`LearnerPolicy`, `AssessmentPolicy`) foram registradas via
  `Gate::policy()` dentro de `AppServiceProvider::boot()`.
- **`Controller` base sem `AuthorizesRequests`.** Precisou adicionar a trait
  manualmente para `$this->authorize()` funcionar nos controllers.

### `Route::resource` singulariza errado

`Route::resource('aprendizes', ...)` gera o parâmetro `{aprendize}` — o
singularizador do Laravel não conhece plural irregular em português. Isso
quebraria silenciosamente o *route model binding* implícito, porque o nome do
parâmetro da rota não bateria com `Learner $learner` no controller. Corrigido
com `->parameters(['aprendizes' => 'learner'])`.

### Fotos: dois discos, por causa da sensibilidade dos dados

Foto de aprendiz vai para o disco **`local`** (privado, sem symlink); foto de
psicólogo vai para o disco **`public`** (menu, exibição direta). A assimetria é
proposital — só a primeira é dado sensível de criança.

Isso quase deu em um conflito de rota: o Laravel 12 tem um recurso novo em que
o disco `local` com `'serve' => true` **auto-registra** uma rota em `/storage`
para servir arquivos com URL assinada — só que é a mesma URI que o disco
`public` já usa via *symlink*. Preferi desligar esse `serve` automático
(`config/filesystems.php`) e escrever `LearnerPhotoController` manualmente, com
`URL::temporarySignedRoute` explícito e checagem de `Gate::authorize('view', …)`
**mesmo com assinatura válida** — a assinatura garante que o link não foi
adulterado e não expirou, não que quem o abriu tem permissão. Testado com um
caso específico: assinatura válida gerada para o dono, aberta por outro usuário
logado — **403**.

A miniatura não ganhou coluna no banco. O caminho é derivado por convenção —
sufixo `-thumb.jpg` no lugar da extensão — em `ImageUploader::thumbnailPathFor()`,
compartilhado entre quem grava e quem serve.

### Intervention Image v4

A API mudou bastante das versões anteriores: `ImageManager` recebe o driver no
construtor (`new ImageManager(new Driver())`), decodifica com `decodePath()`, e
os métodos de redimensionamento são `scaleDown()` (para a foto cheia, sem
recorte, sem ampliar) e `cover()` (para a miniatura quadrada, com recorte).
Registrado como singleton em `AppServiceProvider`.

### Regra de "avaliação aberta" generalizada

A spec pedia para impedir duas avaliações **em andamento**. Implementei
impedindo duas **abertas** — `not_started` OU `in_progress` — porque não faz
sentido abrir uma segunda enquanto a primeira espera para ser retomada. Testado
os dois casos separadamente.

### Dois bugs pegos antes de ir para produção

**Inversão de argumentos.** `AssessmentController::store()` chamava
`OpenAssessment::handle($request->user(), $learner, ...)`, mas a assinatura do
caso de uso é `handle(Learner $learner, User $user, ...)`. Como os dois tipos
são compatíveis o PHP não acusaria erro — só uma leitura atenta antes de rodar
os testes pegou.

**Vazamento de arquivo real em teste.** O primeiro teste de upload de foto
escreveu de verdade em `storage/app/private/aprendizes/`, porque o teste não
tinha `Storage::fake('local')`. `RefreshDatabase` não limpa disco. Corrigido com
`beforeEach(fn () => Storage::fake('local'))` no arquivo de teste, e os arquivos
vazados foram apagados manualmente.

### `AssessmentController@show` é placeholder

A tela de detalhe da avaliação (`/avaliacoes/{assessment}`) já traz o cabeçalho
de identificação completo — foto, nome, idade **na data da aplicação**, data e
aplicador — mas não aplica nível nenhum. Isso é da F3. O placeholder existe
para o painel ter para onde linkar e para validar a integração
`Learner::ageAt()` de ponta a ponta.

### Verificação

```
86 testes passando (275 asserções) — 22 novos desta fase
pint --test → limpo
Verificado ao vivo: idade no painel (hoje) ≠ idade na avaliação (applied_on)
  nascido 2020-03-15, hoje 2026-08-28  → "6 anos e 5 meses"
  nascido 2020-03-15, aplicado 2026-07-08 → "6 anos e 3 meses"
```

### Pendente para F9

- [ ] Validação em dispositivo real (iPad/celular) — só revisão de classes
      responsivas foi feita nesta fase, sem teste em navegador
- [ ] Conferir alvo de toque mínimo 44×44px nos botões desta fase (alguns
      usam `py-1.5`, abaixo do alvo do design system)
