<?php

declare(strict_types=1);

use App\Application\Schedule\DetectConflicts;
use App\Application\Schedule\RescheduleAppointment;
use App\Application\Schedule\ScheduleAppointment;
use App\Application\Schedule\ScheduleAppointmentCommand;
use App\Domain\Schedule\TimeSlot;
use App\Models\Appointment;
use App\Models\Learner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->psicologo = User::factory()->create();
    $this->kaleo = Learner::factory()->for($this->psicologo)->create(['name' => 'Kaleo']);
    $this->irma = Learner::factory()->for($this->psicologo)->create(['name' => 'Alice']);
});

function marcar(Learner $learner, string $quando, int $minutos = 60, bool $permitir = false)
{
    return app(ScheduleAppointment::class)->handle(new ScheduleAppointmentCommand(
        userId: $learner->user_id,
        learnerId: $learner->id,
        startsAt: $quando,
        durationMinutes: $minutos,
        permitirConflito: $permitir,
    ));
}

it('avisa do conflito sem gravar, e diz quem já está no horário', function () {
    marcar($this->kaleo, '2026-09-10 15:00');

    $resultado = marcar($this->irma, '2026-09-10 15:30');

    expect($resultado->precisaConfirmacao())->toBeTrue()
        ->and($resultado->criados)->toBeEmpty()
        ->and($resultado->conflitos)->toHaveCount(1)
        ->and($resultado->conflitos[0]->nomes())->toBe('Kaleo')
        ->and($resultado->conflitos[0]->quando())->toBe('10/09/2026 às 15:30')
        ->and(Appointment::count())->toBe(1);
});

it('grava os dois quando a psicóloga confirma', function () {
    marcar($this->kaleo, '2026-09-10 15:00');

    $resultado = marcar($this->irma, '2026-09-10 15:00', permitir: true);

    expect($resultado->precisaConfirmacao())->toBeFalse()
        ->and(Appointment::count())->toBe(2);
});

/** O caso que justifica a desigualdade estrita — ver TimeSlotTest. */
it('não considera conflito quando um horário encosta no outro', function () {
    marcar($this->kaleo, '2026-09-10 15:00', 60);

    $resultado = marcar($this->irma, '2026-09-10 16:00', 60);

    expect($resultado->precisaConfirmacao())->toBeFalse()
        ->and(Appointment::count())->toBe(2);
});

it('não conflita com atendimento cancelado', function () {
    Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-10 15:00')->cancelado()->create();

    expect(marcar($this->irma, '2026-09-10 15:00')->precisaConfirmacao())->toBeFalse();
});

it('não conflita com falta nem com atendimento concluído', function (string $estado) {
    Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-10 15:00')->{$estado}()->create();

    expect(marcar($this->irma, '2026-09-10 15:00')->precisaConfirmacao())->toBeFalse();
})->with(['falta', 'concluido']);

it('conflita com atendimento em andamento', function () {
    Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-10 15:00')->emAtendimento()->create();

    expect(marcar($this->irma, '2026-09-10 15:00')->precisaConfirmacao())->toBeTrue();
});

it('não conflita com a agenda de outro psicólogo', function () {
    $outro = User::factory()->create();
    $dele = Learner::factory()->for($outro)->create();

    Appointment::factory()->for($outro)->for($dele)->em('2026-09-10 15:00')->create();

    expect(marcar($this->kaleo, '2026-09-10 15:00')->precisaConfirmacao())->toBeFalse();
});

it('lista todas as datas em conflito de uma vez, e uma confirmação cobre o lote', function () {
    // Duas quintas ocupadas dentro de um lote de oito.
    Appointment::factory()->for($this->psicologo)->for($this->irma)->em('2026-09-24 15:00')->create();
    Appointment::factory()->for($this->psicologo)->for($this->irma)->em('2026-10-15 15:00')->create();

    $aviso = app(ScheduleAppointment::class)->handle(new ScheduleAppointmentCommand(
        userId: $this->psicologo->id,
        learnerId: $this->kaleo->id,
        startsAt: '2026-09-10 15:00',
        repeatWeeks: 8,
    ));

    expect($aviso->precisaConfirmacao())->toBeTrue()
        ->and($aviso->conflitos)->toHaveCount(2)
        ->and($aviso->conflitos[0]->quando())->toBe('24/09/2026 às 15:00')
        ->and($aviso->conflitos[1]->quando())->toBe('15/10/2026 às 15:00');

    $gravado = app(ScheduleAppointment::class)->handle(new ScheduleAppointmentCommand(
        userId: $this->psicologo->id,
        learnerId: $this->kaleo->id,
        startsAt: '2026-09-10 15:00',
        repeatWeeks: 8,
        permitirConflito: true,
    ));

    expect($gravado->criados)->toHaveCount(8);
});

/**
 * O conflito é revalidado no servidor: duas telas abertas ao mesmo tempo não
 * criam sobreposição silenciosa. A segunda tela calculou seu alerta quando o
 * horário estava livre — quem decide é a checagem de dentro da transação.
 */
it('revalida no servidor, e não confia no alerta calculado antes', function () {
    $faixa = new TimeSlot('2026-09-10 15:00', '2026-09-10 16:00');

    // A tela da Alice abriu com o horário livre: nada a avisar.
    expect(app(DetectConflicts::class)->para($this->psicologo->id, [$faixa]))->toBeEmpty();

    // Enquanto ela estava aberta, outra tela marcou o Kaleo no mesmo horário.
    marcar($this->kaleo, '2026-09-10 15:00');

    // O salvamento da primeira tela ainda passa pela checagem do servidor.
    expect(marcar($this->irma, '2026-09-10 15:00')->precisaConfirmacao())->toBeTrue()
        ->and(Appointment::count())->toBe(1);
});

it('remarcar não conflita com o próprio agendamento', function () {
    $agendamento = Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-10 15:00')->create();

    $conflitos = app(RescheduleAppointment::class)->handle($agendamento, '2026-09-10 15:30', 60);

    expect($conflitos)->toBeEmpty()
        ->and($agendamento->refresh()->starts_at->format('H:i'))->toBe('15:30');
});

it('remarcar avisa do conflito com outro agendamento e não move', function () {
    $agendamento = Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-10 15:00')->create();
    Appointment::factory()->for($this->psicologo)->for($this->irma)
        ->em('2026-09-10 17:00')->create();

    $conflitos = app(RescheduleAppointment::class)->handle($agendamento, '2026-09-10 17:00', 60);

    expect($conflitos)->toHaveCount(1)
        ->and($conflitos[0]->nomes())->toBe('Alice')
        ->and($agendamento->refresh()->starts_at->format('H:i'))->toBe('15:00');
});

it('não remarca atendimento concluído', function () {
    $agendamento = Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-10 15:00')->concluido()->create();

    app(RescheduleAppointment::class)->handle($agendamento, '2026-09-11 15:00', 60);
})->throws(RuntimeException::class, 'não pode ser remarcado');
