<?php

declare(strict_types=1);

use App\Application\Assessment\OpenAssessment;
use App\Application\Assessment\OpenChartAssessment;
use App\Domain\Assessment\AssessmentAlreadyOpenException;
use App\Domain\Assessment\AssessmentStatus;
use App\Domain\Assessment\EntryMode;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use App\Models\Learner;
use App\Models\User;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);

    $this->psicologo = User::factory()->create();
    $this->learner = Learner::factory()->for($this->psicologo)->create([
        'name' => 'Kaleo', 'birth_date' => '2020-01-10',
    ]);

    $this->actingAs($this->psicologo);
});

it('aceita anos de papel do mesmo aprendiz', function () {
    // O caso que motivou a fase: uma pasta com três formulários arquivados.
    foreach (['2023-04-05', '2024-04-10', '2025-04-02'] as $data) {
        app(OpenChartAssessment::class)->handle($this->learner, $this->psicologo, $data, [1]);
    }

    expect(Assessment::where('learner_id', $this->learner->id)->count())->toBe(3);
});

it('não deixa uma transcrição aberta bloquear a aplicação de hoje', function () {
    app(OpenChartAssessment::class)->handle($this->learner, $this->psicologo, '2024-04-10', [1]);

    $guiada = app(OpenAssessment::class)->handle($this->learner, $this->psicologo);

    expect($guiada->entry_mode)->toBe(EntryMode::Guided)
        ->and($guiada->status)->toBe(AssessmentStatus::NotStarted);
});

it('não deixa a aplicação de hoje bloquear o arquivo do papel', function () {
    app(OpenAssessment::class)->handle($this->learner, $this->psicologo);

    $transcricao = app(OpenChartAssessment::class)->handle(
        $this->learner, $this->psicologo, '2024-04-10', [1],
    );

    expect($transcricao->entry_mode)->toBe(EntryMode::Chart);
});

it('mantém uma aplicação guiada aberta por vez', function () {
    app(OpenAssessment::class)->handle($this->learner, $this->psicologo);
    app(OpenAssessment::class)->handle($this->learner, $this->psicologo);
})->throws(AssessmentAlreadyOpenException::class);

it('abre só os níveis escolhidos', function () {
    $assessment = app(OpenChartAssessment::class)->handle(
        $this->learner, $this->psicologo, '2024-04-10', [1, 3],
    );

    $niveis = AssessmentLevel::where('assessment_id', $assessment->id)->pluck('total_count', 'level');

    expect($niveis->keys()->all())->toBe([1, 3])
        ->and($niveis[1])->toBe(45)
        ->and($niveis[3])->toBe(65);
});

it('exige ao menos um nível', function () {
    app(OpenChartAssessment::class)->handle($this->learner, $this->psicologo, '2024-04-10', []);
})->throws(InvalidArgumentException::class, 'ao menos um nível');

// --- formulário de abertura ---

it('abre o lançamento pelo formulário', function () {
    $this->post(route('lancamento.store', $this->learner), [
        'applied_on' => '2024-04-10',
        'levels' => [1],
        'observations' => 'Transcrito da pasta da família.',
    ])->assertRedirect();

    $assessment = Assessment::where('learner_id', $this->learner->id)->sole();

    expect($assessment->entry_mode)->toBe(EntryMode::Chart)
        ->and($assessment->applied_on->toDateString())->toBe('2024-04-10')
        ->and($assessment->observations)->toBe('Transcrito da pasta da família.');
});

it('recusa aplicação em papel com data futura', function () {
    $this->post(route('lancamento.store', $this->learner), [
        'applied_on' => now()->addDay()->toDateString(),
        'levels' => [1],
    ])->assertSessionHasErrors('applied_on');
});

it('recusa aplicação anterior ao nascimento', function () {
    $this->post(route('lancamento.store', $this->learner), [
        'applied_on' => '2019-01-01',
        'levels' => [1],
    ])->assertSessionHasErrors('applied_on');
});

it('recusa lançamento sem nível', function () {
    $this->post(route('lancamento.store', $this->learner), [
        'applied_on' => '2024-04-10',
    ])->assertSessionHasErrors('levels');
});

it('avisa antes de repetir a mesma data, sem bloquear', function () {
    app(OpenChartAssessment::class)->handle($this->learner, $this->psicologo, '2024-04-10', [1]);

    $this->post(route('lancamento.store', $this->learner), [
        'applied_on' => '2024-04-10',
        'levels' => [1],
    ])->assertSessionHas('aviso');

    expect(Assessment::where('learner_id', $this->learner->id)->count())->toBe(1);

    // Confirmado, passa.
    $this->post(route('lancamento.store', $this->learner), [
        'applied_on' => '2024-04-10',
        'levels' => [1],
        'confirmado' => 1,
    ])->assertRedirect();

    expect(Assessment::where('learner_id', $this->learner->id)->count())->toBe(2);
});

it('nega abrir lançamento para aprendiz de outro psicólogo', function () {
    $outro = Learner::factory()->for(User::factory())->create();

    $this->get(route('lancamento.create', $outro))->assertForbidden();
});
