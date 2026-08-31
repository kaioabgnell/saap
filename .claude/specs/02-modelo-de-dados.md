# 02 — Modelo de dados

## Ambiente

O banco `saap_local` **já existe**. Não rode `CREATE DATABASE`.
O banco de testes `saap_local_test` foi criado na F0.

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=saap_local
DB_USERNAME=root
DB_PASSWORD=
```

Cliente de linha de comando (o do PATH é do Homebrew e falha):

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -h 127.0.0.1 -u root saap_local
```

## Restrições do MariaDB 10.4

O servidor é **MariaDB 10.4.28**, não MySQL 8. Diferenças que afetam o schema:

| Restrição | Consequência |
| --- | --- |
| Sem tipo `JSON` nativo — é alias de `LONGTEXT` | `$table->json()` funciona, mas **não** use `->where('payload->campo', ...)`. Filtre em PHP ou crie coluna dedicada. |
| Sem collation `utf8mb4_0900_ai_ci` | Use `utf8mb4_unicode_ci` em `config/database.php`. O banco tem default `utf8mb4_general_ci`; declarar explicitamente evita mistura de collations em join. |
| Sem índice funcional nem descendente | Índices só sobre colunas simples. |
| `CHECK` é suportado (10.2+) mas silenciosamente ignorado em alguns caminhos | **Não confie em `CHECK` para integridade.** Valide no domínio. |
| Comprimento de chave: 3072 bytes com `ROW_FORMAT=DYNAMIC` (já é o default) | `string(255)` em índice funciona. Não precisa de `defaultStringLength(191)`. |

Em `config/database.php`, conexão `mysql`:

```php
'charset'   => 'utf8mb4',
'collation' => 'utf8mb4_unicode_ci',
'strict'    => true,
'engine'    => 'InnoDB',
```

## Blocos do schema

1. **Catálogo do instrumento** (`vbmapp_*`) — imutável em runtime, populado por seeder.
2. **Cadastros** (`users`, `learners`).
3. **Aplicações** (`assessments`, `assessment_levels`, `responses`, `response_entries`).
4. **Relatórios** (`report_snapshots`).

O isolamento do bloco 1 é uma exigência de licenciamento, não estética — ver
`00-visao-geral.md`.

---

## Catálogo do instrumento

### `vbmapp_areas` — 16 linhas

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `id` | id | |
| `code` | string(32), unique | `mando`, `tato`, `vpmts`, `lrffc`… |
| `name` | string(120) | Nome completo exibido no cartão |
| `short_name` | string(24) | Rótulo do gráfico: `Mando`, `VP/MTS` |
| `position` | tinyint | Ordem de exibição, 1–16 |

### `vbmapp_items` — 170 linhas

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `id` | id | |
| `area_id` | fk → `vbmapp_areas` | |
| `level` | tinyint | 1, 2 ou 3 |
| `position` | tinyint | 1–5 no nível 1, 6–10 no 2, 11–15 no 3 |
| `code` | string(16) | Código do manual: `3-M` |
| `statement` | text | Enunciado do marco |
| `objective` | text, nullable | Bloco *OBJETIVO* do manual |
| `materials` | text, nullable | Bloco *MATERIAIS* |
| `examples` | text, nullable | Bloco *EXEMPLOS* |
| `criteria_full` | text | Bloco *1 PONTO*, literal do manual |
| `criteria_half` | text, nullable | Bloco *½ PONTO*. **Nulo quando o marco não tem meio ponto.** |
| `response_type` | string(24) | Ver `04-catalogo-vbmapp.md` |
| `threshold_full` | smallint | Acertos para 1 ponto |
| `threshold_half` | smallint, **nullable** | Acertos para ½. Nulo = marco sem meio ponto. |
| `scoring_mode` | string(12) | `auto` ou `assisted` |
| `observation_minutes` | smallint, nullable | 30 ou 60 quando o marco exige observação |
| `fixed_list` | json, nullable | Lista pré-definida dos tipos `counter_list` e `matrix` |
| `matrix_columns` | json, nullable | Colunas da grade do tipo `matrix` |

Índice único: `(level, area_id, position)`.

