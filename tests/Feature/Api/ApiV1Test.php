<?php

declare(strict_types=1);

use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Application\Assessment\StartLevel;
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
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);

    $this->psicologo = User::factory()->create(['email' => 'ana@exemplo.test']);
    $this->learner = Learner::factory()->for($this->psicologo)->create(['name' => 'Kaleo']);
    $this->assessment = Assessment::factory()->for($this->learner)->for($this->psicologo)->create();
});

function marcoApi(string $areaCode, int $posicao): Item
{
    return Item::where('area_id', Area::where('code', $areaCode)->sole()->id)
        ->where('position', $posicao)->sole();
}

// --- autenticação ---

it('recusa toda rota sem token', function () {
    foreach ([
        route('api.me'),
        route('api.learners.index'),
        route('api.assessments.show', $this->assessment),
        route('api.catalog.level', 1),
    ] as $url) {
        $this->getJson($url)->assertStatus(401)->assertJson(['code' => 'unauthenticated']);
    }
});

it('faz login e devolve token pessoal', function () {
    $resposta = $this->postJson(route('api.auth.login'), [
        'email' => 'ana@exemplo.test',
        'password' => 'password',
        'device_name' => 'iPad da clínica',
    ]);

    $resposta->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
    expect($resposta->json('token'))->toBeString()->not->toBeEmpty();
});

it('recusa login com senha errada', function () {
    $this->postJson(route('api.auth.login'), [
        'email' => 'ana@exemplo.test', 'password' => 'errada', 'device_name' => 'iPad',
    ])->assertStatus(422)->assertJson(['code' => 'validation_failed']);
});

it('impede acesso a dados de outro usuário', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.learners.show', $this->learner))
        ->assertStatus(403)->assertJson(['code' => 'forbidden']);

    $this->getJson(route('api.assessments.show', $this->assessment))
        ->assertStatus(403)->assertJson(['code' => 'forbidden']);
});

it('lista só os aprendizes do usuário logado', function () {
    Learner::factory()->for(User::factory())->create(['name' => 'De outro psicólogo']);
    Sanctum::actingAs($this->psicologo);

    $resposta = $this->getJson(route('api.learners.index'))->assertOk();

    expect($resposta->json('data'))->toHaveCount(1)
        ->and($resposta->json('data.0.name'))->toBe('Kaleo');
});

// --- gravação idempotente ---

it('mantém idempotência no PUT de resposta', function () {
    Sanctum::actingAs($this->psicologo);
    app(StartLevel::class)->handle($this->assessment, 1);

    $item = marcoApi('mando', 2); // ½ com 3, 1 ponto com 4
    $corpo = ['entries' => [
        ['position' => 1, 'text_value' => 'bola'],
        ['position' => 2, 'text_value' => 'água'],
        ['position' => 3, 'text_value' => 'pipa'],
    ]];

    $url = route('api.assessments.responses.save', ['assessment' => $this->assessment, 'itemId' => $item->id]);

    $primeira = $this->putJson($url, $corpo)->assertOk();
    $segunda = $this->putJson($url, $corpo)->assertOk();

    expect($primeira->json('response.score'))->toBe(0.5)
        ->and($segunda->json('response.score'))->toBe(0.5)
        ->and(Response::count())->toBe(1)
        ->and(ResponseEntry::count())->toBe(3);
});

it('devolve progresso recalculado a cada gravação', function () {
    Sanctum::actingAs($this->psicologo);
    app(StartLevel::class)->handle($this->assessment, 1);

    $resposta = $this->putJson(
        route('api.assessments.responses.save', ['assessment' => $this->assessment, 'itemId' => marcoApi('mando', 1)->id]),
        ['override' => ['score' => 1]],
    )->assertOk();

    expect($resposta->json('progress.area'))->toBe(['answered' => 1, 'total' => 5])
        ->and($resposta->json('progress.level'))->toBe(['answered' => 1, 'total' => 45])
        ->and($resposta->json('progress.assessment'))->toBe(['answered' => 1, 'total' => 45]);
});

it('recusa gravação em avaliação travada', function () {
    Sanctum::actingAs($this->psicologo);
    app(StartLevel::class)->handle($this->assessment, 1);
    $this->assessment->update(['locked_at' => now()]);

    $this->putJson(
        route('api.assessments.responses.save', ['assessment' => $this->assessment, 'itemId' => marcoApi('mando', 1)->id]),
        ['entries' => [['position' => 1, 'text_value' => 'bola']]],
    )->assertStatus(403)->assertJson(['code' => 'forbidden']);
});

it('recusa gravação em nível não iniciado com código estável', function () {
    Sanctum::actingAs($this->psicologo);

    $this->putJson(
        route('api.assessments.responses.save', ['assessment' => $this->assessment, 'itemId' => marcoApi('mando', 1)->id]),
        ['entries' => [['position' => 1, 'text_value' => 'bola']]],
    )->assertStatus(409)->assertJson(['code' => 'level_not_started']);
});

// --- catálogo com ETag ---

