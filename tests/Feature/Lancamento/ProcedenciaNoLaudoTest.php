<?php

declare(strict_types=1);

use App\Application\Assessment\CompleteAssessment;
use App\Application\Assessment\CompleteChartAssessment;
use App\Application\Assessment\CompleteLevel;
use App\Application\Assessment\OpenChartAssessment;
use App\Application\Assessment\SaveChartScore;
use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Application\Assessment\StartLevel;
use App\Models\Assessment;
use App\Models\Learner;
use App\Models\User;
use App\Models\Vbmapp\Item;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);
    Storage::fake('local');

    $this->psicologo = User::factory()->create(['name' => 'Dra. Ana']);
    $this->learner = Learner::factory()->for($this->psicologo)->create([
        'name' => 'Kaleo', 'birth_date' => '2022-01-10',
    ]);

    $this->actingAs($this->psicologo);
});

/**
 * A fila é síncrona nos testes e cada conclusão renderiza um PDF inteiro.
 * Só o teste que LÊ o PDF paga esse preço; os outros conferem o payload e a
 * tela, e não têm por que gastar 40 MB para isso.
 */
function semGerarPdf(): void
{
    Queue::fake();
}

/** Transcrição concluída, com um marco cheio e um meio. */
function transcricaoConcluida(): Assessment
{
    $assessment = app(OpenChartAssessment::class)->handle(
        test()->learner, test()->psicologo, '2025-03-12', [1],
    );

    $marcos = Item::where('level', 1)->orderBy('position')->take(2)->get();
    app(SaveChartScore::class)->handle($assessment->id, $marcos[0]->id, 1.0);
    app(SaveChartScore::class)->handle($assessment->id, $marcos[1]->id, 0.5);

    app(CompleteChartAssessment::class)->handle($assessment);

    return $assessment->refresh();
}

it('congela a procedência dentro do payload', function () {
    semGerarPdf();

    $snapshot = transcricaoConcluida()->reportSnapshot;

    expect($snapshot->payload['aplicacao']['modo'])->toBe('transcricao')
        ->and($snapshot->payload['aplicacao']['data'])->toBe('2025-03-12')
        ->and($snapshot->payload['aplicacao']['transcrita_em'])->not->toBeNull();
});

it('põe a procedência dentro do hash de integridade', function () {
    semGerarPdf();

    $snapshot = transcricaoConcluida()->reportSnapshot;

    $adulterado = $snapshot->payload;
    $adulterado['aplicacao']['modo'] = 'aplicacao';

    $hashAdulterado = hash('sha256', json_encode(
        $adulterado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ));

    // Converter um laudo transcrito em "aplicado no sistema" quebra a
    // verificação de integridade. É esse o ponto de a procedência ir no hash.
    expect($hashAdulterado)->not->toBe($snapshot->content_hash);
});

it('anuncia a transcrição na tela do relatório', function () {
    semGerarPdf();

    $assessment = transcricaoConcluida();

    $this->get(route('avaliacoes.relatorio', $assessment))
        ->assertOk()
        ->assertSee('Transcrição — somente leitura')
        ->assertSee('Resultados transcritos')
        ->assertSee('12/03/2025');
});

it('anuncia a transcrição no PDF', function () {
    $snapshot = transcricaoConcluida()->reportSnapshot;

    $texto = extrairTextoDoPdf(Storage::disk('local')->get($snapshot->pdf_path));

    expect($texto)->toContain('TRANSCRIÇÃO')
        ->toContain('Resultados transcritos')
        ->and($texto)->not->toContain('RELATÓRIO FINAL')
        // Sem exemplares: eles ficaram no formulário de papel.
        ->and($texto)->not->toContain('Registrado:');
});

it('não registra exemplar nenhum no laudo transcrito', function () {
    semGerarPdf();

    $assessment = transcricaoConcluida();

    $marcos = collect($assessment->reportSnapshot->payload['niveis'][0]['areas'])
        ->flatMap(fn (array $area) => $area['marcos']);

    expect($marcos)->toHaveCount(45)
        ->and($marcos->every(fn (array $m) => $m['exemplares'] === []))->toBeTrue()
        ->and($marcos->every(fn (array $m) => $m['sobrescrito'] === false))->toBeTrue()
        ->and($marcos->every(fn (array $m) => $m['score_calculado'] === null))->toBeTrue();

    // "Registrado:" não é assertível na tela: o rótulo existe no molde do
    // tooltip, escondido pelo Alpine. Quem prova a ausência é o PDF, acima.
    $this->get(route('avaliacoes.relatorio', $assessment))
        ->assertOk()
        ->assertDontSee('pontuação ajustada pelo aplicador');
});

it('deixa o laudo guiado exatamente como era', function () {
    semGerarPdf();

    $assessment = Assessment::factory()->for($this->learner)->for($this->psicologo)
        ->create(['applied_on' => '2026-07-08']);

    $nivel = app(StartLevel::class)->handle($assessment, 1);

    foreach (Item::where('level', 1)->get() as $item) {
        app(SaveResponse::class)->handle(new SaveResponseCommand(
            assessmentId: $assessment->id, itemId: $item->id, explicitScore: 1.0,
        ));
    }

    app(CompleteLevel::class)->handle($nivel->refresh());
    $snapshot = app(CompleteAssessment::class)->handle($assessment->refresh());

    expect($snapshot->payload['aplicacao']['modo'])->toBe('aplicacao')
        ->and($snapshot->payload['aplicacao']['transcrita_em'])->toBeNull();

    $this->get(route('avaliacoes.relatorio', $assessment->refresh()))
        ->assertOk()
        ->assertSee('Concluída — somente leitura')
        ->assertDontSee('Resultados transcritos');
});

it('imprime a idade que o aprendiz tinha na data do papel', function () {
    semGerarPdf();

    $assessment = transcricaoConcluida();

    // Nascido em 10/01/2022, aplicado em 12/03/2025: 3 anos e 2 meses —
    // não a idade de hoje.
    expect($assessment->reportSnapshot->payload['aprendiz']['idade_na_aplicacao'])
        ->toBe('3 anos e 2 meses');
});
