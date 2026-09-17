<?php

declare(strict_types=1);

use App\Application\Schedule\RescheduleAppointment;
use App\Application\Schedule\ScheduleAppointment;
use App\Application\Schedule\ScheduleAppointmentCommand;
use App\Application\Schedule\ScheduleAppointmentResult;
use App\Domain\Schedule\AppointmentStatus;
use App\Livewire\Agenda\AppointmentForm;
use App\Models\Appointment;
use App\Models\Learner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->psicologo = User::factory()->create();
    $this->kaleo = Learner::factory()->for($this->psicologo)->create(['name' => 'Kaleo']);
});

function agendar(array $sobrescreve = []): ScheduleAppointmentResult
{
    return app(ScheduleAppointment::class)->handle(new ScheduleAppointmentCommand(...[
        'userId' => test()->psicologo->id,
        'learnerId' => test()->kaleo->id,
        'startsAt' => '2026-09-10 15:00',
        ...$sobrescreve,
    ]));
}

it('cria um agendamento com a duração padrão de 50 minutos', function () {
    $resultado = agendar();

    expect($resultado->precisaConfirmacao())->toBeFalse()
        ->and($resultado->criados)->toHaveCount(1);

    $agendamento = Appointment::sole();

    expect($agendamento->starts_at->format('Y-m-d H:i'))->toBe('2026-09-10 15:00')
        ->and($agendamento->ends_at->format('H:i'))->toBe('15:50')
        ->and($agendamento->status)->toBe(AppointmentStatus::Scheduled)
        ->and($agendamento->learner_id)->toBe($this->kaleo->id);
});

it('guarda o fim, e não só a duração', function () {
    // ends_at é coluna: mudar a duração padrão da clínica no ano que vem não
    // pode reescrever o que já aconteceu.
    agendar(['durationMinutes' => 30]);

    expect(Appointment::sole()->ends_at->format('H:i'))->toBe('15:30');
});

it('separa o recado da marcação do registro clínico', function () {
    agendar(['bookingNote' => 'Vem com a avó.']);

    $agendamento = Appointment::sole();

    expect($agendamento->booking_note)->toBe('Vem com a avó.')
        ->and($agendamento->notes)->toBeNull();
});

it('repete por N semanas criando ocorrências independentes', function () {
    $resultado = agendar(['repeatWeeks' => 8]);

    expect($resultado->criados)->toHaveCount(8)
        ->and(Appointment::count())->toBe(8);

    $datas = Appointment::orderBy('starts_at')->pluck('starts_at')
        ->map(fn ($d) => $d->format('Y-m-d H:i'))->all();

    expect($datas[0])->toBe('2026-09-10 15:00')
        ->and($datas[7])->toBe('2026-10-29 15:00');

    // Toda ocorrência cai na mesma quinta-feira, no mesmo horário.
    foreach (Appointment::all() as $a) {
        expect($a->starts_at->dayOfWeek)->toBe(4)
            ->and($a->starts_at->format('H:i'))->toBe('15:00');
    }
});

it('não guarda identificador de série: remarcar uma não afeta as outras', function () {
    agendar(['repeatWeeks' => 4]);

    $segunda = Appointment::orderBy('starts_at')->skip(1)->first();

    app(RescheduleAppointment::class)
        ->handle($segunda, '2026-09-18 09:00', 50);

    $datas = Appointment::orderBy('starts_at')->pluck('starts_at')
        ->map(fn ($d) => $d->format('Y-m-d H:i'))->all();

    expect($datas)->toBe([
        '2026-09-10 15:00',
        '2026-09-18 09:00', // só esta mudou
        '2026-09-24 15:00',
        '2026-10-01 15:00',
    ]);
});

it('recusa repetição fora de 1 a 52 semanas', function (int $semanas) {
    agendar(['repeatWeeks' => $semanas]);
})->with([0, -1, 53])->throws(RuntimeException::class);

it('só aceita aprendiz cadastrado nesta conta', function () {
    $deOutro = Learner::factory()->create();

    agendar(['learnerId' => $deOutro->id]);
})->throws(RuntimeException::class, 'não está cadastrado nesta conta');

