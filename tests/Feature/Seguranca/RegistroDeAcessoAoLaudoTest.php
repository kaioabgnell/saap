<?php

declare(strict_types=1);

use App\Application\Assessment\CompleteAssessment;
use App\Application\Assessment\CompleteLevel;
use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Application\Assessment\StartLevel;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use App\Models\Learner;
use App\Models\ReportAccessLog;
use App\Models\User;
use App\Models\Vbmapp\Item;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);

    $this->psicologa = User::factory()->create();
    $learner = Learner::factory()->for($this->psicologa)->create();
    $this->assessment = Assessment::factory()->for($learner)->for($this->psicologa)->create();

    app(StartLevel::class)->handle($this->assessment, 1);
    $save = app(SaveResponse::class);
    foreach (Item::where('level', 1)->get() as $item) {
        $save->handle(new SaveResponseCommand(
            assessmentId: $this->assessment->id, itemId: $item->id, explicitScore: 1.0,
        ));
    }
    app(CompleteLevel::class)->handle(
        AssessmentLevel::where('assessment_id', $this->assessment->id)->where('level', 1)->sole()
    );
    $this->snapshot = app(CompleteAssessment::class)->handle($this->assessment->refresh());

    $this->actingAs($this->psicologa);
});

it('registra quem leu o laudo na tela', function () {
    $this->get(route('avaliacoes.relatorio', $this->assessment))->assertOk();

    $registro = ReportAccessLog::sole();

    expect($registro->report_snapshot_id)->toBe($this->snapshot->id)
        ->and($registro->user_id)->toBe($this->psicologa->id)
        ->and($registro->action)->toBe(ReportAccessLog::NA_TELA)
        ->and($registro->ip_address)->not->toBeNull()
        ->and($registro->created_at)->not->toBeNull();
});

it('registra o download do PDF separadamente da leitura', function () {
    Storage::disk('local')->put($this->snapshot->pdf_path ?? 'relatorios/x.pdf', '%PDF-1.4');
    $this->snapshot->forceFill(['pdf_path' => $this->snapshot->pdf_path ?? 'relatorios/x.pdf'])->save();

    $this->get(route('avaliacoes.relatorio', $this->assessment))->assertOk();
    $this->get(route('avaliacoes.relatorio.pdf', $this->assessment))->assertOk();

    expect(ReportAccessLog::pluck('action')->all())
        ->toBe([ReportAccessLog::NA_TELA, ReportAccessLog::DOWNLOAD]);
});

it('registra também a leitura pela API', function () {
    $token = $this->psicologa->createToken('teste')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/assessments/{$this->assessment->id}/report")
        ->assertOk();

    expect(ReportAccessLog::sole()->action)->toBe(ReportAccessLog::PELA_API);
});

it('não registra tentativa de acesso de outro psicólogo', function () {
    // O 403 vem da policy, antes de haver acesso: o registro é de leitura de
    // laudo, não de tentativa barrada.
    $this->actingAs(User::factory()->create())
        ->get(route('avaliacoes.relatorio', $this->assessment))
        ->assertForbidden();

    expect(ReportAccessLog::count())->toBe(0);
});

it('não registra 404 de laudo inexistente', function () {
    $outra = Assessment::factory()
        ->for(Learner::factory()->for($this->psicologa))
        ->for($this->psicologa)->create();

    $this->get(route('avaliacoes.relatorio', $outra))->assertNotFound();

    expect(ReportAccessLog::count())->toBe(0);
});
