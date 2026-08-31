<?php

declare(strict_types=1);

use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Application\Assessment\StartLevel;
use App\Livewire\Assessment\ItemCard;
use App\Livewire\Assessment\LevelBoard;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use App\Models\Learner;
use App\Models\User;
use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\Item;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);

    $this->psicologo = User::factory()->create();
    $this->learner = Learner::factory()->for($this->psicologo)->create(['name' => 'Kaleo']);
    $this->assessment = Assessment::factory()->for($this->learner)->for($this->psicologo)->create();

    $this->actingAs($this->psicologo);
});

function item(string $areaCode, int $posicao): Item
{
    return Item::where('area_id', Area::where('code', $areaCode)->sole()->id)
        ->where('position', $posicao)->sole();
}

it('inicia o nível ao abrir a tela', function () {
    Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 1]);

    $nivel = AssessmentLevel::sole();
    expect($nivel->level)->toBe(1)->and($nivel->total_count)->toBe(45);
});

it('mostra as 9 áreas do nível 1 e só elas', function () {
    $board = Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 1]);

    expect($board->instance()->areas)->toHaveCount(9);

    $codigos = $board->instance()->areas->pluck('code')->all();
    expect($codigos)->toContain('mando', 'tato', 'ouvinte', 'vpmts', 'brincar', 'social', 'imitacao', 'ecoico', 'vocal')
        ->and($codigos)->not->toContain('lrffc', 'intraverbal', 'leitura', 'escrita', 'matematica');
});

it('mostra 12 áreas no nível 2 e 13 no nível 3', function () {
    $n2 = Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 2]);
    expect($n2->instance()->areas)->toHaveCount(12);

    $outra = Assessment::factory()->for(Learner::factory()->for($this->psicologo))->for($this->psicologo)->create();
    $n3 = Livewire::test(LevelBoard::class, ['assessment' => $outra, 'level' => 3]);
    expect($n3->instance()->areas)->toHaveCount(13);
});

it('mostra 5 marcos por área', function () {
    $board = Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 1]);

    foreach ($board->instance()->areas as $area) {
        expect($area->items)->toHaveCount(5, $area->code);
    }
});

it('renderiza só a área selecionada', function () {
    $board = Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 1])
        ->set('areaCode', 'mando');

    expect($board->instance()->marcosVisiveis)->toHaveCount(5);
});

it('renderiza os 45 marcos quando o psicólogo pede todas as áreas', function () {
    $board = Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 1])
        ->call('selecionarArea', null);

    expect($board->instance()->marcosVisiveis)->toHaveCount(45);
});

it('exibe o cabeçalho de identificação com a idade na data da aplicação', function () {
    $this->learner->update(['birth_date' => '2020-03-15']);
    $this->assessment->update(['applied_on' => '2026-07-08']);

    Livewire::test(LevelBoard::class, ['assessment' => $this->assessment->fresh(), 'level' => 1])
        ->assertSee('Kaleo')
        ->assertSee('6 anos e 3 meses')
        ->assertSee('08/07/2026')
        ->assertSee($this->psicologo->name);
});

it('filtra somente as não respondidas', function () {
    $board = Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 1])
        ->set('areaCode', 'brincar');

    expect($board->instance()->marcosVisiveis)->toHaveCount(5);

    // Responde um marco da área
    Livewire::test(ItemCard::class, [
        'assessment' => $this->assessment,
        'item' => item('brincar', 1),
    ])->set('ordinal', 2);

    $board = Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 1])
        ->set('areaCode', 'brincar')
        ->call('alternarPendentes');

    expect($board->instance()->somentePendentes)->toBeTrue()
        ->and($board->instance()->marcosVisiveis)->toHaveCount(4);
});

it('conta progresso do nível e por área', function () {
    app(StartLevel::class)->handle($this->assessment, 1);

    Livewire::test(ItemCard::class, [
        'assessment' => $this->assessment,
        'item' => item('brincar', 1),
    ])->set('ordinal', 2);

    $board = Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 1]);

    expect($board->instance()->levelProgress->format())->toBe('1 de 45')
        ->and($board->instance()->respondidasPorArea['brincar'])->toBe(1)
        ->and($board->instance()->respondidasPorArea['mando'])->toBe(0);
});

it('recusa abrir nível de avaliação de outro psicólogo', function () {
    $outro = User::factory()->create();

    // Pelo caminho real — o navegador chega ao componente pela rota.
    $this->actingAs($outro)
        ->get(route('avaliacoes.nivel', ['assessment' => $this->assessment, 'level' => 1]))
        ->assertForbidden();

    // E a autorização barra antes de qualquer efeito colateral.
    expect(AssessmentLevel::count())->toBe(0);
});

it('recusa abrir nível sem estar autenticado', function () {
    auth()->logout();

    $this->get(route('avaliacoes.nivel', ['assessment' => $this->assessment, 'level' => 1]))
        ->assertRedirect(route('login'));
});

it('conclui o nível quando os 45 marcos estão respondidos', function () {
    $board = Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 1]);

    $save = app(SaveResponse::class);
    foreach (Item::where('level', 1)->get() as $item) {
        $save->handle(new SaveResponseCommand(
            assessmentId: $this->assessment->id, itemId: $item->id, explicitScore: 0.0,
        ));
    }

    $board->call('confirmarConclusao')->assertSet('confirmandoConclusao', true)
        ->call('concluirNivel');

    expect(AssessmentLevel::sole()->status->value)->toBe('completed');
});

it('recusa concluir nível incompleto e mostra o motivo', function () {
    Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 1])
        ->call('concluirNivel')
        ->assertSet('erro', fn ($erro) => str_contains((string) $erro, '0 de 45'));

    expect(AssessmentLevel::sole()->status->value)->toBe('in_progress');
});
