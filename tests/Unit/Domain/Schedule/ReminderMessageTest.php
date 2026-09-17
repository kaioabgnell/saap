<?php

declare(strict_types=1);

use App\Domain\Schedule\ReminderMessage;

it('monta o lembrete com o nome da clínica', function () {
    $texto = ReminderMessage::montar(
        'Kaleo',
        new DateTimeImmutable('2026-09-10 15:00'),
        'Clínica Passo a Passo',
        'Kaio Gomes',
    );

    expect($texto)->toBe(
        'Olá! Lembrando do atendimento de Kaleo na Clínica Passo a Passo, '
        .'quinta-feira (10/09) às 15h. Qualquer imprevisto, é só avisar.'
    );
});

it('usa o nome do psicólogo quando não há clínica cadastrada', function (?string $clinica) {
    $texto = ReminderMessage::montar(
        'Kaleo',
        new DateTimeImmutable('2026-09-10 15:00'),
        $clinica,
        'Kaio Gomes',
    );

    expect($texto)->toContain('de Kaleo com Kaio Gomes,')
        ->and($texto)->not->toContain('na ,');
})->with(['nula' => null, 'vazia' => '', 'em branco' => '   ']);

it('escreve a hora como se fala', function (string $quando, string $esperado) {
    $texto = ReminderMessage::montar('Kaleo', new DateTimeImmutable($quando), null, 'Kaio');

    expect($texto)->toContain("às {$esperado}.");
})->with([
    ['2026-09-10 15:00', '15h'],
    ['2026-09-10 15:30', '15h30'],
    ['2026-09-10 09:00', '9h'],
    ['2026-09-10 08:45', '8h45'],
]);

/** Dia da semana errado manda a criança à clínica no dia errado. */
it('nomeia todos os dias da semana em português', function (string $data, string $dia) {
    $texto = ReminderMessage::montar('Kaleo', new DateTimeImmutable($data.' 15:00'), null, 'Kaio');

    expect($texto)->toContain("{$dia} (");
})->with([
    ['2026-09-07', 'segunda-feira'],
    ['2026-09-08', 'terça-feira'],
    ['2026-09-09', 'quarta-feira'],
    ['2026-09-10', 'quinta-feira'],
    ['2026-09-11', 'sexta-feira'],
    ['2026-09-12', 'sábado'],
    ['2026-09-13', 'domingo'],
]);
