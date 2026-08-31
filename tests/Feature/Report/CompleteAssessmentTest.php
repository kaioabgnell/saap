<?php

declare(strict_types=1);

use App\Application\Assessment\CancelAssessment;
use App\Application\Assessment\CompleteAssessment;
use App\Application\Assessment\CompleteLevel;
use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Application\Assessment\StartLevel;
use App\Domain\Assessment\AssessmentStatus;
use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Models\Assessment;
use App\Models\Learner;
use App\Models\ReportSnapshot;
use App\Models\Response;
use App\Models\User;
use App\Models\Vbmapp\Item;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);
    Storage::fake('local');

    $this->psicologo = User::factory()->create(['name' => 'Dra. Ana']);
    $this->learner = Learner::factory()->for($this->psicologo)->create([
        'name' => 'Kaleo', 'birth_date' => '2020-03-15',
    ]);
    $this->assessment = Assessment::factory()->for($this->learner)->for($this->psicologo)
        ->create(['applied_on' => '2026-07-08']);

    $this->actingAs($this->psicologo);
});

/** Responde e conclui um nível inteiro, para deixar a avaliação concluível. */
function completarNivel(Assessment $assessment, int $level, float $score = 1.0): void
{
    $nivel = app(StartLevel::class)->handle($assessment, $level);
    $save = app(SaveResponse::class);

    foreach (Item::where('level', $level)->get() as $item) {
        $save->handle(new SaveResponseCommand(
            assessmentId: $assessment->id, itemId: $item->id, explicitScore: $score,
        ));
    }

    app(CompleteLevel::class)->handle($nivel->refresh());
}

it('impede conclusão com nível iniciado incompleto', function () {
    app(StartLevel::class)->handle($this->assessment, 1);

    app(CompleteAssessment::class)->handle($this->assessment);
})->throws(RuntimeException::class, 'nível incompleto');

it('impede conclusão sem nenhum nível iniciado', function () {
    app(CompleteAssessment::class)->handle($this->assessment);
})->throws(RuntimeException::class, 'Nenhum nível foi iniciado');

it('conclui avaliação que usou apenas o nível 2', function () {
    completarNivel($this->assessment, 2);

    $snapshot = app(CompleteAssessment::class)->handle($this->assessment);
    $this->assessment->refresh();

    expect($this->assessment->status)->toBe(AssessmentStatus::Completed)
        ->and($this->assessment->locked_at)->not->toBeNull()
        ->and($this->assessment->completed_at)->not->toBeNull();

    // O total é sobre o iniciado, não sobre 170.
    $d = $snapshot->reportPayload()->toArray();
    expect($d['total']['marcos'])->toBe(60)
        ->and($d['niveis'])->toHaveCount(1)
        ->and($d['niveis'][0]['nivel'])->toBe(2);
});

it('recusa gravação após o travamento', function () {
    completarNivel($this->assessment, 1);
    app(CompleteAssessment::class)->handle($this->assessment);

    app(SaveResponse::class)->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: Item::where('level', 1)->first()->id,
        explicitScore: 0.0,
    ));
})->throws(RuntimeException::class, 'concluída');

it('congela o snapshot com os dados do momento', function () {
    completarNivel($this->assessment, 1, 0.5);

    $snapshot = app(CompleteAssessment::class)->handle($this->assessment);
    $d = $snapshot->reportPayload()->toArray();

    expect($d['aprendiz']['nome'])->toBe('Kaleo')
        ->and($d['aprendiz']['idade_na_aplicacao'])->toBe('6 anos e 3 meses')
        ->and($d['aplicador']['nome'])->toBe('Dra. Ana')
        ->and($d['total']['pontuacao'])->toBe(22.5) // 45 marcos × ½
        ->and($snapshot->hashIsValid())->toBeTrue();
});

it('mantém o relatório inalterado após mudança no catálogo', function () {
    completarNivel($this->assessment, 1);
    $snapshot = app(CompleteAssessment::class)->handle($this->assessment);

    $enunciadoOriginal = $snapshot->reportPayload()->toArray()['niveis'][0]['areas'][0]['marcos'][0]['enunciado'];

    // Correção no catálogo meses depois — como a revisão clínica ainda vai fazer.
    $marco = Item::where('level', 1)->orderBy('area_id')->orderBy('position')->first();
    $marco->update(['statement' => 'ENUNCIADO CORRIGIDO DEPOIS DO LAUDO']);
    CatalogCache::flush();

    $snapshot->refresh();
    $depois = $snapshot->reportPayload()->toArray()['niveis'][0]['areas'][0]['marcos'][0]['enunciado'];

    expect($depois)->toBe($enunciadoOriginal)
        ->and($depois)->not->toContain('CORRIGIDO')
        ->and($snapshot->hashIsValid())->toBeTrue();
});

