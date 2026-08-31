<?php

declare(strict_types=1);

use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Application\Assessment\StartLevel;
use App\Application\Report\BuildFormPayload;
use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Models\Assessment;
use App\Models\Learner;
use App\Models\User;
use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\Item;
use App\Models\Vbmapp\Stimulus;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);

    $this->psicologo = User::factory()->create(['name' => 'Dra. Ana']);
    $this->learner = Learner::factory()->for($this->psicologo)->create([
        'name' => 'Kaleo', 'birth_date' => '2020-03-15',
    ]);
    $this->assessment = Assessment::factory()->for($this->learner)->for($this->psicologo)
        ->create(['applied_on' => '2026-07-08']);

    $this->build = app(BuildFormPayload::class);
});

function marcoParaForm(string $areaCode, int $posicao): Item
{
    return Item::where('area_id', Area::where('code', $areaCode)->sole()->id)
        ->where('position', $posicao)->sole();
}

it('recusa montar formulário de nível não iniciado', function () {
    $this->build->handle($this->assessment, 1);
})->throws(RuntimeException::class, 'não foi iniciado');

it('monta o cabeçalho com idade na data da aplicação', function () {
    app(StartLevel::class)->handle($this->assessment, 1);

    $payload = $this->build->handle($this->assessment, 1);

    expect($payload->learnerName)->toBe('Kaleo')
        ->and($payload->ageAtApplication)->toBe('6 anos e 3 meses')
        ->and($payload->appliedOn)->toBe('08/07/2026')
        ->and($payload->applicatorName)->toBe('Dra. Ana');
});

it('agrupa por área na ordem do instrumento e só as 9 do nível 1', function () {
    app(StartLevel::class)->handle($this->assessment, 1);

    $payload = $this->build->handle($this->assessment, 1);

    expect($payload->areas)->toHaveCount(9);
    $primeiraArea = $payload->areas[0];
    expect($primeiraArea->shortName)->toBe('Mando')
        ->and($primeiraArea->items)->toHaveCount(5);
});

it('mostra pontuação e resumo de exemplares de counter_free respondido', function () {
    app(StartLevel::class)->handle($this->assessment, 1);
    $item = marcoParaForm('mando', 2); // ½ com 3, 1 com 4

    app(SaveResponse::class)->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: [
            ['position' => 1, 'text_value' => 'bola'],
            ['position' => 2, 'text_value' => 'água'],
            ['position' => 3, 'text_value' => 'pipa'],
        ],
    ));

    $payload = $this->build->handle($this->assessment, 1);
    $area = collect($payload->areas)->firstWhere('shortName', 'Mando');
    $marco = collect($area->items)->firstWhere('position', 2);

    expect($marco->answered)->toBeTrue()
        ->and($marco->scoreLabel)->toBe('½')
        ->and($marco->exemplaresSummary)->toBe('bola, água, pipa')
        ->and($marco->checklist)->toBe([]);
});

it('mostra checklist de estímulos para marco não respondido com material', function () {
    app(StartLevel::class)->handle($this->assessment, 1);

    $payload = $this->build->handle($this->assessment, 1);
    $area = collect($payload->areas)->firstWhere('shortName', 'Tato');
    $marco = collect($area->items)->firstWhere('position', 1);

    expect($marco->answered)->toBeFalse()
        ->and($marco->checklist)->not->toBeEmpty();
});

it('resume qual estímulo foi acertado em counter_stimuli', function () {
    app(StartLevel::class)->handle($this->assessment, 1);
    $item = marcoParaForm('tato', 1); // precisa de 2

    $estimulo1 = Stimulus::factory()->for($item, 'item')->create(['label' => 'bola']);
    $estimulo2 = Stimulus::factory()->for($item, 'item')->create(['label' => 'gato']);
    CatalogCache::flush();

    app(SaveResponse::class)->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: [
            ['position' => 1, 'stimulus_id' => $estimulo1->id, 'is_checked' => true],
            ['position' => 2, 'stimulus_id' => $estimulo2->id, 'is_checked' => true],
        ],
    ));

    $payload = $this->build->handle($this->assessment, 1);
    $area = collect($payload->areas)->firstWhere('shortName', 'Tato');
    $marco = collect($area->items)->firstWhere('position', 1);

    expect($marco->exemplaresSummary)->toBe('bola, gato');
});

it('resume o critério escolhido em binary_criteria', function () {
    app(StartLevel::class)->handle($this->assessment, 1);
    $item = marcoParaForm('brincar', 1);

    app(SaveResponse::class)->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: [['position' => 1, 'is_checked' => true]],
    ));

    $payload = $this->build->handle($this->assessment, 1);
    $area = collect($payload->areas)->firstWhere('shortName', 'Brincar');
    $marco = collect($area->items)->firstWhere('position', 1);

    expect($marco->scoreLabel)->toBe('½')
        ->and($marco->exemplaresSummary)->toBe($item->criteria_half);
});

it('mostra o checklist dos dois critérios quando binary_criteria não respondido', function () {
    app(StartLevel::class)->handle($this->assessment, 1);

    $payload = $this->build->handle($this->assessment, 1);
    $area = collect($payload->areas)->firstWhere('shortName', 'Brincar');
    $marco = collect($area->items)->firstWhere('position', 1);

    expect($marco->checklist)->toHaveCount(2);
});

it('filtra somente pendentes quando pedido', function () {
    app(StartLevel::class)->handle($this->assessment, 1);
    $item = marcoParaForm('mando', 1);

    app(SaveResponse::class)->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        explicitScore: 1.0,
    ));

    $payload = $this->build->handle($this->assessment, 1, onlyPending: true);
    $area = collect($payload->areas)->firstWhere('shortName', 'Mando');

    expect(collect($area->items)->pluck('position'))->not->toContain(1)
        ->and($area->items)->toHaveCount(4);
});

it('omite exemplares quando a opção está desligada', function () {
    app(StartLevel::class)->handle($this->assessment, 1);
    $item = marcoParaForm('mando', 1);

    app(SaveResponse::class)->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: [['position' => 1, 'text_value' => 'bola'], ['position' => 2, 'text_value' => 'água']],
    ));

    $payload = $this->build->handle($this->assessment, 1, includeExamples: false);
    $area = collect($payload->areas)->firstWhere('shortName', 'Mando');
    $marco = collect($area->items)->firstWhere('position', 1);

    expect($marco->answered)->toBeTrue()->and($marco->exemplaresSummary)->toBe('');
});

it('resume linhas marcadas de matrix, ignorando linhas intocadas', function () {
    app(StartLevel::class)->handle($this->assessment, 2);
    $item = marcoParaForm('tato', 7);

    app(SaveResponse::class)->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: [
            ['position' => 1, 'list_key' => 'Maçã', 'column_key' => 'Exemplar 1', 'is_checked' => true],
            ['position' => 2, 'list_key' => 'Maçã', 'column_key' => 'Exemplar 2', 'is_checked' => true],
            ['position' => 3, 'list_key' => 'Maçã', 'column_key' => 'Exemplar 3', 'is_checked' => false],
        ],
    ));

    $payload = $this->build->handle($this->assessment, 2);
    $area = collect($payload->areas)->firstWhere('shortName', 'Tato');
    $marco = collect($area->items)->firstWhere('position', 7);

    expect($marco->exemplaresSummary)->toBe('Maçã (Exemplar 1, Exemplar 2)');
});
