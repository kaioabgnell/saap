<?php

declare(strict_types=1);

use App\Domain\Schedule\AppointmentStatus;
use App\Livewire\Agenda\Schedule;
use App\Models\Appointment;
use App\Models\Learner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-09-10 10:00'); // uma quinta-feira
    $this->psicologo = User::factory()->create();
    $this->kaleo = Learner::factory()->for($this->psicologo)->create(['name' => 'Kaleo']);
    $this->actingAs($this->psicologo);
});

afterEach(fn () => Carbon::setTestNow());

it('tem a agenda no menu principal', function () {
    $this->get(route('painel'))
        ->assertOk()
        ->assertSee(route('agenda'))
        ->assertSee('Agenda');
});

it('abre no mês, com a grade do mês corrente e hoje destacado', function () {
    Livewire::test(Schedule::class)
        ->assertSet('visao', 'mes')
        ->assertSee('Setembro de 2026')
        // O cabeçalho da grade só existe na visão de mês.
        ->assertSee('Dom')
        ->assertSee('Sáb')
        ->assertSeeHtml('aria-current="date"');
});

it('desenha 42 células: 6 linhas de 7 dias', function () {
    $componente = Livewire::test(Schedule::class);
    $semanas = $componente->instance()->semanas();

    expect($semanas)->toHaveCount(6);

    foreach ($semanas as $semana) {
        expect($semana)->toHaveCount(7);
    }

    // Começa no domingo anterior ao dia 1º — 30/08/2026 é domingo.
    expect($semanas[0][0]->toDateString())->toBe('2026-08-30')
        ->and($semanas[5][6]->toDateString())->toBe('2026-10-10');
});

it('mostra as fichas do dia na grade do mês', function () {
    Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-15 14:00')->create();

    Livewire::test(Schedule::class)
        ->assertSee('14:00')
        ->assertSee('Kaleo');
});

it('resume a célula com "+N mais" quando não cabem todas', function () {
    // Quantas cabem é decisão de layout (Schedule::FICHAS_POR_DIA); o teste
    // verifica a conta do resumo, não o número escolhido lá.
    $cabem = Schedule::FICHAS_POR_DIA;
    $total = $cabem + 3;

    foreach (range(1, $total) as $i) {
        Appointment::factory()->for($this->psicologo)->for($this->kaleo)
            ->em(sprintf('2026-09-15 %02d:00', 7 + $i), 50)->create();
    }

    Livewire::test(Schedule::class)
        ->assertSee('+3 mais')
        // As que cabem aparecem; a partir daí, só o resumo.
        ->assertSee('08:00')
        ->assertDontSee(sprintf('%02d:00', 7 + $total));
});

it('troca de visão e a escolha persiste na sessão', function () {
    Livewire::test(Schedule::class)
        ->call('mudarVisao', 'dia')
        ->assertSet('visao', 'dia')
        ->assertSee('quinta-feira, 10 de setembro');

    // Uma visita nova encontra a visão que a psicóloga deixou.
    Livewire::test(Schedule::class)->assertSet('visao', 'dia');
});

it('ignora visão inválida em vez de quebrar a tela', function () {
    Livewire::test(Schedule::class)
        ->call('mudarVisao', 'trimestre')
        ->assertSet('visao', 'mes');
});

it('anda na unidade da visão atual', function (string $visao, string $depois, string $titulo) {
    Livewire::test(Schedule::class)
        ->call('mudarVisao', $visao)
        ->call('proximo')
        ->assertSet('ancora', $depois)
        ->assertSee($titulo);
})->with([
    'mês' => ['mes', '2026-10-10', 'Outubro de 2026'],
    'semana' => ['semana', '2026-09-17', '13 a 19 de setembro de 2026'],
    'dia' => ['dia', '2026-09-11', 'sexta-feira, 11 de setembro'],
]);

it('volta para hoje', function () {
    Livewire::test(Schedule::class)
        ->call('proximo')
        ->call('proximo')
        ->call('hoje')
        ->assertSet('ancora', '2026-09-10');
});

