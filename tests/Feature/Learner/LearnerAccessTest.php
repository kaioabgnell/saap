<?php

declare(strict_types=1);

use App\Models\Learner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('lista só os aprendizes do usuário logado', function () {
    $psicologo = User::factory()->create();
    $outro = User::factory()->create();

    Learner::factory()->for($psicologo)->create(['name' => 'Do psicólogo logado']);
    Learner::factory()->for($outro)->create(['name' => 'De outro psicólogo']);

    $response = $this->actingAs($psicologo)->get(route('aprendizes.index'));

    $response->assertOk()
        ->assertSee('Do psicólogo logado')
        ->assertDontSee('De outro psicólogo');
});

it('impede acesso a aprendiz de outro psicólogo', function () {
    $dono = User::factory()->create();
    $outro = User::factory()->create();
    $learner = Learner::factory()->for($dono)->create();

    $this->actingAs($outro)->get(route('aprendizes.show', $learner))->assertForbidden();
    $this->actingAs($outro)->get(route('aprendizes.edit', $learner))->assertForbidden();
    $this->actingAs($outro)->put(route('aprendizes.update', $learner), [
        'name' => 'Tentativa de invasão',
        'birth_date' => '2020-01-01',
    ])->assertForbidden();
    $this->actingAs($outro)->delete(route('aprendizes.destroy', $learner))->assertForbidden();
});

it('recusa acesso não autenticado', function () {
    $learner = Learner::factory()->create();

    $this->get(route('aprendizes.show', $learner))->assertRedirect(route('login'));
});

it('foto de aprendiz não é acessível por URL direta sem assinatura', function () {
    $dono = User::factory()->create();
    $learner = Learner::factory()->for($dono)->create(['photo_path' => 'aprendizes/1/foo.jpg']);

    $urlSemAssinatura = route('aprendizes.foto', ['learner' => $learner->id, 'tamanho' => 'padrao']);

    $this->actingAs($dono)->get($urlSemAssinatura)->assertForbidden();
});

it('recusa foto de aprendiz de outro psicólogo mesmo com assinatura válida', function () {
    $dono = User::factory()->create();
    $outro = User::factory()->create();
    $learner = Learner::factory()->for($dono)->create(['photo_path' => 'aprendizes/1/foo.jpg']);

    $url = URL::temporarySignedRoute(
        'aprendizes.foto',
        now()->addMinutes(10),
        ['learner' => $learner->id, 'tamanho' => 'padrao'],
    );

    $this->actingAs($outro)->get($url)->assertForbidden();
});
