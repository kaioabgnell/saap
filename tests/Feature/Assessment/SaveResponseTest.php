<?php

declare(strict_types=1);

use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Application\Assessment\StartLevel;
use App\Domain\Vbmapp\Scoring\Score;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use App\Models\Learner;
use App\Models\Response;
use App\Models\ResponseEntry;
use App\Models\User;
use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\Item;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);

    $this->psicologo = User::factory()->create();
    $this->learner = Learner::factory()->for($this->psicologo)->create();
    $this->assessment = Assessment::factory()->for($this->learner)->for($this->psicologo)->create();

    app(StartLevel::class)->handle($this->assessment, 1);

    $this->save = app(SaveResponse::class);
});

function marco(string $areaCode, int $posicao): Item
{
    $area = Area::where('code', $areaCode)->sole();

    return Item::where('area_id', $area->id)->where('position', $posicao)->sole();
}

function caixas(array $textos): array
{
    return array_values(array_map(
        fn (int $i, ?string $t) => ['position' => $i + 1, 'text_value' => $t],
        array_keys($textos),
        $textos,
    ));
}

it('grava counter_free e pontua pelos limiares do catálogo', function () {
    // Mando 2-M: ½ com 3 mandos, 1 ponto com 4.
    $item = marco('mando', 2);

    $r = $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: caixas(['bola', 'água', 'biscoito']),
    ));

    expect($r->tally)->toBe(3)
        ->and($r->score)->toBe(Score::Half)
        ->and($r->isAnswered)->toBeTrue();

    $r = $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: caixas(['bola', 'água', 'biscoito', 'pipa']),
    ));

    expect($r->tally)->toBe(4)->and($r->score)->toBe(Score::Full);
});

it('é idempotente ao gravar a mesma resposta duas vezes', function () {
    $item = marco('mando', 2);
    $comando = new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: caixas(['bola', 'água', 'biscoito', 'pipa']),
    );

    $this->save->handle($comando);
    $this->save->handle($comando);

    expect(Response::count())->toBe(1)
        ->and(ResponseEntry::count())->toBe(4)
        ->and(AssessmentLevel::sole()->answered_count)->toBe(1);
});

it('recalcula answered_count e score_total na mesma transação', function () {
    $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: marco('mando', 1)->id,
        entries: caixas(['bola', 'água']), // 2 de 2 → 1 ponto
    ));
    $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: marco('mando', 2)->id,
        entries: caixas(['bola', 'água', 'pipa']), // 3 de 4 → ½
    ));

    $nivel = AssessmentLevel::sole();

    expect($nivel->answered_count)->toBe(2)
        ->and($nivel->score_total)->toBe(1.5);
});

it('nunca dá meio ponto no marco sem meio ponto', function () {
    // Ouvinte 2-M: threshold_half nulo, 5 acertos para 1 ponto.
    $item = marco('ouvinte', 2);
    expect($item->threshold_half)->toBeNull();

    $r = $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: caixas(['1', '2', '3', '4']),
    ));

    expect($r->score)->toBe(Score::Zero);

    $r = $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: caixas(['1', '2', '3', '4', '5']),
    ));

    expect($r->score)->toBe(Score::Full);
});

it('grava binary_criteria pelo ordinal escolhido', function () {
    // Brincar 1-M: dois critérios escritos, ordinal 2 = 1 ponto, 1 = ½.
    $item = marco('brincar', 1);

    $meio = $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: [['position' => 1, 'is_checked' => true]],
    ));
    expect($meio->score)->toBe(Score::Half);

    $cheio = $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: [['position' => 2, 'is_checked' => true]],
    ));
    expect($cheio->score)->toBe(Score::Full);

    $nenhum = $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: [['position' => 0, 'is_checked' => true]],
    ));
    expect($nenhum->score)->toBe(Score::Zero)
        ->and($nenhum->isAnswered)->toBeTrue();
});

it('conta counter_list pelos checks', function () {
    // Ecóico 2-M: 25 palavras do subteste, ½ com 3, 1 ponto com 5.
    $item = marco('ecoico', 2);
    expect($item->fixed_list)->toHaveCount(25);

    $entradas = array_map(
        fn (int $i) => ['position' => $i, 'list_key' => $item->fixed_list[$i - 1], 'is_checked' => true],
        range(1, 3),
    );

    $r = $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: $entradas,
    ));

    expect($r->tally)->toBe(3)->and($r->score)->toBe(Score::Half);
});

it('deixa marco assisted pendente até a confirmação', function () {
    // Mando 4-M: ½ e 1 ponto pedem os mesmos 5 mandos; só a qualidade separa.
    $item = marco('mando', 4);
    expect($item->scoring_mode->requiresConfirmation())->toBeTrue();

    $r = $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: caixas(['a', 'b', 'c', 'd', 'e']),
    ));

    expect($r->needsConfirmation)->toBeTrue()
        ->and($r->isAnswered)->toBeFalse()
        ->and(AssessmentLevel::sole()->answered_count)->toBe(0);

    // Os exemplares ficam salvos mesmo sem confirmação — nada se perde.
    expect(ResponseEntry::count())->toBe(5);

    $confirmado = $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: caixas(['a', 'b', 'c', 'd', 'e']),
        explicitScore: 0.5,
    ));

    expect($confirmado->score)->toBe(Score::Half)
        ->and($confirmado->isAnswered)->toBeTrue()
        ->and($confirmado->isOverridden)->toBeTrue()
        ->and(AssessmentLevel::sole()->answered_count)->toBe(1);
});

it('não pede confirmação em marco assisted abaixo do limiar', function () {
    $item = marco('mando', 4);

    $r = $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: caixas(['a', 'b']),
    ));

    expect($r->needsConfirmation)->toBeFalse()
        ->and($r->score)->toBe(Score::Zero)
        ->and($r->isAnswered)->toBeTrue();
});

it('registra zero deliberado como respondido', function () {
    $item = marco('mando', 1);

    $r = $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: [],
        explicitScore: 0.0,
    ));

    expect($r->score)->toBe(Score::Zero)
        ->and($r->isAnswered)->toBeTrue()
        ->and(AssessmentLevel::sole()->answered_count)->toBe(1);
});

it('volta o marco a pendente quando o formulário é esvaziado', function () {
    $item = marco('mando', 1);

    $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: caixas(['bola', 'água']),
    ));
    expect(AssessmentLevel::sole()->answered_count)->toBe(1);

    $r = $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: caixas([null, null]),
    ));

    expect($r->isAnswered)->toBeFalse()
        ->and(AssessmentLevel::sole()->answered_count)->toBe(0);
});

it('recusa gravação em avaliação travada', function () {
    $this->assessment->update(['locked_at' => now()]);

    $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: marco('mando', 1)->id,
        entries: caixas(['bola']),
    ));
})->throws(RuntimeException::class, 'concluída');

it('recusa gravação em nível não iniciado', function () {
    $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: marco('mando', 7)->id, // nível 2, não iniciado
        entries: caixas(['bola']),
    ));
})->throws(RuntimeException::class, 'não foi iniciado');

it('devolve progresso nas três granularidades', function () {
    $r = $this->save->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: marco('mando', 1)->id,
        entries: caixas(['bola', 'água']),
    ));

    expect($r->areaProgress->format())->toBe('1 de 5')
        ->and($r->levelProgress->format())->toBe('1 de 45')
        ->and($r->assessmentProgress->format())->toBe('1 de 45'); // só o nível 1 iniciado
});
