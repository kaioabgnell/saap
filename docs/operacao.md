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

> Os níveis 2 e 3 têm 233 páginas e **não foram curados na v1**. O pipeline é o
> mesmo; falta rodar e revisar.

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
