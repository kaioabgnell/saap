<?php

declare(strict_types=1);

use App\Livewire\Agenda\AppointmentForm;
use App\Livewire\Agenda\Schedule;
use App\Livewire\Atendimento\SessionBoard;
use App\Models\Appointment;
use App\Models\Learner;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Um psicólogo só enxerga a própria agenda e o próprio prontuário. A regra é
 * repetida em toda ação, nunca inferida do contexto da rota.
 */
beforeEach(function () {
    $this->dono = User::factory()->create();
    $this->intruso = User::factory()->create();

    $this->aprendiz = Learner::factory()->for($this->dono)->create(['name' => 'Kaleo']);
    $this->atendimento = Appointment::factory()->for($this->dono)->for($this->aprendiz)
        ->em('2026-09-10 15:00')->create();
});

it('exige login em toda a agenda', function (string $rota, array $parametros) {
    $this->get(route($rota, $parametros))->assertRedirect(route('login'));
})->with(fn () => [
    ['agenda', []],
    ['atendimentos.show', ['appointment' => 1]],
    ['aprendizes.prontuario', ['learner' => 1]],
]);

it('nega a tela do atendimento de outro psicólogo', function () {
    $this->actingAs($this->intruso)
        ->get(route('atendimentos.show', $this->atendimento))
        ->assertForbidden();
});

it('nega o prontuário de aprendiz de outro psicólogo', function () {
    $this->actingAs($this->intruso)
        ->get(route('aprendizes.prontuario', $this->aprendiz))
        ->assertForbidden();
});

it('o dono acessa as duas telas', function () {
    $this->actingAs($this->dono);

    $this->get(route('atendimentos.show', $this->atendimento))->assertOk();
    $this->get(route('aprendizes.prontuario', $this->aprendiz))->assertOk();
    $this->get(route('agenda'))->assertOk();
});

it('não deixa agir sobre agendamento de outro psicólogo', function (string $acao) {
    $this->actingAs($this->intruso);

    Livewire::test(Schedule::class)->call($acao, $this->atendimento->id);
})->with(['checkIn', 'marcarFalta', 'cancelar', 'abrirLembrete'])
    ->throws(ModelNotFoundException::class);

it('não abre a remarcação de agendamento de outro psicólogo', function () {
    $this->actingAs($this->intruso);

    Livewire::test(AppointmentForm::class)->call('abrirRemarcacao', $this->atendimento->id);
})->throws(ModelNotFoundException::class);

it('não agenda para aprendiz de outro psicólogo', function () {
    $this->actingAs($this->intruso);

    Livewire::test(AppointmentForm::class)
        ->call('abrir', '2026-09-17', '15:00')
        // O id existe: o que barra é a posse, não a existência.
        ->set('learnerId', $this->aprendiz->id)
        ->call('salvar')
        ->assertSee('não está cadastrado nesta conta');

    expect(Appointment::count())->toBe(1);
});

it('a agenda de um não aparece na do outro', function () {
    Livewire::actingAs($this->intruso)->test(Schedule::class)
        ->set('ancora', '2026-09-10')
        ->assertDontSee('Kaleo');

    Livewire::actingAs($this->dono)->test(Schedule::class)
        ->set('ancora', '2026-09-10')
        ->assertSee('Kaleo');
});

/**
 * A policy é a trava, não o `if` da tela: atendimento concluído é terminal e
 * nem o dono altera.
 */
it('nem o dono altera atendimento concluído', function () {
    $concluido = Appointment::factory()->for($this->dono)->for($this->aprendiz)
        ->em('2026-09-01 15:00')->concluido()->create();

    expect($this->dono->can('update', $concluido))->toBeFalse()
        ->and($this->dono->can('view', $concluido))->toBeTrue()
        // O aditamento é justamente a saída que o fechamento deixa aberta.
        ->and($this->dono->can('addendum', $concluido))->toBeTrue()
        ->and($this->dono->can('delete', $concluido))->toBeFalse();

    Livewire::actingAs($this->dono)->test(SessionBoard::class, ['appointment' => $concluido])
        ->set('registro', 'Reescrita.')
        ->call('salvar');

    expect($concluido->refresh()->notes)->toBe('Sessão tranquila.');
});

it('só apaga agendamento que ainda não virou prontuário', function () {
    $limpo = Appointment::factory()->for($this->dono)->for($this->aprendiz)->create();
    $comRegistro = Appointment::factory()->for($this->dono)->for($this->aprendiz)
        ->create(['notes' => 'Já tem registro.']);

    expect($this->dono->can('delete', $limpo))->toBeTrue()
        ->and($this->dono->can('delete', $comRegistro))->toBeFalse();
});
