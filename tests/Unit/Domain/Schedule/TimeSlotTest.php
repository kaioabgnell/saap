<?php

declare(strict_types=1);

use App\Domain\Schedule\TimeSlot;

/** Duas faixas do mesmo dia, escritas curto para o teste ficar legível. */
function faixa(string $inicio, string $fim): TimeSlot
{
    return new TimeSlot("2026-09-10 {$inicio}", "2026-09-10 {$fim}");
}

it('detecta sobreposição parcial', function () {
    expect(faixa('15:00', '16:00')->overlaps(faixa('15:30', '16:30')))->toBeTrue();
    expect(faixa('15:30', '16:30')->overlaps(faixa('15:00', '16:00')))->toBeTrue();
});

it('detecta faixa contida em outra', function () {
    expect(faixa('15:00', '17:00')->overlaps(faixa('15:30', '16:00')))->toBeTrue();
    expect(faixa('15:30', '16:00')->overlaps(faixa('15:00', '17:00')))->toBeTrue();
});

it('detecta faixas idênticas', function () {
    expect(faixa('15:00', '16:00')->overlaps(faixa('15:00', '16:00')))->toBeTrue();
});

/**
 * O caso que justifica a desigualdade estrita: 15h–16h e 16h–17h se encostam
 * e NÃO conflitam. Alertar aqui treinaria a psicóloga a ignorar o alerta.
 */
it('não considera conflito quando uma faixa encosta na outra', function () {
    expect(faixa('15:00', '16:00')->overlaps(faixa('16:00', '17:00')))->toBeFalse();
    expect(faixa('16:00', '17:00')->overlaps(faixa('15:00', '16:00')))->toBeFalse();
});

it('não considera conflito entre faixas separadas', function () {
    expect(faixa('15:00', '16:00')->overlaps(faixa('18:00', '19:00')))->toBeFalse();
});

it('não considera conflito no mesmo horário de dias diferentes', function () {
    $quinta = new TimeSlot('2026-09-10 15:00', '2026-09-10 16:00');
    $sexta = new TimeSlot('2026-09-11 15:00', '2026-09-11 16:00');

    expect($quinta->overlaps($sexta))->toBeFalse();
});

it('calcula a duração em minutos', function () {
    expect(faixa('15:00', '15:50')->duration())->toBe(50);
    expect(faixa('15:00', '17:00')->duration())->toBe(120);
});

it('monta a faixa a partir de início e duração', function () {
    $faixa = TimeSlot::deDuracao('2026-09-10 15:00', 50);

    expect($faixa->ends->format('H:i'))->toBe('15:50')
        ->and($faixa->duration())->toBe(50);
});

it('recusa faixa que termina antes de começar', function () {
    faixa('16:00', '15:00');
})->throws(InvalidArgumentException::class);

it('recusa faixa de duração zero', function () {
    faixa('15:00', '15:00');
})->throws(InvalidArgumentException::class);

it('recusa duração menor que um minuto', function () {
    TimeSlot::deDuracao('2026-09-10 15:00', 0);
})->throws(InvalidArgumentException::class);

/**
 * A repetição anda em semanas de calendário: quinta às 15h continua quinta
 * às 15h, e não "168 horas depois".
 */
it('desloca a faixa em semanas mantendo o dia e a hora', function () {
    $original = new TimeSlot('2026-09-10 15:00', '2026-09-10 15:50');
    $terceira = $original->deslocadoEmSemanas(2);

    expect($terceira->starts->format('Y-m-d H:i'))->toBe('2026-09-24 15:00')
        ->and($terceira->ends->format('Y-m-d H:i'))->toBe('2026-09-24 15:50')
        ->and($terceira->starts->format('N'))->toBe($original->starts->format('N'));
});

it('devolve a mesma faixa ao deslocar zero semanas', function () {
    $original = new TimeSlot('2026-09-10 15:00', '2026-09-10 15:50');

    expect($original->deslocadoEmSemanas(0)->starts->format('Y-m-d H:i'))
        ->toBe('2026-09-10 15:00');
});