/** 31/03 recuando um mês tem de dar 28/02, não 03/03 — senão a grade pula fevereiro. */
it('não transborda o mês ao andar a partir de um dia 31', function () {
    Livewire::test(Schedule::class)
        ->set('ancora', '2026-03-31')
        ->call('anterior')
        ->assertSet('ancora', '2026-02-28')
        ->assertSee('Fevereiro de 2026');
});

it('clicar no número do dia abre aquele dia', function () {
    Livewire::test(Schedule::class)
        ->call('abrirDia', '2026-09-22')
        ->assertSet('visao', 'dia')
        ->assertSet('ancora', '2026-09-22')
        ->assertSee('terça-feira, 22 de setembro');
});

it('clicar num dia vazio abre o agendamento já com aquela data', function () {
    Livewire::test(Schedule::class)
        ->call('novo', '2026-09-22')
        ->assertDispatched('abrir-agendamento', data: '2026-09-22', hora: null);
});

/**
 * O botão do cabeçalho não passa data: o padrão é HOJE, mesmo depois de
 * navegar para outro mês ou de ter acabado de criar um agendamento em outra
 * data — a âncora, nesses casos, não é mais hoje, e o botão genérico não pode
 * herdar a data do último agendamento como se fosse o dia sendo visto.
 */
it('o botão "Novo agendamento" do cabeçalho sempre abre em hoje, mesmo navegando', function () {
    Livewire::test(Schedule::class)
        ->call('proximo')
        ->call('proximo')
        ->call('novo')
        ->assertDispatched('abrir-agendamento', data: '2026-09-10', hora: null);
});

it('o botão "Novo agendamento" do cabeçalho abre em hoje mesmo após criar outro agendamento', function () {
    Livewire::test(Schedule::class)
        ->call('aoMudarAgenda', '2026-09-25')
        ->assertSet('ancora', '2026-09-25')
        ->call('novo')
        ->assertDispatched('abrir-agendamento', data: '2026-09-10', hora: null);
});

it('clicar num horário vazio leva a data e a hora', function () {
    Livewire::test(Schedule::class)
        ->call('mudarVisao', 'dia')
        ->call('novo', '2026-09-10', 15)
        ->assertDispatched('abrir-agendamento', data: '2026-09-10', hora: '15:00');
});

it('mostra falta na agenda: o horário foi ocupado de fato', function () {
    Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-15 08:00')->falta()->create();

    Livewire::test(Schedule::class)->assertSee('Faltou');
});

/**
 * Cancelar libera o horário — mantê-lo na grade faria a agenda parecer mais
 * ocupada do que está. O cancelamento não some do sistema: continua na linha
 * do tempo do prontuário (ver ProntuarioTest).
 */
it('remove o cancelamento da agenda, sem apagar o registro', function () {
    $cancelado = Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-16 08:00')->cancelado()->create();

    Livewire::test(Schedule::class)->assertDontSee('Cancelado');

    expect($cancelado->fresh())->not->toBeNull()
        ->and($cancelado->fresh()->status)->toBe(AppointmentStatus::Cancelled);
});

it('some da agenda assim que o cancelamento é confirmado', function () {
    $agendamento = Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-15 15:00')->create();

    Livewire::test(Schedule::class)
        ->assertSee('15:00')
        ->call('abrirDetalhe', $agendamento->id)
        ->call('pedirMotivo')
        ->set('motivoDoCancelamento', 'Responsável remarcou.')
        ->call('cancelar', $agendamento->id)
        ->assertDontSee('15:00');
});

it('a trilha de horas se abre para caber atendimento fora do expediente', function () {
    Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-10 06:00')->create();

    $horas = Livewire::test(Schedule::class)->call('mudarVisao', 'dia')->instance()->horas();

    expect($horas[0])->toBe(6);
});

it('não mostra a agenda de outro psicólogo', function () {
    $outro = User::factory()->create();
    $dele = Learner::factory()->for($outro)->create(['name' => 'Criança de outro']);
    Appointment::factory()->for($outro)->for($dele)->em('2026-09-15 14:00')->create();

    Livewire::test(Schedule::class)->assertDontSee('Criança de outro');
});
