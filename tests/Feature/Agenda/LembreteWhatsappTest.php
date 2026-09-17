<?php

declare(strict_types=1);

use App\Livewire\Agenda\Schedule;
use App\Models\Appointment;
use App\Models\Learner;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-09-10 10:00');
    $this->psicologo = User::factory()->create([
        'name' => 'Kaio Gomes',
        'clinic_name' => 'Clínica Passo a Passo',
    ]);
    $this->actingAs($this->psicologo);
});

afterEach(fn () => Carbon::setTestNow());

function agendamentoCom(?string $telefone): Appointment
{
    $learner = Learner::factory()->for(test()->psicologo)
        ->create(['name' => 'Kaleo', 'contact_phone' => $telefone]);

    return Appointment::factory()->for(test()->psicologo)->for($learner)
        ->em('2026-09-10 15:00')->create();
}

it('monta o link do WhatsApp com o número normalizado e a mensagem pronta', function () {
    $agendamento = agendamentoCom('(11) 90000-0000');

    $link = $agendamento->linkDoLembrete();

    expect($link)->toStartWith('https://wa.me/5511900000000?text=')
        ->and(urldecode($link))->toContain(
            'Olá! Lembrando do atendimento de Kaleo na Clínica Passo a Passo, '
            .'quinta-feira (10/09) às 15h.'
        );
});

it('usa o nome do psicólogo quando não há clínica cadastrada', function () {
    $this->psicologo->update(['clinic_name' => null]);

    $link = agendamentoCom('11900000000')->refresh()->linkDoLembrete();

    expect(urldecode($link))->toContain('de Kaleo com Kaio Gomes,');
});

it('abre o lembrete e carimba a data de abertura', function () {
    $agendamento = agendamentoCom('(11) 90000-0000');

    expect($agendamento->reminder_opened_at)->toBeNull();

    Livewire::test(Schedule::class)
        ->call('abrirDetalhe', $agendamento->id)
        ->call('abrirLembrete', $agendamento->id)
        ->assertDispatched('abrir-lembrete');

    expect($agendamento->refresh()->reminder_opened_at)->not->toBeNull();
});

/**
 * A coluna se chama `reminder_opened_at`, não `sent`: o sistema abre o
 * WhatsApp, quem envia é a pessoa. Nada aqui pode afirmar que a mensagem saiu.
 */
it('registra abertura, não envio', function () {
    expect(Schema::hasColumn('appointments', 'reminder_opened_at'))->toBeTrue()
        ->and(Schema::hasColumn('appointments', 'reminder_sent_at'))->toBeFalse();
});

it('sem telefone utilizável, não há link e o botão fica desabilitado com o motivo', function (?string $telefone, string $motivo) {
    $agendamento = agendamentoCom($telefone);

    expect($agendamento->linkDoLembrete())->toBeNull();

    Livewire::test(Schedule::class)
        ->call('abrirDetalhe', $agendamento->id)
        ->assertSeeHtml('disabled')
        ->assertSee($motivo);
})->with([
    'sem telefone' => [null, 'não tem telefone de contato cadastrado'],
    'telefone impossível' => ['0000', 'não é um número brasileiro válido'],
]);

it('não carimba abertura quando não há número para abrir', function () {
    $agendamento = agendamentoCom(null);

    Livewire::test(Schedule::class)
        ->call('abrirDetalhe', $agendamento->id)
        ->call('abrirLembrete', $agendamento->id)
        ->assertNotDispatched('abrir-lembrete')
        ->assertSee('Sem telefone utilizável');

    expect($agendamento->refresh()->reminder_opened_at)->toBeNull();
});

it('não abre o lembrete de agendamento de outro psicólogo', function () {
    $outro = User::factory()->create();
    $dele = Learner::factory()->for($outro)->create(['contact_phone' => '(11) 90000-0000']);
    $agendamento = Appointment::factory()->for($outro)->for($dele)->create();

    Livewire::test(Schedule::class)->call('abrirLembrete', $agendamento->id);
})->throws(ModelNotFoundException::class);
