<?php

declare(strict_types=1);

use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Application\Assessment\StartLevel;
use App\Domain\Vbmapp\Progress\ProgressCounter;
use App\Livewire\Assessment\ItemCard;
use App\Models\Assessment;
use App\Models\Learner;
use App\Models\Response;
use App\Models\ResponseEntry;
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
    $this->learner = Learner::factory()->for($this->psicologo)->create();
    $this->assessment = Assessment::factory()->for($this->learner)->for($this->psicologo)->create();
    $this->actingAs($this->psicologo);
});

function marcoDoNivel(string $areaCode, int $posicao): Item
{
    return Item::where('area_id', Area::where('code', $areaCode)->sole()->id)
        ->where('position', $posicao)->sole();
}

function cartaoDe(Item $item, ?Response $response = null)
{
    return Livewire::test(ItemCard::class, [
        'assessment' => test()->assessment,
        'item' => $item,
        'response' => $response,
    ]);
}

// --- Matrix: Tato 7-M (rows_complete, nível 2) ---

it('monta a grade do Tato 7 com 50 linhas e 3 colunas', function () {
    app(StartLevel::class)->handle($this->assessment, 2);
    $item = marcoDoNivel('tato', 7);

    $card = cartaoDe($item);

    expect($card->instance()->linhasDaMatriz())->toHaveCount(50)
        ->and($card->instance()->colunasDaMatriz())->toBe(['Exemplar 1', 'Exemplar 2', 'Exemplar 3']);
});

it('conta acerto de matrix apenas com todos os exemplares marcados', function () {
    app(StartLevel::class)->handle($this->assessment, 2);
    $item = marcoDoNivel('tato', 7); // rows_complete: ½ com 25 linhas, 1 com 50

    $card = cartaoDe($item);

    // Marca 2 dos 3 exemplares da primeira linha (Maçã) — linha incompleta.
    $card->set('matrizMarcadas.Maçã::Exemplar 1', true)
        ->set('matrizMarcadas.Maçã::Exemplar 2', true);

    expect($card->get('acertos'))->toBe(0);

    // Completa a linha — agora conta 1.
    $card->set('matrizMarcadas.Maçã::Exemplar 3', true);
    expect($card->get('acertos'))->toBe(1);
});

it('grava list_key e column_key da matrix', function () {
    app(StartLevel::class)->handle($this->assessment, 2);
    $item = marcoDoNivel('tato', 7);

    cartaoDe($item)
        ->set('matrizMarcadas.Gato::Exemplar 1', true)
        ->set('matrizMarcadas.Gato::Exemplar 2', true)
        ->set('matrizMarcadas.Gato::Exemplar 3', true);

    $entradas = ResponseEntry::where('is_checked', true)->get();

    expect($entradas)->toHaveCount(3);
    foreach ($entradas as $e) {
        expect($e->list_key)->toBe('Gato')->and($e->column_key)->toBeIn(['Exemplar 1', 'Exemplar 2', 'Exemplar 3']);
    }
});

it('acrescenta linha à matrix além da lista fixa', function () {
    app(StartLevel::class)->handle($this->assessment, 2);
    $item = marcoDoNivel('tato', 7);

    $card = cartaoDe($item)
        ->set('novaLinhaMatrix', 'Girafa de pelúcia')
        ->call('acrescentarLinhaMatriz');

    expect($card->instance()->linhasDaMatriz())->toContain('Girafa de pelúcia')
        ->and($card->instance()->linhasDaMatriz())->toHaveCount(51);
});

it('preserva a matrix ao recarregar', function () {
    app(StartLevel::class)->handle($this->assessment, 2);
    $item = marcoDoNivel('tato', 7);

    cartaoDe($item)
        ->set('matrizMarcadas.Bola::Exemplar 1', true)
        ->set('matrizMarcadas.Bola::Exemplar 2', true);

    $response = Response::with('entries')->sole();
    $recarregado = cartaoDe($item->fresh(), $response);

    expect($recarregado->get('matrizMarcadas')['Bola::Exemplar 1'])->toBeTrue()
        ->and($recarregado->get('matrizMarcadas')['Bola::Exemplar 2'])->toBeTrue();
});

// --- Matrix: Tato 11-M (total_cells, nível 3, sem lista fixa) ---

it('conta total de células no Tato 11, não linhas completas', function () {
    app(StartLevel::class)->handle($this->assessment, 3);
    $item = marcoDoNivel('tato', 11); // total_cells: ½ com 10, 1 com 15

    $card = cartaoDe($item)
        ->set('novaLinhaMatrix', 'maçã')->call('acrescentarLinhaMatriz')
        ->set('novaLinhaMatrix', 'lixeira')->call('acrescentarLinhaMatriz');

    $card->set('matrizMarcadas.maçã::Cor', true)
        ->set('matrizMarcadas.maçã::Forma', true)
        ->set('matrizMarcadas.lixeira::Cor', true);

    // 3 células certas, nenhuma linha completa — mas total_cells conta assim mesmo.
    expect($card->get('acertos'))->toBe(3);
});

it('não tem lista fixa no Tato 11 — as linhas vêm só do que for acrescentado', function () {
    app(StartLevel::class)->handle($this->assessment, 3);
    $item = marcoDoNivel('tato', 11);

    expect($item->fixed_list)->toBeNull();

    $card = cartaoDe($item);
    expect($card->instance()->linhasDaMatriz())->toBeEmpty();
});

// --- counter_list: acréscimo ---

it('acrescenta item fora da lista fixa em counter_list', function () {
    app(StartLevel::class)->handle($this->assessment, 1);
    $item = marcoDoNivel('ecoico', 2); // 25 palavras, ½ com 3, 1 com 5

    $card = cartaoDe($item)
        ->set('marcadas.Ah', true)
        ->set('marcadas.eu', true)
        ->set('novoItemLista', 'mamãe')
        ->call('acrescentarItemLista');

    expect($card->get('acertos'))->toBe(3)->and($card->get('score'))->toBe(0.5);

    $extra = ResponseEntry::whereNull('list_key')->where('text_value', 'mamãe')->first();
    expect($extra)->not->toBeNull();
});

it('não altera o catálogo ao acrescentar item', function () {
    app(StartLevel::class)->handle($this->assessment, 1);
    $item = marcoDoNivel('ecoico', 2);
    $listaOriginal = $item->fixed_list;

    cartaoDe($item)->set('novoItemLista', 'extra')->call('acrescentarItemLista');

    expect($item->fresh()->fixed_list)->toBe($listaOriginal);
});

// --- Navegação entre níveis / progresso global ---

it('soma 170 respostas com os três níveis completos', function () {
    $save = app(SaveResponse::class);

    foreach ([1, 2, 3] as $nivel) {
        app(StartLevel::class)->handle($this->assessment, $nivel);

        foreach (Item::where('level', $nivel)->get() as $item) {
            $save->handle(new SaveResponseCommand(
                assessmentId: $this->assessment->id,
                itemId: $item->id,
                explicitScore: 1.0,
            ));
        }
    }

    $total = Response::where('assessment_id', $this->assessment->id)
        ->whereNotNull('answered_at')->count();

    expect($total)->toBe(170);

    $progresso = app(ProgressCounter::class)
        ->forAssessment($total, [1, 2, 3]);

    expect($progresso->format())->toBe('170 de 170')->and($progresso->isComplete())->toBeTrue();
});
