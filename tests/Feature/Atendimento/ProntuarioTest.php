<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\AppointmentAddendum;
use App\Models\Assessment;
use App\Models\Learner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-09-10 18:00');
    $this->psicologo = User::factory()->create();
    $this->kaleo = Learner::factory()->for($this->psicologo)->create([
        'name' => 'Kaleo', 'birth_date' => '2020-03-15', 'mother_name' => 'Ana Souza',
    ]);
    $this->actingAs($this->psicologo);
});

afterEach(fn () => Carbon::setTestNow());

it('agrega identificação, avaliações e atendimentos', function () {
    Assessment::factory()->for($this->psicologo)->for($this->kaleo)
        ->create(['applied_on' => '2026-08-01']);

    Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-03 15:00')->concluido('Sessão de intervenção.')->create();

    $this->get(route('aprendizes.prontuario', $this->kaleo))
        ->assertOk()
        ->assertSee('Prontuário')
        ->assertSee('Kaleo')
        ->assertSee('Ana Souza')
        ->assertSee('15/03/2020')
        ->assertSee('01/08/2026')
        ->assertSee('Sessão de intervenção.');
});

it('lista os atendimentos do mais recente para o mais antigo', function () {
    foreach (['2026-07-01', '2026-09-01', '2026-08-01'] as $dia) {
        Appointment::factory()->for($this->psicologo)->for($this->kaleo)
            ->em("{$dia} 15:00")->concluido("Registro de {$dia}.")->create();
    }

    $html = $this->get(route('aprendizes.prontuario', $this->kaleo))->getContent();

    expect(strpos($html, 'Registro de 2026-09-01.'))
        ->toBeLessThan(strpos($html, 'Registro de 2026-08-01.'))
        ->and(strpos($html, 'Registro de 2026-08-01.'))
        ->toBeLessThan(strpos($html, 'Registro de 2026-07-01.'));
});

it('mostra a duração real, do check-in ao check-out', function () {
    $atendimento = Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-03 15:00', 50)->create([
            'status' => 'completed',
            'checked_in_at' => '2026-09-03 15:07',
            'checked_out_at' => '2026-09-03 15:47',
            'notes' => 'x',
            'notes_locked_at' => '2026-09-03 15:47',
        ]);

    expect($atendimento->duracaoReal())->toBe(40);

    $this->get(route('aprendizes.prontuario', $this->kaleo))
        ->assertSee('40 min')
        ->assertSee('15:07–15:47');
});

it('mostra o aditamento abaixo do registro original, sem substituí-lo', function () {
    $atendimento = Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-03 15:00')->concluido('Registro original.')->create();

    AppointmentAddendum::create([
        'appointment_id' => $atendimento->id,
        'body' => 'Acréscimo do dia seguinte.',
    ]);

    $html = $this->get(route('aprendizes.prontuario', $this->kaleo))
        ->assertSee('Registro original.')
        ->assertSee('Acréscimo do dia seguinte.')
        ->assertSee('Aditamento')
        ->getContent();

    expect(strpos($html, 'Registro original.'))
        ->toBeLessThan(strpos($html, 'Acréscimo do dia seguinte.'));
});

/** Uma sequência de faltas é informação clínica; escondê-la seria editar a história. */
it('mostra faltas e cancelamentos na linha do tempo', function () {
    Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-01 15:00')->falta()->create();
    Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-02 15:00')->cancelado()->create();

    $this->get(route('aprendizes.prontuario', $this->kaleo))
        ->assertSee('Faltou')
        ->assertSee('Cancelado')
        ->assertSee('Responsável remarcou.');
});

it('registra que o atendimento foi concluído sem registro escrito', function () {
    Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-03 15:00')->concluido(null)->create();

    $this->get(route('aprendizes.prontuario', $this->kaleo))
        ->assertSee('Concluído sem registro escrito.');
});

/**
 * Um aprendiz que só fez anamnese tem prontuário: a seção de avaliações
 * aparece vazia, e o atendimento entra normalmente.
 */
it('vale para aprendiz sem avaliação nenhuma', function () {
    Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-03 15:00')->concluido('Anamnese inicial com a mãe.')->create();

    expect($this->kaleo->assessments()->count())->toBe(0);

    $this->get(route('aprendizes.prontuario', $this->kaleo))
        ->assertOk()
        ->assertSee('Nenhuma avaliação registrada.')
        ->assertSee('Anamnese inicial com a mãe.');
});

it('é somente leitura: não há formulário de escrita', function () {
    Appointment::factory()->for($this->psicologo)->for($this->kaleo)
        ->em('2026-09-03 15:00')->concluido()->create();

    $html = $this->get(route('aprendizes.prontuario', $this->kaleo))->getContent();

    // Só o conteúdo da página: o <form> de logout mora no menu, e não é
    // escrita de prontuário.
    $conteudo = Str::between($html, '<main>', '</main>');

    expect($conteudo)->not->toContain('<form')
        ->and($conteudo)->not->toContain('<textarea')
        ->and($conteudo)->not->toContain('wire:model');
});

it('a ficha do aprendiz leva ao prontuário', function () {
    $this->get(route('aprendizes.show', $this->kaleo))
        ->assertOk()
        ->assertSee(route('aprendizes.prontuario', $this->kaleo));
});
