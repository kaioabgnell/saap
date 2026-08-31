# F0 — Fundação

> **Depende de:** nada · **Estimativa:** ~1 semana · **Habilita:** F1, F2
> **Modelo:** Sonnet 5 (`claude-sonnet-5`) — Scaffolding de caminho batido: `create-project`, Breeze, Tailwind, Vite. Nenhuma decisão de domínio.

> **Status: concluída.** Registro das decisões tomadas na execução, ao final.

## Objetivo

Projeto Laravel rodando localmente contra `saap_local`, com autenticação
funcionando, `/` levando a `/login`, e a estrutura de camadas em pé.

## Pré-requisitos

Já verificados neste ambiente:

- PHP 8.1.33 com `pdo_mysql`, `mbstring`, `gd`, `zip`, `curl`, `bcmath` — ok
- Composer 2.7.2, Node 20.19.2, npm 10.8.2 — ok
- MariaDB 10.4.28 (XAMPP) com `saap_local` criado e **vazio** — ok

## Entregáveis

- Aplicação Laravel 10 em `/Applications/XAMPP/xamppfiles/htdocs/saap`
- `.env` apontando para `saap_local`
- Breeze (stack Blade) com login, registro e recuperação de senha
- Tailwind + Franken UI + Font Awesome compilando via Vite
- Esqueleto de `app/Domain/`, `app/Application/`, `app/Support/`
- Pest configurado, Pint configurado
- Layout base com o wordmark SAAP, navegação e área de conteúdo

## Tarefas

### 1. Criar o projeto

```bash
composer create-project laravel/laravel:^10.0 .
```

**Trave a versão.** Laravel 11 exige PHP ^8.2 e vai quebrar. Fixe em
`composer.json`: `"laravel/framework": "^10.0"`.

O diretório já contém `docs/`, `refs/`, `.claude/` e `CLAUDE.md` — preserve-os.
Se o `create-project` reclamar de diretório não vazio, gere em pasta temporária
e mova o conteúdo.

### 2. Configurar o ambiente

`.env`:

```dotenv
APP_NAME=SAAP
APP_URL=http://127.0.0.1:8000
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=saap_local
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
FILESYSTEM_DISK=public
```

Em `config/database.php`, conexão `mysql`, garanta
`'collation' => 'utf8mb4_unicode_ci'` — o MariaDB 10.4 não conhece
`utf8mb4_0900_ai_ci`.

Em `config/app.php`: `'timezone' => 'America/Sao_Paulo'`.

Confirme a conexão:

```bash
php artisan migrate
/Applications/XAMPP/xamppfiles/bin/mysql -h 127.0.0.1 -u root saap_local -e "SHOW TABLES;"
```

### 3. Autenticação

```bash
composer require laravel/breeze --dev
php artisan breeze:install blade
```

Depois:

- Traduza todas as telas para português.
- Acrescente **telefone** ao formulário de registro — o cadastro pede nome,
  telefone, e-mail e senha. Migration de `users` com as colunas de perfil e
  clínica de `02-modelo-de-dados.md` (as demais ficam nuláveis, preenchidas na F2).
- Redirecione o pós-login para `/painel`.

### 4. Rota raiz

Em `routes/web.php`, **primeira rota do arquivo**:

```php
Route::get('/', fn () => redirect()->route('login'));
```

Requisito explícito: a primeira tela do sistema é `/login`. Um visitante não
autenticado em `/` cai no login; um autenticado é levado a `/painel` pelo
middleware `guest` do Breeze.

### 5. Front-end

```bash
npm install
npm install -D tailwindcss postcss autoprefixer
npm install franken-ui @fortawesome/fontawesome-free
```

- Registre os tokens de `03-design-system.md` em `tailwind.config.js`, sob
  `theme.extend.colors`, com os nomes `primary`, `success`, `warning`, `danger`.
- Importe o CSS do Franken UI e do Font Awesome em `resources/css/app.css`.
- Confirme que `npm run build` gera o bundle sem aviso.

**Não instale nenhuma dependência de emoji nem use emoji em view.**

### 6. Livewire

```bash
composer require livewire/livewire:^3.0
```

Publique a config e confirme que um componente de teste renderiza dentro do
layout base.

### 7. Camadas

Crie os diretórios de `01-arquitetura.md` com um `.gitkeep` cada:

```
app/Domain/{Vbmapp/{Scoring,Progress,Report},Learner,Assessment}
app/Application/{Assessment,Learner,Report}
app/Support/Content
app/Jobs
```

