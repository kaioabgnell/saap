<?php

declare(strict_types=1);

use App\Application\Assessment\OpenChartAssessment;
use App\Application\Assessment\SaveChartScore;
use App\Domain\Vbmapp\Chart\ChartGrid;
use App\Models\AssessmentLevel;
use App\Models\Learner;
use App\Models\Response;
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

    $this->assessment = app(OpenChartAssessment::class)->handle(
        $this->learner, $this->psicologo, '2025-03-12', [1],
    );

    $this->save = app(SaveChartScore::class);
});

function marcoLancamento(string $areaCode, int $posicao): Item
{
    $area = Area::where('code', $areaCode)->sole();

    return Item::where('area_id', $area->id)->where('position', $posicao)->sole();
}

it('grava a pontuação sem cálculo, sem exemplares e sem sobrescrita', function () {
    $item = marcoLancamento('mando', 3);

    $resultado = $this->save->handle($this->assessment->id, $item->id, 1.0);

    $resposta = Response::where('assessment_id', $this->assessment->id)
        ->where('item_id', $item->id)->sole();

    expect((float) $resposta->score)->toBe(1.0)
        // O sistema não calculou nada: a pontuação veio pronta do papel.
        ->and($resposta->computed_score)->toBeNull()
        // E ninguém discordou de cálculo nenhum.
        ->and($resposta->is_overridden)->toBeFalse()
        ->and($resposta->override_reason)->toBeNull()
        ->and($resposta->answered_at)->not->toBeNull()
        ->and($resposta->entries()->count())->toBe(0)
        ->and($resultado->state)->toBe(ChartGrid::ESTADO_CHEIO);
});

it('registra meio ponto', function () {
    $item = marcoLancamento('mando', 3);

    $this->save->handle($this->assessment->id, $item->id, 0.5);

    expect((float) Response::where('item_id', $item->id)->sole()->score)->toBe(0.5);
});

it('registra zero como pontuação, não como pendência', function () {
    $item = marcoLancamento('mando', 3);

    $this->save->handle($this->assessment->id, $item->id, 0.0);

    $resposta = Response::where('item_id', $item->id)->sole();

    expect((float) $resposta->score)->toBe(0.0)
        ->and($resposta->answered_at)->not->toBeNull();

    // Zero marcado conta como respondido — é o que separa "avaliei e não
    // atingiu" de "ainda não transcrevi".
    expect(AssessmentLevel::where('assessment_id', $this->assessment->id)->sole()->answered_count)->toBe(1);
});

it('volta a pendente quando a pontuação é apagada', function () {
    $item = marcoLancamento('mando', 3);

    $this->save->handle($this->assessment->id, $item->id, 1.0);
    $this->save->handle($this->assessment->id, $item->id, null);

    expect(Response::where('item_id', $item->id)->exists())->toBeFalse()
        ->and(AssessmentLevel::where('assessment_id', $this->assessment->id)->sole()->answered_count)->toBe(0);
});

it('recalcula os totais do nível a cada clique', function () {
    $this->save->handle($this->assessment->id, marcoLancamento('mando', 1)->id, 1.0);
    $this->save->handle($this->assessment->id, marcoLancamento('mando', 2)->id, 0.5);
    $resultado = $this->save->handle($this->assessment->id, marcoLancamento('tato', 1)->id, 1.0);

    $nivel = AssessmentLevel::where('assessment_id', $this->assessment->id)->sole();

    expect($nivel->answered_count)->toBe(3)
        ->and((float) $nivel->score_total)->toBe(2.5)
        ->and($resultado->levelProgress->answered)->toBe(3)
        ->and($resultado->levelProgress->total)->toBe(45)
        ->and($resultado->levelScore)->toBe(2.5);
});

it('recusa meio ponto em marco que o manual não prevê', function () {
    // Ouvinte 2-M: threshold_half nulo. Ver regra 4 do CLAUDE.md.
    $item = Item::whereNull('threshold_half')->where('level', 1)->firstOrFail();

    $this->save->handle($this->assessment->id, $item->id, 0.5);
})->throws(InvalidArgumentException::class, 'não admite meio ponto');

it('aceita 1 e 0 no marco sem meio ponto', function () {
    $item = Item::whereNull('threshold_half')->where('level', 1)->firstOrFail();

    $this->save->handle($this->assessment->id, $item->id, 1.0);

    expect((float) Response::where('item_id', $item->id)->sole()->score)->toBe(1.0);
});

it('recusa pontuação fora de 0, ½ e 1', function () {
    $this->save->handle($this->assessment->id, marcoLancamento('mando', 1)->id, 0.75);
})->throws(InvalidArgumentException::class);

it('recusa marco de nível que não faz parte do lançamento', function () {
    // O lançamento abriu só o nível 1.
    $this->save->handle($this->assessment->id, marcoLancamento('mando', 7)->id, 1.0);
})->throws(RuntimeException::class, 'não faz parte deste lançamento');

it('recusa qualquer alteração depois de concluída', function () {
    $this->assessment->update(['locked_at' => now()]);

    $this->save->handle($this->assessment->id, marcoLancamento('mando', 1)->id, 1.0);
})->throws(RuntimeException::class, 'não pode ser alterada');