> **`threshold_half` anulável não é detalhe.** Pelo menos o marco Ouvinte 2 traz
> "Não há ½ ponto para esta habilidade" no manual. Um `NOT NULL` aqui obriga a
> inventar um limiar e produz pontuação errada.

> **`scoring_mode = assisted`** marca os marcos cujo critério tem componente
> qualitativo que a contagem não resolve. Exemplo real — Mando 4: 1 ponto para
> 5 mandos espontâneos *diferentes*, ½ ponto para 5 mandos espontâneos *sempre
> com a mesma palavra*. A contagem é a mesma; o que muda é a qualidade. Nesses
> marcos o sistema calcula uma sugestão e **exige confirmação explícita**.

### `vbmapp_stimuli` — ~96 linhas na v1 (só nível 1)

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `id` | id | |
| `item_id` | fk → `vbmapp_items` | |
| `label` | string(80) | Nome do estímulo: `bola`, `gato` |
| `image_path` | string(255) | Caminho no disco `public` |
| `source_page` | smallint | Página de origem no PDF, para auditoria |
| `position` | smallint | Ordem na grade |

### `vbmapp_material_pages` — 261 linhas

Páginas inteiras do material, usadas pelos níveis 2 e 3 na v1 e como fallback
no nível 1.

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `id` | id | |
| `level` | tinyint | |
| `area_id` | fk, nullable | Quando o cabeçalho da página identifica a área |
| `item_position` | tinyint, nullable | Quando identifica o marco |
| `image_path` | string(255) | PNG renderizado |
| `page_number` | smallint | |
| `source_file` | string(160) | Nome do PDF de origem |

---

## Cadastros

### `users`

Sobre a tabela do Breeze, acrescentar:

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `phone` | string(20), nullable | |
| `whatsapp` | string(20), nullable | |
| `photo_path` | string(255), nullable | |
| `council_id` | string(40), nullable | Registro profissional (CRP) |
| `clinic_name` | string(160), nullable | |
| `clinic_phone` | string(20), nullable | |
| `clinic_email` | string(160), nullable | |
| `clinic_address` | string(255), nullable | |
| `clinic_city` | string(120), nullable | |
| `clinic_state` | char(2), nullable | |
| `clinic_zip` | string(9), nullable | |
| `vbmapp_license_ref` | string(120), nullable | Marcador de licença — ver `00-visao-geral.md` |

Campos obrigatórios no **cadastro**: `name`, `phone`, `email`, `password`.
Todo o resto é preenchido depois, no perfil.

### `learners`

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `id` | id | |
| `user_id` | fk → `users`, cascade on delete | |
| `name` | string(160) | |
| `birth_date` | date | Base do cálculo de idade |
| `father_name` | string(160), nullable | |
| `mother_name` | string(160), nullable | |
| `contact_phone` | string(20), nullable | |
| `photo_path` | string(255), nullable | |
| `notes` | text, nullable | |
| `timestamps`, `softDeletes` | | |

Índice: `(user_id, name)`.

---

## Aplicações

### `assessments`

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `id` | id | |
| `learner_id` | fk → `learners` | |
| `user_id` | fk → `users` | Aplicador |
| `instrument` | string(24), default `vbmapp` | Prepara outros instrumentos |
| `status` | string(16) | `not_started` `in_progress` `completed` `cancelled` |
| `applied_on` | date | Data da aplicação, exibida no cabeçalho e no laudo |
| `started_at` | datetime, nullable | |
| `completed_at` | datetime, nullable | |
| `locked_at` | datetime, nullable | **Preenchido = imutável** |
| `cancelled_at` | datetime, nullable | |
| `cancel_reason` | string(255), nullable | |
| `observations` | text, nullable | Campo *Observações* do formulário |

Índice: `(learner_id, status)`.

### `assessment_levels`

Uma linha por nível **iniciado**. Níveis nunca iniciados não têm linha.

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `id` | id | |
| `assessment_id` | fk, cascade | |
| `level` | tinyint | |
| `status` | string(16) | `in_progress` `completed` |
| `started_at` | datetime | |
| `completed_at` | datetime, nullable | |
| `answered_count` | smallint, default 0 | Desnormalizado, para não contar a cada render |
| `total_count` | smallint | 45, 60 ou 65 |
| `score_total` | decimal(4,1), default 0 | Soma dos marcos |

