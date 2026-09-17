<?php

declare(strict_types=1);

use App\Domain\Schedule\AppointmentStatus;
use App\Livewire\Agenda\Schedule;
use App\Livewire\Atendimento\SessionBoard;
use App\Models\Appointment;
use App\Models\AppointmentAddendum;
use App\Models\Learner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-09-10 15:00');
    $this->psicologo = User::factory()->create();
    $this->kaleo = Learner::factory()->for($this->psicologo)->create(['name' => 'Kaleo']);
    $this->actingAs($this->psicologo);

    $this->agendamento = Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-10 15:00', 50)->create();
});

afterEach(fn () => Carbon::setTestNow());

it('o check-in muda o estado e leva à tela do atendimento', function () {
    Livewire::test(Schedule::class)
        ->call('checkIn', $this->agendamento->id)
        ->assertRedirect(route('atendimentos.show', $this->agendamento));

    $this->agendamento->refresh();

    expect($this->agendamento->status)->toBe(AppointmentStatus::InProgress)
        ->and($this->agendamento->checked_in_at)->not->toBeNull();
});

/** Sem janela de horário: a criança chega adiantada, o anterior varou. */
it('aceita check-in fora do horário marcado', function () {
    Carbon::setTestNow('2026-09-10 09:12');

    Livewire::test(Schedule::class)->call('checkIn', $this->agendamento->id);

    expect($this->agendamento->refresh()->status)->toBe(AppointmentStatus::InProgress);
});

it('não faz check-in duas vezes', function () {
    Livewire::test(Schedule::class)->call('checkIn', $this->agendamento->id);

    Livewire::test(Schedule::class)
        ->call('abrirDetalhe', $this->agendamento->id)
        ->call('checkIn', $this->agendamento->id)
        ->assertSee('não aceita check-in');
});

it('salva o registro sozinho e mostra a hora', function () {
    $this->agendamento->update(['status' => 'in_progress', 'checked_in_at' => now()]);

    Livewire::test(SessionBoard::class, ['appointment' => $this->agendamento])
        ->set('registro', 'Anamnese inicial com a mãe. Queixa principal: fala.')
        ->call('salvar')
        ->assertSet('salvoEm', '15:00');

    expect($this->agendamento->refresh()->notes)
        ->toBe('Anamnese inicial com a mãe. Queixa principal: fala.');
});

it('o check-out fecha o registro e conclui', function () {
    $this->agendamento->update(['status' => 'in_progress', 'checked_in_at' => now()->subMinutes(50)]);

    Livewire::test(SessionBoard::class, ['appointment' => $this->agendamento])
        ->set('registro', 'Sessão de devolutiva aos pais.')
        ->call('pedirConfirmacao')
        ->assertRedirect(route('aprendizes.prontuario', $this->kaleo));

    $this->agendamento->refresh();

    expect($this->agendamento->status)->toBe(AppointmentStatus::Completed)
        ->and($this->agendamento->notes)->toBe('Sessão de devolutiva aos pais.')
        ->and($this->agendamento->notes_locked_at)->not->toBeNull()
        ->and($this->agendamento->duracaoReal())->toBe(50);
});

/**
 * O que foi digitado nos segundos antes do clique tem de entrar no documento
 * que está sendo lacrado — senão o check-out perde o último parágrafo.
 */
it('leva para dentro do fechamento o texto ainda não salvo', function () {
    $this->agendamento->update([
        'status' => 'in_progress',
        'checked_in_at' => now(),
        'notes' => 'Primeira parte.',
    ]);

    Livewire::test(SessionBoard::class, ['appointment' => $this->agendamento])
        ->set('registro', 'Primeira parte. E o parágrafo digitado no fim.')
        ->call('pedirConfirmacao');

    expect($this->agendamento->refresh()->notes)
        ->toBe('Primeira parte. E o parágrafo digitado no fim.');
});

