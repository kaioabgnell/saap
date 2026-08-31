<?php

declare(strict_types=1);

use App\Application\Assessment\OpenAssessment;
use App\Domain\Assessment\AssessmentAlreadyOpenException;
use App\Domain\Assessment\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Learner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('abre avaliação com applied_on e status not_started', function () {
    $psicologo = User::factory()->create();
    $learner = Learner::factory()->for($psicologo)->create();

    $response = $this->actingAs($psicologo)->post(route('avaliacoes.store', $learner), [
        'applied_on' => '2026-07-08',
    ]);

    $assessment = Assessment::sole();

    $response->assertRedirect(route('avaliacoes.show', $assessment));
    expect($assessment->status)->toBe(AssessmentStatus::NotStarted)
        ->and($assessment->applied_on->toDateString())->toBe('2026-07-08')
        ->and($assessment->user_id)->toBe($psicologo->id)
        ->and($assessment->instrument)->toBe('vbmapp');
});

it('usa hoje como data padrão quando não informada', function () {
    $psicologo = User::factory()->create();
    $learner = Learner::factory()->for($psicologo)->create();

    $this->actingAs($psicologo)->post(route('avaliacoes.store', $learner));

    expect(Assessment::sole()->applied_on->toDateString())->toBe(now()->toDateString());
});

it('impede duas avaliações em andamento para o mesmo aprendiz', function () {
    $psicologo = User::factory()->create();
    $learner = Learner::factory()->for($psicologo)->create();
    Assessment::factory()->inProgress()->for($learner)->for($psicologo)->create();

    $response = $this->actingAs($psicologo)->post(route('avaliacoes.store', $learner));

    $response->assertRedirect(route('aprendizes.show', $learner));
    expect(Assessment::count())->toBe(1);
});

it('impede também abrir uma segunda avaliação não iniciada', function () {
    // Generalização deliberada de "impede duas in_progress": duas "não
    // iniciadas" abertas ao mesmo tempo para o mesmo aprendiz também não faz
    // sentido — ver App\Application\Assessment\OpenAssessment.
    $psicologo = User::factory()->create();
    $learner = Learner::factory()->for($psicologo)->create();
    Assessment::factory()->for($learner)->for($psicologo)->create(); // not_started, por padrão da factory

    expect(fn () => app(OpenAssessment::class)->handle($learner, $psicologo))
        ->toThrow(AssessmentAlreadyOpenException::class);
});

it('permite nova avaliação depois que a anterior foi concluída', function () {
    $psicologo = User::factory()->create();
    $learner = Learner::factory()->for($psicologo)->create();
    Assessment::factory()->completed()->for($learner)->for($psicologo)->create();

    $response = $this->actingAs($psicologo)->post(route('avaliacoes.store', $learner));

    $response->assertRedirect();
    expect(Assessment::count())->toBe(2);
});

it('impede abrir avaliação para aprendiz de outro psicólogo', function () {
    $dono = User::factory()->create();
    $outro = User::factory()->create();
    $learner = Learner::factory()->for($dono)->create();

    $this->actingAs($outro)->post(route('avaliacoes.store', $learner))->assertForbidden();
});

it('recusa acesso ao detalhe de avaliação de outro psicólogo', function () {
    $dono = User::factory()->create();
    $outro = User::factory()->create();
    $learner = Learner::factory()->for($dono)->create();
    $assessment = Assessment::factory()->for($learner)->for($dono)->create();

    $this->actingAs($outro)->get(route('avaliacoes.show', $assessment))->assertForbidden();
});