Único: `(assessment_id, level)`.

### `responses`

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `id` | id | |
| `assessment_id` | fk, cascade | |
| `item_id` | fk → `vbmapp_items` | |
| `score` | decimal(2,1) | `0.0`, `0.5` ou `1.0` — o valor que vale |
| `computed_score` | decimal(2,1) | O que o sistema calculou |
| `is_overridden` | boolean, default false | `score !== computed_score` |
| `override_reason` | string(255), nullable | |
| `notes` | text, nullable | |
| `answered_at` | datetime | |
| `timestamps` | | `updated_at` alimenta o "Salvo às…" |

**Único: `(assessment_id, item_id)`.** É o que torna a gravação idempotente e
permite `updateOrCreate` sem duplicar — base do reenvio após queda de rede e do
`PUT` idempotente da API.

### `response_entries`

Uma linha por exemplar registrado. É o detalhe que alimenta a contagem.

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `id` | id | |
| `response_id` | fk, cascade | |
| `position` | smallint | Índice da caixa ou do estímulo na grade |
| `stimulus_id` | fk → `vbmapp_stimuli`, nullable | Preenchido no tipo `counter_stimuli` |
| `list_key` | string(80), nullable | Chave na `fixed_list`, tipos `counter_list` e `matrix` |
| `column_key` | string(80), nullable | Coluna da grade, tipo `matrix` |
| `text_value` | string(255), nullable | Tipo `counter_free` |
| `is_checked` | boolean, default false | |

Único: `(response_id, position, column_key)`.

> **Por que tabela separada e não JSON:** o relatório detalha exemplar a
> exemplar, e o MariaDB 10.4 não consulta JSON. Uma linha por exemplar deixa a
> agregação trivial e o laudo auditável.

### `report_snapshots`

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `id` | id | |
| `assessment_id` | fk, unique | Um snapshot por avaliação |
| `payload` | json | Estrutura congelada: identificação, gráfico, itens |
| `pdf_path` | string(255), nullable | Preenchido pelo job |
| `content_hash` | char(64) | SHA-256 do payload, carimbado no PDF |
| `generated_at` | datetime | |

> **Por que congelar.** Se um enunciado ou limiar do catálogo for corrigido meses
> depois, o laudo já emitido continua reproduzindo exatamente o que foi avaliado.
> É o que sustenta a promessa de imutabilidade.

---

## Máquina de estados

### Avaliação

```
not_started ──> in_progress ──> completed
      │               │
      └───────────────┴───────> cancelled
```

- `not_started` → `in_progress`: ao iniciar o **primeiro** nível.
- `in_progress` → `completed`: exige que **todos os níveis iniciados** estejam
  `completed`. Níveis nunca iniciados **não** bloqueiam — um aprendiz pode ser
  avaliado só no nível 2.
- Qualquer estado → `cancelled`: exige motivo. Não apaga respostas.
- `completed` grava `locked_at`. Depois disso, `AssessmentPolicy::update()`
  retorna `false` e nenhuma resposta muda.

### Nível

```
(sem linha) ──> in_progress ──> completed
```

- `completed` exige `answered_count === total_count`.
- Um nível concluído não reabre enquanto a avaliação estiver `in_progress`;
  reabrir é decisão de produto adiada para a v2.

## Invariantes

Validar no domínio, com teste. `CHECK` do MariaDB não é confiável.

1. `score ∈ {0.0, 0.5, 1.0}`.
2. `score = computed_score` sempre que `is_overridden = false`.
3. `threshold_half < threshold_full` quando `threshold_half` não é nulo.
4. Marco com `criteria_half IS NULL` tem `threshold_half IS NULL`, e vice-versa.
5. Nenhuma escrita em `responses` ou `response_entries` quando `locked_at` não é nulo.
6. `assessment_levels.answered_count` = contagem real de `responses` do nível.
   Recalcular na mesma transação da gravação, nunca por job assíncrono.
7. Contagem de itens por nível: 45, 60, 65. Total 170. Teste no seeder.