it('devolve 404 no relatório de avaliação em andamento', function () {
    app(StartLevel::class)->handle($this->assessment, 1);

    $this->get(route('avaliacoes.relatorio', $this->assessment))->assertNotFound();
});

it('mostra o relatório depois de concluída', function () {
    completarNivel($this->assessment, 1);
    app(CompleteAssessment::class)->handle($this->assessment);

    $this->get(route('avaliacoes.relatorio', $this->assessment))
        ->assertOk()
        ->assertSee('Kaleo')
        ->assertSee('6 anos e 3 meses')
        ->assertSee('Concluída — somente leitura', escape: false);
});

it('recusa relatório de avaliação de outro psicólogo', function () {
    completarNivel($this->assessment, 1);
    app(CompleteAssessment::class)->handle($this->assessment);

    $this->actingAs(User::factory()->create());

    $this->get(route('avaliacoes.relatorio', $this->assessment))->assertForbidden();
});

it('monta o gráfico com meia célula para meio ponto', function () {
    $nivel = app(StartLevel::class)->handle($this->assessment, 1);
    $save = app(SaveResponse::class);

    // Um marco com 1 ponto, outro com ½, o resto zero.
    $marcos = Item::where('level', 1)->orderBy('area_id')->orderBy('position')->get();
    foreach ($marcos as $i => $item) {
        $save->handle(new SaveResponseCommand(
            assessmentId: $this->assessment->id, itemId: $item->id,
            explicitScore: $i === 0 ? 1.0 : ($i === 1 ? 0.5 : 0.0),
        ));
    }
    app(CompleteLevel::class)->handle($nivel->refresh());

    $snapshot = app(CompleteAssessment::class)->handle($this->assessment);
    $chart = $snapshot->reportPayload()->chartFor(1);

    expect($chart->columns)->toHaveCount(9); // 9 áreas no nível 1

    $primeiraColuna = $chart->columns[0];
    // As células vêm de cima para baixo: marco 5 primeiro, marco 1 por último.
    $celulas = collect($primeiraColuna['cells'])->keyBy('position');

    expect($celulas[1]['state'])->toBe('full')
        ->and($celulas[2]['state'])->toBe('half')
        ->and($celulas[3]['state'])->toBe('zero')
        ->and($celulas[1]['label'])->toContain('1 ponto')
        ->and($celulas[2]['label'])->toContain('meio ponto');
});

it('marca como pendente no gráfico o marco não respondido', function () {
    // Nível 2 iniciado e concluído; nível 3 nunca iniciado não entra no laudo.
    completarNivel($this->assessment, 2);
    $snapshot = app(CompleteAssessment::class)->handle($this->assessment);

    expect($snapshot->reportPayload()->levels())->toBe([2])
        ->and($snapshot->reportPayload()->chartFor(3))->toBeNull();
});

it('preserva respostas ao cancelar', function () {
    app(StartLevel::class)->handle($this->assessment, 1);
    $item = Item::where('level', 1)->first();

    app(SaveResponse::class)->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id, itemId: $item->id, explicitScore: 1.0,
    ));

    app(CancelAssessment::class)->handle($this->assessment, 'aprendiz desligado da clínica');
    $this->assessment->refresh();

    expect($this->assessment->status)->toBe(AssessmentStatus::Cancelled)
        ->and($this->assessment->cancel_reason)->toBe('aprendiz desligado da clínica')
        ->and(Response::where('assessment_id', $this->assessment->id)->count())->toBe(1);
});

it('exige motivo para cancelar', function () {
    app(CancelAssessment::class)->handle($this->assessment, '   ');
})->throws(RuntimeException::class, 'motivo');

it('não gera relatório de avaliação cancelada', function () {
    app(CancelAssessment::class)->handle($this->assessment, 'motivo qualquer');

    $this->get(route('avaliacoes.relatorio', $this->assessment))->assertNotFound();
    expect(ReportSnapshot::count())->toBe(0);
});

it('impede concluir avaliação cancelada', function () {
    completarNivel($this->assessment, 1);
    app(CancelAssessment::class)->handle($this->assessment, 'motivo');

    app(CompleteAssessment::class)->handle($this->assessment->refresh());
})->throws(RuntimeException::class, 'cancelada');

it('gera o PDF do relatório e permite baixar', function () {
    completarNivel($this->assessment, 1);
    $snapshot = app(CompleteAssessment::class)->handle($this->assessment);

    $snapshot->refresh();
    expect($snapshot->pdf_path)->not->toBeNull();
    Storage::disk('local')->assertExists($snapshot->pdf_path);

    $this->get(route('avaliacoes.relatorio.pdf', $this->assessment))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition',
            'attachment; filename="relatorio-vbmapp-kaleo-'.now()->format('Y-m-d').'.pdf"');
});
