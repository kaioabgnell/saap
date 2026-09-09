<?php

declare(strict_types=1);

use App\Application\Assessment\OpenChartAssessment;
use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Application\Assessment\StartLevel;
use App\Livewire\Assessment\ChartEntry;
use App\Livewire\Assessment\LevelBoard;
use App\Models\Assessment;
use App\Models\Learner;
use App\Models\User;
use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\Item;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
 * O risco mais sério da F10, e o mais silencioso: a tela do nível gravaria uma
 * transcrição com zero exemplares, o ScoreCalculator devolveria 0 e o marco
 * lançado como 1 ponto viraria 0 — sem erro nenhum, porque para o fluxo
 * guiado nada de errado teria acontecido.
 *
 * Por isso a trava é testada nas três camadas onde ela existe.
 */

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);

    $this->psicologo = User::factory()->create();
    $this->learner = Learner::factory()->for($this->psicologo)->create();

    $this->transcricao = app(OpenChartAssessment::class)->handle(
        $this->learner, $this->psicologo, '2025-03-12', [1],
    );

    $this->guiada = Assessment::factory()->for($this->learner)->for($this->psicologo)->create();
    app(StartLevel::class)->handle($this->guiada, 1);

    $this->actingAs($this->psicologo);
});

function marcoModo(string $areaCode, int $posicao): Item
{
    return Item::where('area_id', Area::where('code', $areaCode)->sole()->id)
        ->where('position', $posicao)->sole();
}

it('nega a tela do nível para uma transcrição', function () {
    Livewire::test(LevelBoard::class, ['assessment' => $this->transcricao, 'level' => 1])
        ->assertForbidden();
});

it('nega a tela de lançamento para uma avaliação guiada', function () {
    Livewire::test(ChartEntry::class, ['assessment' => $this->guiada])
        ->assertForbidden();
});

it('nega o PUT de resposta da API para uma transcrição', function () {
    Sanctum::actingAs($this->psicologo);

    $this->putJson(route('api.assessments.responses.save', [
        'assessment' => $this->transcricao->id,
        'itemId' => marcoModo('mando', 1)->id,
    ]), ['entries' => []])->assertForbidden();
});

it('mantém o PUT da API funcionando na avaliação guiada', function () {
    Sanctum::actingAs($this->psicologo);

    $this->putJson(route('api.assessments.responses.save', [
        'assessment' => $this->guiada->id,
        'itemId' => marcoModo('mando', 1)->id,
    ]), ['entries' => []])->assertOk();
});

it('recusa a gravação guiada mesmo se a policy for contornada', function () {
    // A última camada: um chamador novo pode esquecer de autorizar, mas não
    // consegue passar por aqui.
    app(SaveResponse::class)->handle(new SaveResponseCommand(
        assessmentId: $this->transcricao->id,
        itemId: marcoModo('mando', 1)->id,
        explicitScore: 1.0,
    ));
})->throws(RuntimeException::class, 'gráfico de lançamento');

it('nega a escrita na grade de quem não é dono da avaliação', function () {
    $intruso = User::factory()->create();
    $this->actingAs($intruso);

    Livewire::test(ChartEntry::class, ['assessment' => $this->transcricao])
        ->assertForbidden();
});

it('nega a escrita na grade depois de concluída', function () {
    // A tela redireciona para o laudo, mas a ação avulsa — reenvio de um
    // clique pendente, aba velha — tem de bater na policy.
    $tela = Livewire::test(ChartEntry::class, ['assessment' => $this->transcricao]);

    $this->transcricao->update(['locked_at' => now()]);

    $tela->call('marcar', marcoModo('mando', 1)->id, 'top')->assertForbidden();
});

it('não oferece a lista de níveis na tela de uma transcrição', function () {
    $this->get(route('avaliacoes.show', $this->transcricao))
        ->assertOk()
        ->assertSee('Continuar lançamento')
        ->assertDontSee(route('avaliacoes.nivel', ['assessment' => $this->transcricao, 'level' => 1]));
});