it('confirma antes de concluir sem registro escrito', function () {
    $this->agendamento->update(['status' => 'in_progress', 'checked_in_at' => now()]);

    $componente = Livewire::test(SessionBoard::class, ['appointment' => $this->agendamento])
        ->call('pedirConfirmacao')
        ->assertSet('confirmandoCheckout', true)
        ->assertSee('Concluir sem registro escrito?')
        ->assertNoRedirect();

    expect($this->agendamento->refresh()->status)->toBe(AppointmentStatus::InProgress);

    // Atendimento sem registro é legítimo — só precisa ser ato consciente.
    $componente->call('checkOut');

    expect($this->agendamento->refresh()->status)->toBe(AppointmentStatus::Completed)
        ->and($this->agendamento->notes)->toBeNull();
});

it('recusa alterar o registro depois do check-out', function () {
    $this->agendamento->update([
        'status' => 'completed',
        'notes' => 'Registro original.',
        'notes_locked_at' => now(),
        'checked_in_at' => now()->subHour(),
        'checked_out_at' => now(),
    ]);

    Livewire::test(SessionBoard::class, ['appointment' => $this->agendamento])
        ->set('registro', 'Tentativa de reescrever.')
        ->call('salvar')
        ->assertSee('fechado');

    expect($this->agendamento->refresh()->notes)->toBe('Registro original.');
});

it('a correção seguinte vira aditamento datado, sem tocar no original', function () {
    $this->agendamento->update([
        'status' => 'completed',
        'notes' => 'Registro original.',
        'notes_locked_at' => now(),
        'checked_in_at' => now()->subHour(),
        'checked_out_at' => now(),
    ]);

    Livewire::test(SessionBoard::class, ['appointment' => $this->agendamento])
        ->set('aditamento', 'Correção: a sessão durou 40 minutos, não 50.')
        ->call('acrescentarAditamento')
        ->assertSet('aditamento', '')
        ->assertSee('Correção: a sessão durou 40 minutos');

    $adendo = AppointmentAddendum::sole();

    expect($adendo->body)->toBe('Correção: a sessão durou 40 minutos, não 50.')
        ->and($adendo->created_at->format('d/m/Y'))->toBe('10/09/2026')
        ->and($this->agendamento->refresh()->notes)->toBe('Registro original.');
});

it('não aceita aditamento em atendimento ainda aberto', function () {
    $this->agendamento->update(['status' => 'in_progress', 'checked_in_at' => now()]);

    Livewire::test(SessionBoard::class, ['appointment' => $this->agendamento])
        ->set('aditamento', 'Cedo demais.')
        ->call('acrescentarAditamento')
        ->assertForbidden();

    expect(AppointmentAddendum::count())->toBe(0);
});

it('não aceita aditamento vazio', function () {
    $this->agendamento->update([
        'status' => 'completed', 'notes' => 'x', 'notes_locked_at' => now(),
    ]);

    Livewire::test(SessionBoard::class, ['appointment' => $this->agendamento])
        ->set('aditamento', '   ')
        ->call('acrescentarAditamento')
        ->assertSee('Escreva o aditamento');

    expect(AppointmentAddendum::count())->toBe(0);
});

it('o aditamento é somente-acréscimo: a tabela não tem updated_at', function () {
    expect(Schema::hasColumn('appointment_addenda', 'updated_at'))->toBeFalse()
        ->and(Schema::hasColumn('appointment_addenda', 'created_at'))->toBeTrue();
});

it('marca falta e cancela com motivo', function () {
    $falta = Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-11 15:00')->create();

    Livewire::test(Schedule::class)->call('marcarFalta', $falta->id);
    expect($falta->refresh()->status)->toBe(AppointmentStatus::NoShow);

    Livewire::test(Schedule::class)
        ->call('abrirDetalhe', $this->agendamento->id)
        ->call('pedirMotivo')
        ->set('motivoDoCancelamento', 'Responsável remarcou.')
        ->call('cancelar', $this->agendamento->id);

    $this->agendamento->refresh();

    expect($this->agendamento->status)->toBe(AppointmentStatus::Cancelled)
        ->and($this->agendamento->cancel_reason)->toBe('Responsável remarcou.');
});

it('não cancela sem motivo', function () {
    Livewire::test(Schedule::class)
        ->call('abrirDetalhe', $this->agendamento->id)
        ->call('pedirMotivo')
        ->set('motivoDoCancelamento', '  ')
        ->call('cancelar', $this->agendamento->id)
        ->assertSee('Informe o motivo');

    expect($this->agendamento->refresh()->status)->toBe(AppointmentStatus::Scheduled);
});
