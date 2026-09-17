<?php

declare(strict_types=1);

use App\Application\Assessment\CompleteAssessment;
use App\Application\Assessment\CompleteLevel;
use App\Application\Assessment\RecalculateLevelTotals;
use App\Application\Assessment\StartLevel;
use App\Application\Report\GenerateAiSummary;
use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Jobs\GenerateFormPdf;
use App\Jobs\GenerateReportPdf;
use App\Livewire\Assessment\PrintForm;
use App\Models\AiSummary;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use App\Models\Learner;
use App\Models\ReportSnapshot;
use App\Models\Response;
use App\Models\User;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);
    Storage::fake('local');
    config()->set('services.gemini.key', 'chave-de-teste');
    config()->set('services.gemini.model', 'gemini-3.5-flash-lite');

    $this->psicologo = User::factory()->create();
    $this->learner = Learner::factory()->for($this->psicologo)->create([
        'name' => 'Kaleo Nascimento', 'birth_date' => '2020-03-15',
    ]);
    $this->assessment = Assessment::factory()->for($this->learner)->for($this->psicologo)
        ->create(['applied_on' => '2026-07-08']);

    $this->actingAs($this->psicologo);
});

/** Responde o nível inteiro, que é a condição para o resumo existir. */
function responderNivelInteiro(Assessment $assessment, int $level): AssessmentLevel
{
    app(StartLevel::class)->handle($assessment, $level);

    foreach (CatalogCache::level($level) as $area) {
        foreach ($area->items as $item) {
            Response::updateOrCreate(
                ['assessment_id' => $assessment->id, 'item_id' => $item->id],
                ['score' => 1.0, 'computed_score' => 1.0, 'answered_at' => now()],
            );
        }
    }

    $nivel = AssessmentLevel::where('assessment_id', $assessment->id)->where('level', $level)->sole();
    app(RecalculateLevelTotals::class)->handle($nivel);

    return $nivel->refresh();
}

function respostaDoGemini(?string $texto = null): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [[
                'text' => $texto ?? "Dados da avaliação\nFoi aplicado o VB-MAPP, Nível 1. {{APRENDIZ}} obteve 45 de 45 marcos.\n\nDesempenho por área\n{{APRENDIZ}} pontuou o máximo em todas as áreas.",
            ]]]]],
            'usageMetadata' => ['totalTokenCount' => 640],
        ]),
    ]);
}

function gerarPdf(Assessment $assessment, int $level, bool $comResultado, bool $comIa): array
{
    $token = (string) Str::uuid();
    GenerateFormPdf::iniciar($token);

    dispatch_sync(new GenerateFormPdf(
        token: $token, assessmentId: $assessment->id, level: $level,
        onlyPending: false, includeCriteria: false, includeExamples: true,
        slugAprendiz: 'kaleo', includeResults: $comResultado, includeAiSummary: $comIa,
    ));

    return GenerateFormPdf::status($token);
}

// ------------------------------------------------- o que sai daqui

/**
 * A trava mais importante do recurso: o nome de uma criança não vai para
 * terceiro. Ver App\Domain\Vbmapp\Report\SummaryBriefing.
 */
it('não manda o nome do aprendiz para o Google', function () {
    respostaDoGemini();
    responderNivelInteiro($this->assessment, 1);

    app(GenerateAiSummary::class)->handle($this->assessment, 1);

    Http::assertSent(function (Request $req) {
        $corpo = json_encode($req->data(), JSON_UNESCAPED_UNICODE);

        expect($corpo)->not->toContain('Kaleo')
            ->and($corpo)->not->toContain('Nascimento')
            // Nem o nome do psicólogo, nem o da clínica.
            ->and($corpo)->not->toContain($this->psicologo->name);

        return true;
    });
});

it('devolve o texto já com o nome do aprendiz no lugar do marcador', function () {
    respostaDoGemini();
    responderNivelInteiro($this->assessment, 1);

    $resumo = app(GenerateAiSummary::class)->handle($this->assessment, 1);

    expect($resumo->body)->toContain('Kaleo Nascimento')
        ->and($resumo->body)->not->toContain('{{APRENDIZ}}');
});

