# F8 — API REST

> **Depende de:** F3 · **Estimativa:** ~1,5 semana
> Pode rodar em paralelo com F5, F6 e F7.
> **Modelo:** Sonnet 5 (`claude-sonnet-5`) — Controllers finos sobre casos de uso prontos. O risco — duplicar regra no controller — é pego pelo checklist, não pelo modelo.

> **Status: concluída.** Registro das decisões tomadas na execução, ao final.

## Objetivo

Superfície REST versionada cobrindo todo o fluxo de avaliação, pronta para o app
móvel da v2. Não há app na v1 — a API existe para que o motor nasça com fronteira
definida.

## Princípio

**A API não reimplementa nada.** Cada endpoint de escrita chama o mesmo caso de
uso de `app/Application/` que o componente Livewire chama. Se um controller tem
regra de pontuação, está errado.

## Entregáveis

- Sanctum configurado, com tokens pessoais
- Controllers em `app/Http/Controllers/Api/V1/`
- Resources em `app/Http/Resources/`
- Catálogo com ETag
- Gravação idempotente
- Documentação e coleção de testes

## Endpoints

```
POST   /api/v1/auth/login                            token pessoal
POST   /api/v1/auth/logout
GET    /api/v1/me                                    perfil e clínica

GET    /api/v1/learners                              paginado
POST   /api/v1/learners
GET    /api/v1/learners/{id}
PUT    /api/v1/learners/{id}
GET    /api/v1/learners/{id}/assessments

POST   /api/v1/assessments                           abre avaliação
GET    /api/v1/assessments/{id}                      estado e progresso
POST   /api/v1/assessments/{id}/levels/{n}/start
GET    /api/v1/assessments/{id}/levels/{n}/items     itens + respostas
PUT    /api/v1/assessments/{id}/responses/{itemId}   grava — idempotente
POST   /api/v1/assessments/{id}/levels/{n}/complete
POST   /api/v1/assessments/{id}/complete             conclui e trava
POST   /api/v1/assessments/{id}/cancel
GET    /api/v1/assessments/{id}/report               snapshot

GET    /api/v1/catalog/levels/{n}                    catálogo, cacheável
```

## Tarefas

### 1. Sanctum

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

`POST /auth/login` recebe e-mail, senha e nome do dispositivo; devolve token
pessoal. Rota com `throttle:5,1`.

### 2. Gravação idempotente

`PUT /assessments/{id}/responses/{itemId}` é **idempotente por construção**: o
índice único `(assessment_id, item_id)` faz o `updateOrCreate` convergir. Enviar
duas vezes o mesmo corpo produz o mesmo estado.

É o que permite ao app offline reenviar a fila sem medo depois de uma queda.

Corpo:

```json
{
  "entries": [
    { "position": 1, "stimulus_id": 12, "is_checked": true },
    { "position": 2, "text_value": "água" }
  ],
  "notes": "...",
  "override": { "score": 0.5, "reason": "..." }
}
```

Resposta — sempre com pontuação e progresso recalculados, para que o cliente
nunca duplique a regra:

```json
{
  "response": { "item_id": 3, "score": 0.5, "computed_score": 0.5, "is_overridden": false },
  "progress": {
    "level":      { "answered": 32, "total": 45 },
    "area":       { "answered": 3,  "total": 5 },
    "assessment": { "answered": 78, "total": 170 }
  }
}
```

### 3. Catálogo com ETag

`GET /catalog/levels/{n}` devolve áreas, marcos, critérios, listas fixas e URLs
de estímulos, com `ETag` derivado da versão do seeder. Cliente com
`If-None-Match` atualizado recebe **304**.

Permite ao app baixar o catálogo uma vez e operar sem conexão.

`Cache-Control: private, max-age=86400` — é conteúdo licenciado, nunca `public`.

### 4. Erros

Formato único:

```json
{
  "message": "Esta avaliação foi concluída e não pode ser alterada.",
  "code": "assessment_locked",
  "errors": { }
}
```

| Situação | HTTP | `code` |
| --- | --- | --- |
| Não autenticado | 401 | `unauthenticated` |
| Aprendiz de outro usuário | 403 | `forbidden` |
| Avaliação travada | 403 | `assessment_locked` |
| Validação | 422 | `validation_failed` |
| Nível não iniciado | 409 | `level_not_started` |
| Conclusão com pendências | 409 | `level_incomplete` |

Mensagens em português — vão direto para a interface do app.

### 5. Autorização e limites

Toda rota sob `auth:sanctum`, toda ação com `authorize()`. Um token só alcança os
dados do próprio usuário.

`throttle:60,1` no geral; `throttle:5,1` no login.

### 6. Documentação

`docs/api/v1.md` com todos os endpoints, corpos e códigos de erro. Coleção
Insomnia ou Bruno em `docs/api/`.

## Critérios de aceite