it('não grava nada quando o aprendiz é de outra conta', function () {
    $deOutro = Learner::factory()->create();

    try {
        agendar(['learnerId' => $deOutro->id, 'repeatWeeks' => 5]);
    } catch (RuntimeException) {
        // esperado
    }

    expect(Appointment::count())->toBe(0);
});

// --------------------------------------------------------------- pelo modal

it('agenda pelo modal da agenda', function () {
    $this->actingAs($this->psicologo);

    Livewire::test(AppointmentForm::class)
        ->call('abrir', '2026-09-17', '15:00')
        ->assertSet('aberto', true)
        ->assertSet('duracao', 50)
        ->assertSet('repetir', 1)
        ->call('selecionar', $this->kaleo->id)
        ->set('recado', 'Vem com a avó.')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertSet('aberto', false)
        ->assertDispatched('agenda-mudou', data: '2026-09-17');

    $agendamento = Appointment::sole();

    expect($agendamento->starts_at->format('Y-m-d H:i'))->toBe('2026-09-17 15:00')
        ->and($agendamento->booking_note)->toBe('Vem com a avó.');
});

it('o modal mostra o conflito e só grava depois de confirmar', function () {
    $this->actingAs($this->psicologo);

    $alice = Learner::factory()->for($this->psicologo)->create(['name' => 'Alice']);
    Appointment::factory()->for($this->psicologo)->for($alice)->em('2026-09-17 15:00')->create();

    $modal = Livewire::test(AppointmentForm::class)
        ->call('abrir', '2026-09-17', '15:00')
        ->call('selecionar', $this->kaleo->id)
        ->call('salvar')
        // Continua aberto, mostrando quem já está lá.
        ->assertSet('aberto', true)
        ->assertSee('Já há atendimento neste horário')
        ->assertSee('17/09/2026 às 15:00')
        ->assertSee('Alice')
        ->assertSee('Marcar mesmo assim');

    expect(Appointment::count())->toBe(1);

    $modal->call('salvar', true)->assertSet('aberto', false);

    expect(Appointment::count())->toBe(2);
});

it('mudar o horário derruba o alerta já mostrado', function () {
    $this->actingAs($this->psicologo);

    $alice = Learner::factory()->for($this->psicologo)->create(['name' => 'Alice']);
    Appointment::factory()->for($this->psicologo)->for($alice)->em('2026-09-17 15:00')->create();

    Livewire::test(AppointmentForm::class)
        ->call('abrir', '2026-09-17', '15:00')
        ->call('selecionar', $this->kaleo->id)
        ->call('salvar')
        ->assertSee('Marcar mesmo assim')
        // O alerta era sobre 15h. Às 17h a pergunta é outra.
        ->set('hora', '17:00')
        ->assertSet('conflitos', [])
        ->assertDontSee('Marcar mesmo assim');
});

it('o modal exige aprendiz, data e hora', function () {
    $this->actingAs($this->psicologo);

    Livewire::test(AppointmentForm::class)
        ->call('abrir')
        ->set('hora', '')
        ->call('salvar')
        ->assertHasErrors(['learnerId', 'hora']);

    expect(Appointment::count())->toBe(0);
});

it('remarcar pelo modal move só aquele atendimento', function () {
    $this->actingAs($this->psicologo);

    agendar(['repeatWeeks' => 3]);
    $segunda = Appointment::orderBy('starts_at')->skip(1)->first();

    Livewire::test(AppointmentForm::class)
        ->call('abrirRemarcacao', $segunda->id)
        ->assertSet('learnerId', $this->kaleo->id)
        ->assertSet('data', '2026-09-17')
        ->assertSet('hora', '15:00')
        // Não se repete uma remarcação: o campo nem aparece.
        ->assertDontSee('Repetir por')
        ->set('hora', '09:00')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(Appointment::orderBy('starts_at')->pluck('starts_at')
        ->map(fn ($d) => $d->format('Y-m-d H:i'))->all())
        ->toBe(['2026-09-10 15:00', '2026-09-17 09:00', '2026-09-24 15:00']);
});