it('manda a pontuação por área, que é o que o modelo precisa', function () {
    respostaDoGemini();
    responderNivelInteiro($this->assessment, 1);

    app(GenerateAiSummary::class)->handle($this->assessment, 1);

    Http::assertSent(function (Request $req) {
        $texto = $req->data()['contents'][0]['parts'][0]['text'];

        expect($texto)->toContain('Nível 1')
            ->toContain('Mando')
            ->toContain('45 marcos');

        return true;
    });
});

// ------------------------------------------------- quando pode gerar

it('recusa resumo de nível que ainda não está todo respondido', function () {
    respostaDoGemini();
    app(StartLevel::class)->handle($this->assessment, 1);

    app(GenerateAiSummary::class)->handle($this->assessment, 1);
})->throws(RuntimeException::class, 'ainda não está todo respondido');

it('não chama o Google quando o nível está incompleto', function () {
    respostaDoGemini();
    app(StartLevel::class)->handle($this->assessment, 1);

    try {
        app(GenerateAiSummary::class)->handle($this->assessment, 1);
    } catch (RuntimeException) {
        // esperado
    }

    Http::assertNothingSent();
});

it('esconde a opção de IA enquanto o nível não está completo', function () {
    app(StartLevel::class)->handle($this->assessment, 1);

    Livewire::test(PrintForm::class, ['assessment' => $this->assessment, 'level' => 1])
        ->call('abrir')
        ->assertSee('Análise com IA')
        ->assertSee('Disponível só com o nível 1 todo respondido');
});

it('oferece a opção de IA com o nível completo', function () {
    responderNivelInteiro($this->assessment, 1);

    Livewire::test(PrintForm::class, ['assessment' => $this->assessment, 'level' => 1])
        ->call('abrir')
        ->assertSee('Análise com IA')
        ->assertDontSee('Disponível só com o nível');
});

it('some com a opção inteira quando não há chave configurada', function () {
    config()->set('services.gemini.key', null);
    responderNivelInteiro($this->assessment, 1);

    Livewire::test(PrintForm::class, ['assessment' => $this->assessment, 'level' => 1])
        ->call('abrir')
        ->assertDontSee('Análise com IA');
});

// ------------------------------------------------- o PDF não pode cair

/**
 * O requisito central: o resumo é acessório. Qualquer falha dele sai de cena
 * sem levar o PDF junto.
 */
it('gera o PDF mesmo quando o Google devolve erro', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response('falha', 500)]);
    responderNivelInteiro($this->assessment, 1);

    $status = gerarPdf($this->assessment, 1, comResultado: true, comIa: true);

    expect($status['status'])->toBe('ready')
        ->and(AiSummary::count())->toBe(0);
});

it('gera o PDF mesmo quando a conexão com o Google falha', function () {
    Http::fake(fn () => throw new ConnectionException('sem rede'));
    responderNivelInteiro($this->assessment, 1);

    expect(gerarPdf($this->assessment, 1, comResultado: true, comIa: true)['status'])->toBe('ready');
});

it('gera o PDF mesmo quando o Google devolve resposta vazia', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => []])]);
    responderNivelInteiro($this->assessment, 1);

    expect(gerarPdf($this->assessment, 1, comResultado: true, comIa: true)['status'])->toBe('ready')
        ->and(AiSummary::count())->toBe(0);
});

// ------------------------------------------------- as duas caixas

it('não gera resumo quando só o resultado foi pedido', function () {
    respostaDoGemini();
    responderNivelInteiro($this->assessment, 1);

    gerarPdf($this->assessment, 1, comResultado: true, comIa: false);

    Http::assertNothingSent();
    expect(AiSummary::count())->toBe(0);
});

it('gera e guarda o resumo quando a IA foi pedida', function () {
    respostaDoGemini();
    responderNivelInteiro($this->assessment, 1);

    expect(gerarPdf($this->assessment, 1, comResultado: true, comIa: true)['status'])->toBe('ready');

    $resumo = AiSummary::sole();

    expect($resumo->level)->toBe(1)
        ->and($resumo->model)->toBe('gemini-3.5-flash-lite')
        ->and($resumo->tokens_used)->toBe(640)
        ->and($resumo->generated_at)->not->toBeNull();
});

