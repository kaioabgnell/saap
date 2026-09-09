<?php

declare(strict_types=1);

use App\Application\Assessment\CompleteChartAssessment;
use App\Application\Assessment\OpenChartAssessment;
use App\Application\Assessment\SaveChartScore;
use App\Domain\Assessment\AssessmentStatus;
use App\Domain\Assessment\LevelStatus;
use App\Jobs\GenerateReportPdf;
use App\Livewire\Assessment\ChartEntry;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use App\Models\Learner;
use App\Models\ReportSnapshot;
use App\Models\Response;
use App\Models\User;
use App\Models\Vbmapp\Item;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);
    Storage::fake('local');

    // A fila é síncrona nos testes: sem isto, cada conclusão renderiza um PDF
    // de 45 marcos e o processo estoura a memória antes do fim da suíte. O
    // conteúdo do PDF tem teste próprio, em ProcedenciaNoLaudoTest.
    Queue::fake();

    $this->psicologo = User::factory()->create(['name' => 'Dra. Ana']);
    $this->learner = Learner::factory()->for($this->psicologo)->create([
        'name' => 'Kaleo', 'birth_date' => '2022-01-10',
    ]);

    $this->assessment = app(OpenChartAssessment::class)->handle(
        $this->learner, $this->psicologo, '2025-03-12', [1],
    );

    $this->actingAs($this->psicologo);
});

it('registra zero em todo marco não marcado', function () {
    $marcados = Item::where('level', 1)->take(32)->get();

    foreach ($marcados as $item) {
        app(SaveChartScore::class)->handle($this->assessment->id, $item->id, 1.0);
    }

    app(CompleteChartAssessment::class)->handle($this->assessment);

    $respostas = Response::where('assessment_id', $this->assessment->id)->get();

    expect($respostas)->toHaveCount(45)
        ->and($respostas->whereNotNull('answered_at'))->toHaveCount(45)
        ->and($respostas->where('score', 0.0))->toHaveCount(13)
        // O zero automático também não é cálculo do sistema.
        ->and($respostas->whereNotNull('computed_score'))->toHaveCount(0)
        ->and($respostas->where('is_overridden', true))->toHaveCount(0);
});

it('conclui o nível e trava a avaliação', function () {
    app(SaveChartScore::class)->handle($this->assessment->id, Item::where('level', 1)->first()->id, 0.5);

    app(CompleteChartAssessment::class)->handle($this->assessment);

    $this->assessment->refresh();
    $nivel = AssessmentLevel::where('assessment_id', $this->assessment->id)->sole();

    expect($nivel->status)->toBe(LevelStatus::Completed)
        ->and($nivel->answered_count)->toBe(45)
        ->and((float) $nivel->score_total)->toBe(0.5)
        ->and($this->assessment->status)->toBe(AssessmentStatus::Completed)
        ->and($this->assessment->locked_at)->not->toBeNull();
});

it('congela o laudo com hash de integridade', function () {
    app(SaveChartScore::class)->handle($this->assessment->id, Item::where('level', 1)->first()->id, 1.0);

    $snapshot = app(CompleteChartAssessment::class)->handle($this->assessment);

    expect(ReportSnapshot::where('assessment_id', $this->assessment->id)->exists())->toBeTrue()
        ->and($snapshot->content_hash)->toHaveLength(64)
        // O payload volta do JSON: 1.0 serializa como 1 e desserializa como int.
        ->and((float) $snapshot->payload['total']['pontuacao'])->toBe(1.0)
        ->and($snapshot->payload['total']['respondidos'])->toBe(45);
});

it('enfileira a geração do PDF', function () {
    app(SaveChartScore::class)->handle($this->assessment->id, Item::where('level', 1)->first()->id, 1.0);

    app(CompleteChartAssessment::class)->handle($this->assessment);

    Queue::assertPushed(GenerateReportPdf::class);
});

it('recusa concluir uma avaliação guiada por este caminho', function () {
    $guiada = Assessment::factory()->for($this->learner)->for($this->psicologo)->create();

    app(CompleteChartAssessment::class)->handle($guiada);
})->throws(RuntimeException::class, 'não é um lançamento por gráfico');

it('recusa concluir duas vezes', function () {
    app(CompleteChartAssessment::class)->handle($this->assessment);
    app(CompleteChartAssessment::class)->handle($this->assessment->refresh());
})->throws(RuntimeException::class, 'já foi concluída');

it('anuncia na tela quantos marcos ficarão com zero antes de concluir', function () {
    foreach (Item::where('level', 1)->take(32)->get() as $item) {
        app(SaveChartScore::class)->handle($this->assessment->id, $item->id, 1.0);
    }

    Livewire::test(ChartEntry::class, ['assessment' => $this->assessment])
        ->call('confirmarConclusao')
        ->assertSee('13 marcos ainda não marcados serão registrados')
        ->assertSee('32 marcados, 13 em zero');
});

it('leva ao relatório depois de concluir pela tela', function () {
    Livewire::test(ChartEntry::class, ['assessment' => $this->assessment])
        ->call('concluir')
        ->assertRedirect(route('avaliacoes.relatorio', $this->assessment));
});

it('marca a célula pela metade clicada', function () {
    $item = Item::where('level', 1)->orderBy('position')->first();

    $tela = Livewire::test(ChartEntry::class, ['assessment' => $this->assessment]);

    $tela->call('marcar', $item->id, 'bottom');
    expect((float) Response::where('item_id', $item->id)->sole()->score)->toBe(0.5);

    $tela->call('marcar', $item->id, 'top');
    expect((float) Response::where('item_id', $item->id)->sole()->score)->toBe(1.0);

    $tela->call('marcar', $item->id, 'bottom');
    expect((float) Response::where('item_id', $item->id)->sole()->score)->toBe(0.0);
});

it('limpa o nível inteiro', function () {
    foreach (Item::where('level', 1)->take(5)->get() as $item) {
        app(SaveChartScore::class)->handle($this->assessment->id, $item->id, 1.0);
    }

    Livewire::test(ChartEntry::class, ['assessment' => $this->assessment])
        ->call('limparNivel', 1);

    expect(Response::where('assessment_id', $this->assessment->id)->count())->toBe(0);
});
