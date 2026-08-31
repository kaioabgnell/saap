# 01 — Arquitetura

## Princípio

Uma aplicação Laravel monolítica com **duas superfícies de entrega** sobre o
mesmo domínio: a web em Blade/Livewire e a API REST que o app futuro consome.

A regra que sustenta isso: **a camada `Application` é o contrato único de
escrita**. Um componente Livewire e um controller de API chamam *o mesmo* caso
de uso. Nenhuma regra de pontuação, progresso ou travamento existe em dois
lugares.

## Stack e versões

| Camada | Escolha | Motivo da trava |
| --- | --- | --- |
| PHP | **8.3.33** (Homebrew, `/usr/local/opt/php@8.3/bin/php`) | O 8.1 do PATH e o 8.0 do XAMPP não servem |
| Framework | **Laravel 12.x** | O 10 é EOL, com 3 advisories sem patch — dois atingem cadastro e URL assinada |
| Banco | **MariaDB 10.4.28** | XAMPP. Não é MySQL 8 — ver `02-modelo-de-dados.md` |
| Interatividade | **Livewire 4** + Alpine.js 3 | Salvamento por item sem escrever JS |
| CSS | **Tailwind CSS 3.4** + Franken UI 2 | Franken UI é a porta HTML-first do shadcn/ui |
| Ícones | **Font Awesome 6** | Sem emoji na interface, em nenhuma hipótese |
| Auth web | **Laravel Breeze** (stack Blade) | Enxuto, sem SPA |
| Auth API | **Laravel Sanctum** | Tokens pessoais |
| PDF | **dompdf** (`barryvdh/laravel-dompdf`) | Ver restrições de CSS abaixo |
| Testes | **Pest** | |
| Formatação | **Laravel Pint** | |

## Estrutura de diretórios

```
app/
  Domain/                      PHP puro: sem Eloquent, sem HTTP, sem facades
    Vbmapp/
      Scoring/
        ScoreCalculator.php        aplica limiares → 0, 0.5 ou 1
        ResponseType.php           enum dos 5 tipos de item
        ScoringMode.php            enum auto | assisted
      Progress/
        ProgressCounter.php        respondidos por avaliação, nível e área
      Report/
        MilestoneChart.php         monta a matriz do gráfico
        ReportPayload.php          estrutura do snapshot congelado
    Learner/
      Age.php                      idade em anos e meses na data da aplicação
    Assessment/
      AssessmentStatus.php         enum
      LevelStatus.php              enum

  Application/                 casos de uso — única porta de escrita
    Assessment/
      OpenAssessment.php
      StartLevel.php
      SaveResponse.php
      CompleteLevel.php
      CompleteAssessment.php
      CancelAssessment.php
    Learner/
      CreateLearner.php
      UpdateLearner.php
    Report/
      GenerateReport.php

  Models/                      Eloquent, sem regra de negócio
  Http/
    Livewire/
      Assessment/
        LevelBoard.php             tela do nível: áreas, progresso, filtro
        ItemCard.php               um marco — o componente do salvamento
        StimulusGrid.php           grade de imagens com check
        SaveIndicator.php          "Salvo às 14:32"
      Learner/
      Profile/
    Controllers/
      Api/V1/                      superfície REST
    Requests/
    Resources/                     serialização JSON
    Middleware/

  Support/
    Content/
      ManualImporter.php           parser do manual traduzido
      MaterialSlicer.php           recorte das imagens do material
      ThresholdInferrer.php        infere limiares a partir dos critérios

  Jobs/
    GenerateFormPdf.php
    GenerateReportPdf.php
```

## Fronteiras entre camadas

**`Domain` não conhece Laravel.** Recebe e devolve tipos primitivos e objetos de
valor. É testável sem banco. Toda a matemática de pontuação e progresso mora aqui.

**`Application` orquestra.** Carrega models, chama o domínio, persiste em
transação, dispara eventos. Recebe DTOs, devolve DTOs.