it('recusa gerar PDF sem nenhum dos dois conteúdos', function () {
    responderNivelInteiro($this->assessment, 1);

    Livewire::test(PrintForm::class, ['assessment' => $this->assessment, 'level' => 1])
        ->call('abrir')
        ->set('incluirResultado', false)
        ->set('incluirResumoIa', false)
        ->call('gerar')
        ->assertSee('Escolha ao menos um conteúdo')
        ->assertSet('status', 'idle');
});

// ------------------------------------------------- o registro

/**
 * Somente-acréscimo, como os aditamentos de atendimento: um resumo que já foi
 * enviado à família não some porque outro foi gerado depois.
 */
it('guarda cada geração como uma linha nova', function () {
    respostaDoGemini();
    responderNivelInteiro($this->assessment, 1);

    app(GenerateAiSummary::class)->handle($this->assessment, 1);
    respostaDoGemini('Dados da avaliação\nSegunda versão para {{APRENDIZ}}.');
    $segundo = app(GenerateAiSummary::class)->handle($this->assessment, 1);

    expect(AiSummary::count())->toBe(2)
        ->and(AiSummary::maisRecente($this->assessment->id, 1)->id)->toBe($segundo->id);
});

it('avisa no PDF que o texto foi escrito por IA', function () {
    respostaDoGemini();
    responderNivelInteiro($this->assessment, 1);

    $status = gerarPdf($this->assessment, 1, comResultado: false, comIa: true);
    $texto = extrairTextoDoPdf(Storage::disk('local')->get($status['path']));

    expect($texto)->toContain('gerado por inteligência artificial')
        ->toContain('Não substitui a avaliação')
        // Sem o resultado marcado, os marcos não entram.
        ->and($texto)->not->toContain('1-M');
});

// ------------------------------------------------- o resumo do laudo

/** Conclui a avaliação de verdade, produzindo o snapshot do laudo. */
function concluirAvaliacao(Assessment $assessment): ReportSnapshot
{
    responderNivelInteiro($assessment, 1);
    $nivel = AssessmentLevel::where('assessment_id', $assessment->id)->where('level', 1)->sole();
    app(CompleteLevel::class)->handle($nivel);

    return app(CompleteAssessment::class)->handle($assessment->refresh());
}

it('gera o resumo sozinho ao abrir a tela do laudo, sem perguntar', function () {
    respostaDoGemini();
    concluirAvaliacao($this->assessment);

    // Ninguém pediu o resumo: nem checkbox, nem botão. Quem o produz é o
    // caminho normal do laudo — o job do PDF que a conclusão dispara, ou a
    // primeira visita à tela, o que vier antes.
    AiSummary::query()->delete();

    $this->get(route('avaliacoes.relatorio', $this->assessment))
        ->assertOk()
        ->assertSee('Resumo da avaliação')
        ->assertSee('Texto gerado por inteligência artificial')
        ->assertSee('Kaleo Nascimento');

    expect(AiSummary::whereNull('level')->count())->toBe(1);
});

/**
 * Um laudo cujo resumo muda a cada visita não é laudo. A tela e o PDF têm de
 * mostrar o MESMO texto.
 */
it('reaproveita o resumo em vez de redigir outro a cada visita', function () {
    respostaDoGemini();
    concluirAvaliacao($this->assessment);

    $this->get(route('avaliacoes.relatorio', $this->assessment))->assertOk();
    $primeiro = AiSummary::whereNull('level')->sole();

    respostaDoGemini('Dados da avaliação\nTexto COMPLETAMENTE diferente para {{APRENDIZ}}.');
    $this->get(route('avaliacoes.relatorio', $this->assessment))->assertOk();

    expect(AiSummary::whereNull('level')->count())->toBe(1)
        ->and(AiSummary::whereNull('level')->sole()->body)->toBe($primeiro->body);
});

it('não mistura o resumo do laudo com o de um nível', function () {
    respostaDoGemini();
    concluirAvaliacao($this->assessment);

    app(GenerateAiSummary::class)->handle($this->assessment, 1);
    $this->get(route('avaliacoes.relatorio', $this->assessment))->assertOk();

    expect(AiSummary::whereNull('level')->count())->toBe(1)
        ->and(AiSummary::where('level', 1)->count())->toBe(1);
});