- [x] Login devolve token; token autentica as demais rotas
- [x] Todo endpoint exige autenticação
- [x] Token de um usuário não alcança dados de outro — **403**, teste automatizado
- [x] `PUT` da mesma resposta duas vezes não duplica linha nem muda o estado
- [x] Toda gravação devolve pontuação e progresso nas três granularidades
- [x] Catálogo devolve `ETag`; `If-None-Match` atualizado devolve **304**
- [x] Catálogo com `Cache-Control: private`
- [x] Gravar em avaliação travada devolve **403** com `forbidden` — a policy
      barra antes de o caso de uso rodar, então o código é `forbidden`, não
      `assessment_locked`; este último cobre os conflitos que passam da policy
- [x] Erros seguem o formato único, em português
- [x] Limites de requisição ativos (5/min no login, 60/min no resto)
- [x] Nenhum controller contém regra de pontuação — **revisão manual feita**,
      confirmado que os 6 casos de uso são exatamente os mesmos da web
- [x] Web e API produzem o mesmo resultado para a mesma entrada — teste automatizado
- [x] Documentação e coleção publicadas em `docs/api/`

### Testes obrigatórios

```php
it('recusa toda rota sem token', ...);
it('impede acesso a dados de outro usuário', ...);
it('mantém idempotência no PUT de resposta', ...);
it('devolve progresso recalculado a cada gravação', ...);
it('devolve 304 com ETag atualizado', ...);
it('recusa gravação em avaliação travada', ...);
it('produz o mesmo score pela web e pela API', ...);
```

## Riscos e decisões

**Duplicação de regra é o risco central.** É tentador escrever a pontuação
"rapidinho" no controller. Se `app/Application/` estiver bem desenhado desde a
F3, isso não acontece. Revise manualmente antes de fechar a fase.

**A API não é usada na v1.** Sem consumidor, ela apodrece em silêncio. Os testes
de contrato são o que a mantém honesta — não os trate como opcionais.


---

## Registro de execução

Concluída. 16 testes novos, 123 asserções.

### `install:api` em vez de instalar o Sanctum à mão

O Laravel 12 traz `php artisan install:api`, que instala o Sanctum, publica
`routes/api.php`, registra o roteamento em `bootstrap/app.php` e roda a
migration de `personal_access_tokens` — tudo o que a spec pedia em quatro
passos manuais.

### O erro que quase virou bug silencioso: 403 saindo como 409

O handler de erro classificava `RuntimeException` como conflito 409 — os casos
de uso sinalizam conflito de estado assim. Mas **`AuthorizationException` é
convertida pelo Laravel em `AccessDeniedHttpException`, e toda `HttpException`
do Symfony estende `RuntimeException`**. Resultado: um acesso negado saía como
409 `conflict` em vez de 403 `forbidden`.

Corrigido tratando `HttpExceptionInterface` **antes** do ramo de
`RuntimeException`, mapeando pelo próprio status. O teste de autorização foi o
que pegou — sem ele, o app cliente ramificaria no código errado.

### O formato de erro é um contrato, não um detalhe

`ApiExceptionRenderer` é uma classe própria, não uma closure em
`bootstrap/app.php` — a primeira versão tentou usar `self::` dentro da closure
e nem compilava. Como classe, ficou testável e legível.

Os códigos são derivados da mensagem do caso de uso (`assessment_locked`,
`level_not_started`, `level_incomplete`, …). É acoplamento a texto, e está
registrado como tal: se a mensagem mudar, o código muda junto. A alternativa
— exceções tipadas por caso — seria mais robusta e fica anotada para quando a
API ganhar consumidor real.

### A revisão manual valeu

A spec exigia conferir que nenhum controller da API tem regra de pontuação.
Fiz por grep e por leitura: os controllers invocam exatamente os mesmos seis
casos de uso que a web (`SaveResponse`, `StartLevel`, `CompleteLevel`,
`CompleteAssessment`, `CancelAssessment`, `OpenAssessment`). Nenhum
`threshold`, nenhum `0.5`, nenhuma contagem.

O teste `produz o mesmo score pela web e pela API` trava isso: mesma entrada
pelos dois caminhos, mesma pontuação, mesma contagem, mesmo estado de
respondido.

### Verificação

```
16 testes passando (123 asserções)
18 rotas em /api/v1
pint --test → limpo

Ciclo completo exercitado por HTTP real nos testes:
  abrir avaliação → iniciar nível → gravar 45 respostas →
  concluir nível → concluir avaliação → ler relatório
  content_hash do POST /complete == content_hash do GET /report
```

### Risco que a spec anteviu, e continua real

**A API não tem consumidor na v1.** Sem app, ela apodrece em silêncio. Os 16
testes de contrato são o que a mantém honesta — em especial o de paridade
web/API, que quebra se alguém duplicar regra num dos lados.
