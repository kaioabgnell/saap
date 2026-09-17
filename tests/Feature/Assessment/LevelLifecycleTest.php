<?php

declare(strict_types=1);

use App\Application\Assessment\CompleteLevel;
use App\Application\Assessment\RecalculateLevelTotals;
use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Application\Assessment\StartLevel;
use App\Domain\Assessment\AssessmentStatus;
use App\Domain\Assessment\LevelStatus;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use App\Models\Learner;
use App\Models\Response;
use App\Models\User;
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

    $this->start = app(StartLevel::class);
});

it('iniciar nível 1 cria assessment_levels com total_count 45', function () {
    $nivel = $this->start->handle($this->assessment, 1);

    expect($nivel->level)->toBe(1)
        ->and($nivel->total_count)->toBe(45)
        ->and($nivel->answered_count)->toBe(0)
        ->and($nivel->status)->toBe(LevelStatus::InProgress);
});

it('promove a avaliação a em andamento no primeiro nível', function () {
    expect($this->assessment->status)->toBe(AssessmentStatus::NotStarted);

    $this->start->handle($this->assessment, 1);

    expect($this->assessment->fresh()->status)->toBe(AssessmentStatus::InProgress)
        ->and($this->assessment->fresh()->started_at)->not->toBeNull();
});

it('permite iniciar pelo nível 2 sem o nível 1', function () {
    $nivel = $this->start->handle($this->assessment, 2);

    expect($nivel->level)->toBe(2)
        ->and($nivel->total_count)->toBe(60)
        ->and(AssessmentLevel::where('level', 1)->exists())->toBeFalse();
});

it('permite iniciar pelo nível 3', function () {
    expect($this->start->handle($this->assessment, 3)->total_count)->toBe(65);
});

it('não duplica o nível ao iniciar duas vezes', function () {
    $a = $this->start->handle($this->assessment, 1);
    $b = $this->start->handle($this->assessment, 1);

    expect($a->id)->toBe($b->id)->and(AssessmentLevel::count())->toBe(1);
});

it('recusa iniciar nível em avaliação travada', function () {
    $this->assessment->update(['locked_at' => now()]);

    $this->start->handle($this->assessment, 1);
})->throws(RuntimeException::class, 'concluída');

it('recusa concluir nível incompleto', function () {
    $nivel = $this->start->handle($this->assessment, 1);

    app(CompleteLevel::class)->handle($nivel);
})->throws(RuntimeException::class, '0 de 45');

it('conclui o nível quando todos os 45 marcos estão respondidos', function () {
    $nivel = $this->start->handle($this->assessment, 1);
    $save = app(SaveResponse::class);

    // Zero deliberado em todos: pontuar zero é resposta, não pendência.
    foreach (Item::where('level', 1)->get() as $item) {
        $save->handle(new SaveResponseCommand(
            assessmentId: $this->assessment->id,
            itemId: $item->id,
            explicitScore: 0.0,
        ));
    }

    $nivel->refresh();
    expect($nivel->answered_count)->toBe(45)->and($nivel->isComplete())->toBeTrue();

    $concluido = app(CompleteLevel::class)->handle($nivel);

    expect($concluido->status)->toBe(LevelStatus::Completed)
        ->and($concluido->completed_at)->not->toBeNull();
});

it('soma score_total corretamente ao concluir', function () {
    $nivel = $this->start->handle($this->assessment, 1);
    $save = app(SaveResponse::class);

    foreach (Item::where('level', 1)->get() as $i => $item) {
        $save->handle(new SaveResponseCommand(
            assessmentId: $this->assessment->id,
            itemId: $item->id,
            explicitScore: $i < 10 ? 1.0 : ($i < 20 ? 0.5 : 0.0),
        ));
    }

    expect($nivel->fresh()->score_total)->toBe(15.0); // 10×1 + 10×0,5
});

/**
 * Um nível concluído que perde respostas volta a "em andamento" — e a data de
 * conclusão volta junto. Se só o status voltasse, o laudo afirmaria
 * "concluído em 17/09" sobre um nível incompleto: BuildReportPayload lê
 * `completed_at` do nível direto para o campo `concluido_em`.
 */
it('devolve status e data de conclusão quando o nível perde respostas', function () {
    $nivel = $this->start->handle($this->assessment, 1);
    $save = app(SaveResponse::class);

    foreach (Item::where('level', 1)->get() as $item) {
        $save->handle(new SaveResponseCommand(
            assessmentId: $this->assessment->id,
            itemId: $item->id,
            explicitScore: 1.0,
        ));
    }

    $concluido = app(CompleteLevel::class)->handle($nivel->refresh());
    expect($concluido->status)->toBe(LevelStatus::Completed)
        ->and($concluido->completed_at)->not->toBeNull();

    // Uma resposta é desfeita (correção de dado, limpeza de lançamento errado).
    Response::where('assessment_id', $this->assessment->id)->first()->delete();
    app(RecalculateLevelTotals::class)->handle($concluido);

    $nivel = $concluido->refresh();

    expect($nivel->answered_count)->toBe(44)
        ->and($nivel->status)->toBe(LevelStatus::InProgress)
        ->and($nivel->completed_at)->toBeNull();
});

it('preserva a data de conclusão quando o nível segue completo', function () {
    $nivel = $this->start->handle($this->assessment, 1);
    $save = app(SaveResponse::class);

    foreach (Item::where('level', 1)->get() as $item) {
        $save->handle(new SaveResponseCommand(
            assessmentId: $this->assessment->id,
            itemId: $item->id,
            explicitScore: 1.0,
        ));
    }

    $concluido = app(CompleteLevel::class)->handle($nivel->refresh());
    $quando = $concluido->completed_at;

    // Recalcular sem perder resposta nenhuma não pode mexer na data.
    app(RecalculateLevelTotals::class)->handle($concluido);

    expect($concluido->refresh()->completed_at?->toIso8601String())
        ->toBe($quando?->toIso8601String());
});