/** O laudo é o documento; o resumo é acessório dele. */
it('abre o laudo normalmente quando a IA falha', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response('falha', 500)]);
    concluirAvaliacao($this->assessment);

    $this->get(route('avaliacoes.relatorio', $this->assessment))
        ->assertOk()
        ->assertSee('Relatório VB-MAPP')
        ->assertDontSee('Texto gerado por inteligência artificial');

    expect(AiSummary::count())->toBe(0);
});

it('abre o laudo normalmente sem chave configurada', function () {
    config()->set('services.gemini.key', null);
    concluirAvaliacao($this->assessment);

    $this->get(route('avaliacoes.relatorio', $this->assessment))
        ->assertOk()
        ->assertDontSee('Texto gerado por inteligência artificial');
});

it('manda os níveis e as áreas no briefing do laudo', function () {
    respostaDoGemini();
    concluirAvaliacao($this->assessment);

    $this->get(route('avaliacoes.relatorio', $this->assessment))->assertOk();

    Http::assertSent(function (Request $req) {
        $texto = $req->data()['contents'][0]['parts'][0]['text'];
        $instrucao = $req->data()['systemInstruction']['parts'][0]['text'];

        expect($texto)->toContain('Avaliação VB-MAPP concluída')
            ->toContain('Nível 1')
            ->toContain('Mando');
        expect($instrucao)->toContain('Desempenho por nível')
            ->toContain('NÃO faça diagnóstico');

        return true;
    });
});

it('também não manda o nome do aprendiz no resumo do laudo', function () {
    respostaDoGemini();
    concluirAvaliacao($this->assessment);

    $this->get(route('avaliacoes.relatorio', $this->assessment))->assertOk();

    Http::assertSent(function (Request $req) {
        expect(json_encode($req->data(), JSON_UNESCAPED_UNICODE))->not->toContain('Kaleo');

        return true;
    });
});

it('leva o mesmo resumo para o PDF do laudo', function () {
    respostaDoGemini();
    $snapshot = concluirAvaliacao($this->assessment);

    // A tela gerou o texto; o PDF tem de usar aquele, não outro.
    $this->get(route('avaliacoes.relatorio', $this->assessment))->assertOk();
    $resumo = AiSummary::whereNull('level')->sole();

    dispatch_sync(new GenerateReportPdf($snapshot->id));

    $texto = extrairTextoDoPdf(Storage::disk('local')->get($snapshot->refresh()->pdf_path));

    expect($texto)->toContain('gerado por inteligência artificial')
        ->toContain('Kaleo Nascimento')
        ->and(AiSummary::whereNull('level')->count())->toBe(1)
        ->and(AiSummary::whereNull('level')->sole()->id)->toBe($resumo->id);
});

/** O snapshot é o registro congelado: o texto da IA não pode entrar nele. */
it('não toca no snapshot congelado nem no hash', function () {
    respostaDoGemini();
    $snapshot = concluirAvaliacao($this->assessment);
    $hashAntes = $snapshot->content_hash;

    $this->get(route('avaliacoes.relatorio', $this->assessment))->assertOk();

    $snapshot->refresh();

    expect($snapshot->content_hash)->toBe($hashAntes)
        ->and($snapshot->hashIsValid())->toBeTrue()
        ->and(json_encode($snapshot->payload, JSON_UNESCAPED_UNICODE))
        ->not->toContain('inteligência artificial');
});

// ------------------------------------------------- a suíte não liga para fora

/**
 * Guarda contra um defeito que já aconteceu: sem neutralizar a chave no
 * phpunit.xml, a suíte herdava a do `.env` e — com a fila em `sync` — todo
 * teste que concluía uma avaliação disparava o job do laudo e chamava o
 * Google de verdade. Custava dinheiro, deixava a suíte 50 s mais lenta e
 * mandava dado de teste para terceiro.
 */
it('mantém o resumo por IA desligado por padrão na suíte', function () {
    // O beforeEach deste arquivo liga a chave de propósito; aqui interessa o
    // que vale para todos os OUTROS testes.
    expect(env('GEMINI_API_KEY'))->toBe('');
});

it('não chama o Google ao concluir uma avaliação sem chave', function () {
    config()->set('services.gemini.key', null);
    Http::fake();

    concluirAvaliacao($this->assessment);

    Http::assertNothingSent();
    expect(AiSummary::count())->toBe(0);
});