it('devolve 304 com ETag atualizado', function () {
    Sanctum::actingAs($this->psicologo);

    $primeira = $this->getJson(route('api.catalog.level', 1))->assertOk();
    $etag = $primeira->headers->get('ETag');

    expect($etag)->not->toBeNull()
        ->and($primeira->headers->get('Cache-Control'))->toContain('private');

    $this->withHeaders(['If-None-Match' => $etag])
        ->getJson(route('api.catalog.level', 1))
        ->assertStatus(304);
});

it('devolve o catálogo do nível com áreas e marcos', function () {
    Sanctum::actingAs($this->psicologo);

    $resposta = $this->getJson(route('api.catalog.level', 1))->assertOk();

    expect($resposta->json('areas'))->toHaveCount(9)
        ->and($resposta->json('areas.0.items'))->toHaveCount(5)
        ->and($resposta->json('areas.0.items.0'))->toHaveKeys([
            'id', 'code', 'statement', 'criteria_full', 'response_type',
            'threshold_full', 'threshold_half', 'scoring_mode',
        ]);
});

it('recusa nível inválido no catálogo', function () {
    Sanctum::actingAs($this->psicologo);

    $this->getJson(route('api.catalog.level', 9))->assertStatus(404)->assertJson(['code' => 'not_found']);
});

// --- ciclo completo ---

it('percorre o ciclo completo pela API', function () {
    Sanctum::actingAs($this->psicologo);

    $outroAprendiz = Learner::factory()->for($this->psicologo)->create(['name' => 'Bia']);

    // JsonResource devolve 201 quando o modelo foi criado nesta requisição.
    $criada = $this->postJson(route('api.assessments.store'), [
        'learner_id' => $outroAprendiz->id,
        'applied_on' => '2026-07-08',
    ])->assertCreated();

    $id = $criada->json('data.id');
    expect($criada->json('data.status'))->toBe('not_started');

    $this->postJson(route('api.assessments.levels.start', ['assessment' => $id, 'level' => 1]))->assertOk();

    $itens = $this->getJson(route('api.assessments.levels.items', ['assessment' => $id, 'level' => 1]))->assertOk();
    expect($itens->json('areas'))->toHaveCount(9);

    foreach (Item::where('level', 1)->get() as $item) {
        $this->putJson(
            route('api.assessments.responses.save', ['assessment' => $id, 'itemId' => $item->id]),
            ['override' => ['score' => 1]],
        )->assertOk();
    }

    $this->postJson(route('api.assessments.levels.complete', ['assessment' => $id, 'level' => 1]))->assertOk();

    $concluida = $this->postJson(route('api.assessments.complete', $id))->assertOk();
    expect($concluida->json('assessment.locked'))->toBeTrue()
        ->and($concluida->json('report.content_hash'))->toBeString();

    $relatorio = $this->getJson(route('api.assessments.report', $id))->assertOk();
    expect($relatorio->json('payload.total.pontuacao'))->toBe(45)
        ->and($relatorio->json('content_hash'))->toBe($concluida->json('report.content_hash'));
});

it('impede concluir avaliação com nível incompleto, com código estável', function () {
    Sanctum::actingAs($this->psicologo);
    app(StartLevel::class)->handle($this->assessment, 1);

    $this->postJson(route('api.assessments.complete', $this->assessment))
        ->assertStatus(409)->assertJson(['code' => 'level_incomplete']);
});

it('cancela pela API exigindo motivo', function () {
    Sanctum::actingAs($this->psicologo);

    $this->postJson(route('api.assessments.cancel', $this->assessment), [])
        ->assertStatus(422)->assertJson(['code' => 'validation_failed']);

    $this->postJson(route('api.assessments.cancel', $this->assessment), ['reason' => 'aprendiz desligado'])
        ->assertOk()->assertJsonPath('data.status', 'cancelled');
});

// --- o teste que mais importa: web e API concordam ---

it('produz o mesmo score pela web e pela API', function () {
    app(StartLevel::class)->handle($this->assessment, 1);
    $item = marcoApi('mando', 2); // ½ com 3, 1 ponto com 4

    // Caminho web: o caso de uso direto, como o ItemCard chama.
    $viaWeb = app(SaveResponse::class)->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id,
        itemId: $item->id,
        entries: [
            ['position' => 1, 'text_value' => 'bola'],
            ['position' => 2, 'text_value' => 'água'],
            ['position' => 3, 'text_value' => 'pipa'],
        ],
    ));

    // Mesma entrada, outra avaliação, agora pela API.
    $outra = Assessment::factory()
        ->for(Learner::factory()->for($this->psicologo))
        ->for($this->psicologo)->create();
    app(StartLevel::class)->handle($outra, 1);

    Sanctum::actingAs($this->psicologo);
    $viaApi = $this->putJson(
        route('api.assessments.responses.save', ['assessment' => $outra, 'itemId' => $item->id]),
        ['entries' => [
            ['position' => 1, 'text_value' => 'bola'],
            ['position' => 2, 'text_value' => 'água'],
            ['position' => 3, 'text_value' => 'pipa'],
        ]],
    )->assertOk();

    expect($viaApi->json('response.score'))->toBe($viaWeb->score->toFloat())
        ->and($viaApi->json('response.tally'))->toBe($viaWeb->tally)
        ->and($viaApi->json('response.answered'))->toBe($viaWeb->isAnswered);
});