**`Http` só traduz.** Um componente Livewire valida entrada, chama o caso de uso,
renderiza. Se um componente Livewire tem `if` sobre regra de pontuação, está errado.

```php
// Errado — regra vazando para a camada HTTP
if ($this->acertos >= $item->threshold_full) { $score = 1; }

// Certo
$resultado = app(SaveResponse::class)->handle(
    new SaveResponseCommand($assessmentId, $itemId, $entries)
);
```

## Rotas

`routes/web.php` — a raiz **sempre** leva ao login:

```php
Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function () {
    // login, registro, recuperação de senha (Breeze)
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/painel', Dashboard::class)->name('painel');
    Route::resource('aprendizes', LearnerController::class);
    Route::get('/perfil', ProfileController::class)->name('perfil');

    Route::prefix('avaliacoes/{assessment}')->group(function () {
        Route::get('/', AssessmentOverview::class)->name('avaliacoes.show');
        Route::get('/nivel/{level}', LevelBoard::class)
            ->whereIn('level', ['1', '2', '3'])
            ->name('avaliacoes.nivel');
        Route::get('/nivel/{level}/formulario.pdf', PrintForm::class)
            ->name('avaliacoes.formulario');
        Route::get('/relatorio', ShowReport::class)->name('avaliacoes.relatorio');
    });
});
```

`routes/api.php` — ver `fases/F8-api-rest.md`.

## Autorização

Toda leitura e escrita passa por policy. Um psicólogo só enxerga os próprios
aprendizes e avaliações.

- `LearnerPolicy` — `$learner->user_id === $user->id`
- `AssessmentPolicy` — mesma regra, mais: `update` retorna `false` quando
  `locked_at !== null`. **É a trava de imutabilidade.** Ela vive na policy, não
  espalhada em `if` pelos componentes.

## Filas

`QUEUE_CONNECTION=database` na v1.

Vão para fila: geração do PDF do formulário, geração do relatório final, e o
recorte de imagens do material. Renderizar 170 itens em requisição HTTP trava o
navegador e estoura o tempo limite.

## Cache

O catálogo VB-MAPP é imutável em runtime — só muda por deploy com seeder novo.

- `Cache::rememberForever("vbmapp.level.{$n}")` para o catálogo de um nível.
- Invalidação apenas no `db:seed`, via `php artisan vbmapp:cache-clear`.
- Nunca cachear respostas, progresso ou qualquer dado de aplicação.

## Storage

Disco `public` para fotos de perfil e de aprendizes, e para o acervo de imagens.

```
storage/app/public/
  perfis/{user_id}/avatar.jpg
  aprendizes/{learner_id}/foto.jpg
  vbmapp/estimulos/nivel-1/{item_code}/{slug}.png
  vbmapp/paginas/nivel-{n}/{arquivo}-p{NNN}.png
```

Fotos de aprendizes são dado sensível de criança. Sirva por URL assinada com
expiração, nunca por link público direto — ver `fases/F2`.

## Restrições do dompdf

O dompdf implementa CSS 2.1. **Não suporta** flexbox, grid, `gap`, variáveis CSS
nem gradientes confiáveis.

Consequência prática para o gráfico de marcos: ele é montado como `<table>`, com
cada marco ocupando **duas linhas de meia altura** — a inferior pintada de âmbar
representa o ½ ponto, as duas pintadas de verde representam 1 ponto. Nada de
gradiente, nada de pseudo-elemento.

As views de PDF vivem em `resources/views/pdf/` e têm folha de estilo própria,
separada do Tailwind da aplicação.

## Convenções de código

- Português do Brasil em código, comentários, commits e interface.
- Nomes de tabela e coluna em inglês (convenção Laravel); rótulos de UI em português.
- `declare(strict_types=1);` em todo arquivo de `Domain` e `Application`.
- Enums nativos do PHP 8.1 para todo estado — nunca strings soltas.
- Toda regra de pontuação tem teste unitário em `tests/Unit/Domain/`.
- `./vendor/bin/pint` antes de cada commit.
