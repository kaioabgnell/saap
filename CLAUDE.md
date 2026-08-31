# SAAP — Sistema de Avaliação de Aprendiz

Plataforma web para psicólogos aplicarem o **VB-MAPP** em aprendizes: cadastro,
condução dos três níveis de avaliação com pontuação por marco, salvamento
contínuo e relatório final com gráfico de marcos.

## Especificações

Todas as specs vivem em [`.claude/specs/`](.claude/specs/README.md). **Leia a spec
da fase antes de escrever código.** Ordem de execução e dependências estão em
`.claude/specs/README.md`.

| Documento | Conteúdo |
| --- | --- |
| `.claude/specs/00-visao-geral.md` | Produto, escopo v1, glossário |
| `.claude/specs/01-arquitetura.md` | Camadas, stack, convenções de código |
| `.claude/specs/02-modelo-de-dados.md` | Schema completo, migrations, estados |
| `.claude/specs/03-design-system.md` | Tokens, componentes, responsividade |
| `.claude/specs/04-catalogo-vbmapp.md` | 16 áreas, 170 marcos, tipos de item |
| `.claude/specs/fases/F0..F9` | Fases de implementação, em ordem |

## Modelo por fase

Vinculante. Detalhes e exceções em `.claude/specs/README.md`.

| Opus 5 (`max`/`xhigh`) | Sonnet 5 |
| --- | --- |
| F1 catálogo · F3 motor · F4 imagens · F7 relatório · F9 validação | F0 fundação · F2 cadastros · F5 níveis 2-3 · F6 PDF · F8 API |

Opus nas fases que **decidem** regra de domínio; Sonnet nas que **aplicam** padrão
já estabelecido. Troque com `/model` na fronteira entre fases, em sessão limpa —
nunca no meio de uma. Nunca desça de Opus em qualquer coisa que toque pontuação.

## Stack

- **PHP 8.3.33** via Homebrew — **não** o 8.1 do PATH nem o 8.0 do XAMPP
- **Laravel 12.x** — o 10 é EOL e carrega 3 vulnerabilidades sem patch
- **MariaDB 10.4.28** (XAMPP) — não é MySQL 8, ver restrições em `02-modelo-de-dados.md`
- **Livewire 4** + **Alpine.js** — interatividade
- **Tailwind CSS 3.4** + **Franken UI 2** — componentes
- **Font Awesome 6** — ícones. **Nunca use emoji na interface.**
- **Laravel Sanctum** — auth da API
- **dompdf** — geração de PDF

## Banco de dados

O banco `saap_local` **já existe e está vazio**. Não recrie.

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=saap_local
DB_USERNAME=root
DB_PASSWORD=
```

O cliente `mysql` do PATH é o do Homebrew e falha por plugin de auth.
Use sempre o binário do XAMPP:

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -h 127.0.0.1 -u root saap_local -e "SHOW TABLES;"
```

## PHP: use sempre o 8.3

O `php` do PATH é o **8.1** (Homebrew) e o Apache do XAMPP roda **8.0** — nenhum
dos dois serve. Este projeto exige o 8.3:

```bash
/usr/local/opt/php@8.3/bin/php artisan ...
/usr/local/opt/php@8.3/bin/php /usr/local/bin/composer ...
```

Atalho recomendado para a sessão de trabalho:

```bash
export PATH="/usr/local/opt/php@8.3/bin:$PATH"
```

O 8.1 segue como padrão do sistema de propósito — há outros projetos em `htdocs`
que dependem dele. Não rode `brew link php@8.3`.

## Comandos

Assumindo o `PATH` ajustado acima.

```bash
php artisan serve                  # http://127.0.0.1:8000
npm run dev                        # Vite em watch
php artisan migrate:fresh --seed   # recria schema + catálogo VB-MAPP
php artisan vbmapp:cache-clear     # OBRIGATÓRIO após qualquer mudança no catálogo
php artisan test                   # Pest
./vendor/bin/pint                  # formatação
php artisan queue:work             # fila (PDFs e relatórios)
```

Operação, backup, fila travada e checklist de produção: [`docs/operacao.md`](docs/operacao.md).
API: [`docs/api/v1.md`](docs/api/v1.md).

## Regras de domínio inegociáveis

1. **A rota raiz `/` redireciona para `/login`.** É a primeira tela do sistema.
2. **São 170 marcos**, não 160: 34 pares área/nível × 5 marcos.
   Nível 1 = 9 áreas (marcos 1–5), nível 2 = 12 áreas (6–10), nível 3 = 13 áreas (11–15).
3. **Os limiares de pontuação vêm do manual**, nunca do layout dos PDFs de registro.
   O registro posiciona o `½` graficamente, sem correspondência com o critério real.
4. **`threshold_half` é anulável.** Pelo menos um marco (Ouvinte 2) não tem meio ponto.
5. **Avaliação concluída é imutável.** Depois de `locked_at`, nenhuma resposta muda.
6. **Níveis são independentes.** Um aprendiz pode começar pelo nível 2 ou 3.
7. **A aplicação é longitudinal.** Marcos exigem 30–60 min de observação; uma
   avaliação leva dias ou semanas. Nada de sessão única ou expiração.
8. **O catálogo se lê pelo `CatalogCache`, nunca por consulta direta.** Ele tem
   duas camadas: o `Cache` (atravessa requisições) e o `CatalogMemo` (atravessa
   as chamadas de uma requisição). A segunda não é otimização opcional — sem
   ela, cada um dos 65 cartões da tela do nível vai ao driver de cache, e o
   driver de produção é o `database`. Os testes rodam com `array` e **não
   enxergam esse N+1**.

## Convenções

- Domínio em `app/Domain/` — PHP puro, sem Eloquent nem HTTP, testável isolado.
- Casos de uso em `app/Application/` — a **única** porta de entrada para escrita.
  Livewire e controllers de API chamam o mesmo caso de uso; nunca duplique regra.
- Migrations, seeders e factories sempre versionados.
- Testes com Pest. Toda regra de pontuação tem teste unitário.
- Código, comentários, commits e UI em **português do Brasil**.