Em `composer.json`, confirme que `App\` mapeia para `app/` — o PSR-4 padrão já
cobre os subdiretórios.

### 8. Qualidade

```bash
composer require pestphp/pest --dev --with-all-dependencies
php artisan pest:install
composer require laravel/pint --dev
```

`phpunit.xml` deve usar **a mesma conexão MySQL**, num banco
`saap_local_test`. Não use SQLite em memória: o MariaDB 10.4 tem
particularidades (JSON como LONGTEXT, ausência de índice funcional) que o SQLite
esconderia até a produção.

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -h 127.0.0.1 -u root -e "CREATE DATABASE IF NOT EXISTS saap_local_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 9. Layout base

`resources/views/layouts/app.blade.php`:

- Wordmark SAAP conforme `03-design-system.md`
- Navegação: Painel, Aprendizes, Perfil
- Menu do usuário com foto e sair
- Área de conteúdo e slot para a barra de ações fixa
- Região de toasts

### 10. Filas

```bash
php artisan queue:table
php artisan migrate
```

## Critérios de aceite

- [ ] `php artisan serve` sobe sem erro em `http://127.0.0.1:8000`
- [ ] `GET /` redireciona para `/login`
- [ ] Registro com nome, telefone, e-mail e senha cria usuário e autentica
- [ ] Login, logout e recuperação de senha funcionam, todos em português
- [ ] Pós-login cai em `/painel`
- [ ] Usuário não autenticado em `/painel` é mandado ao login
- [ ] `php artisan migrate:fresh` roda limpo contra `saap_local`
- [ ] Tabelas criadas com `utf8mb4_unicode_ci` — verificar com
      `SHOW TABLE STATUS FROM saap_local;`
- [ ] `npm run build` compila sem aviso; Tailwind, Franken UI e Font Awesome ativos
- [ ] Um ícone Font Awesome renderiza no layout
- [ ] `php artisan test` passa
- [ ] `./vendor/bin/pint --test` passa
- [ ] `php artisan queue:work` conecta

## Riscos e decisões

**Laravel 10, não 11.** O ambiente é PHP 8.1.33. Não aceite sugestão de
atualizar o framework nesta fase.

**Diretório não vazio.** `docs/` tem 98 MB de PDFs. Acrescente ao `.gitignore`
o que não deve versionar, mas **não apague** — são a fonte do catálogo.

**Não recrie o banco.** `saap_local` já existe. `migrate:fresh` derruba tabelas,
não o schema — é seguro.


---

## Registro de execução

Concluída. Divergências entre o planejado e o executado, e por quê:

### Laravel 12 no lugar do Laravel 10

O plano previa Laravel 10 por causa do PHP 8.1. Na execução, `composer audit`
acusou **3 advisories sem correção** na 10.50.3 — a última versão que existe da
linha 10, que está em fim de vida. Duas atingem este projeto diretamente:

- **CRLF injection na regra de validação de e-mail** (CVE-2026-48019) — o
  formulário de cadastro, entregável desta fase.
- **Temporary Signed URL Path Confusion** — o mecanismo que a F2 usa para
  proteger as fotos dos aprendizes sob LGPD.

Correções existem só a partir da 12.61.1, sem backport. Decisão do usuário:
subir para **Laravel 12.68.0**, que audita limpo.

### PHP 8.3 no lugar do 8.4

`brew install php@8.4` falhou — sem bottle para macOS 26 em Intel (Tier 3 no
Homebrew). O `php@8.3` (8.3.33) tem bottle e satisfaz o `^8.2` do Laravel 12.

O 8.1 continua como **padrão do sistema** para não quebrar os outros projetos em
`htdocs`. Este projeto usa o caminho explícito
`/usr/local/opt/php@8.3/bin/php`. Não rode `brew link php@8.3`.

O Apache do XAMPP roda PHP **8.0.28**, velho demais para qualquer Laravel atual —
servir por ele nunca foi viável. A app roda em `php artisan serve`; o XAMPP
continua responsável apenas pelo MySQL.

### Livewire 4 no lugar do Livewire 3

A versão corrente é a **4.4.2**, e ela suporta PHP ^8.1 e Laravel 10 a 13.
Não havia razão para fixar na 3.

### Tailwind 3.4 com Franken UI 2

O esqueleto do Laravel 12 traz Tailwind 4 via plugin do Vite; o Breeze
sobrescreve com Tailwind 3.4 e PostCSS. O Franken UI 2.1.2 declara
`tailwindcss: ^3.4.9 || ^4.0.0` — funciona nas duas.

Ficou o **Tailwind 3.4**, que é o que as views do Breeze já usam, evitando a
migração de classes que a v4 exigiria. O `@tailwindcss/vite` foi removido por
estar inativo.

### Outros ajustes

- Rota `/dashboard` renomeada para `/painel` e `/profile` para `/perfil`;
  referências corrigidas em controllers, views e testes.
- Traduções pt_BR via `lucascudo/laravel-pt-br-localization`, com 199 chaves
  ajustadas para o vocabulário do domínio.
- `phpunit.xml` aponta para `saap_local_test` em **MySQL**, não SQLite — o
  MariaDB 10.4 tem particularidades que o SQLite esconderia.
- Testes de fábrica do Breeze adaptados às novas rotas e ao telefone obrigatório.

### Verificação

```
Laravel 12.68.0 · PHP 8.3.33 · MariaDB 10.4.28
composer audit    → nenhuma vulnerabilidade
pest              → 26 passando (63 asserções)
pint --test       → limpo
npm run build     → ok
GET /             → 302 para /login
GET /painel       → 302 para /login (sem sessão)
tabelas           → InnoDB, utf8mb4_unicode_ci
```
