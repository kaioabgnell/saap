<?php

declare(strict_types=1);

use App\Domain\Learner\Age;

it('calcula idade em anos e meses na data da aplicação', function () {
    $idade = Age::between('2020-03-15', '2024-10-20');

    expect($idade->years)->toBe(4)
        ->and($idade->months)->toBe(7)
        ->and($idade->format())->toBe('4 anos e 7 meses');
});

it('usa singular para 1 ano e 1 mês', function () {
    $idade = Age::between('2023-06-01', '2024-07-01');

    expect($idade->format())->toBe('1 ano e 1 mês');
});

it('usa singular só para o ano quando os meses são zero', function () {
    $idade = Age::between('2023-06-01', '2024-06-01');

    expect($idade->format())->toBe('1 ano');
});

it('omite meses quando são zero', function () {
    $idade = Age::between('2021-04-10', '2024-04-10');

    expect($idade->format())->toBe('3 anos');
});

it('mostra só meses quando tem menos de 1 ano', function () {
    $idade = Age::between('2024-01-01', '2024-08-15');

    expect($idade->years)->toBe(0)
        ->and($idade->months)->toBe(7)
        ->and($idade->format())->toBe('7 meses');
});

it('usa singular de mês quando o total é 1', function () {
    $idade = Age::between('2024-06-01', '2024-07-15');

    expect($idade->format())->toBe('1 mês');
});

it('trata nascido em 31/jan avaliado em 28/fev como mês incompleto', function () {
    // Fevereiro — mesmo bissexto — não chega ao dia 31, então o mês não fecha.
    $idade = Age::between('2020-01-31', '2024-02-28');

    expect($idade->years)->toBe(4)
        ->and($idade->months)->toBe(0)
        ->and($idade->format())->toBe('4 anos');
});

it('fecha o mês quando a referência cai no último dia do bissexto', function () {
    $idade = Age::between('2020-01-31', '2024-02-29');

    expect($idade->years)->toBe(4)
        ->and($idade->months)->toBe(0)
        ->and($idade->format())->toBe('4 anos');
});

it('trata nascido em 29/fev avaliado em ano não bissexto', function () {
    $idade = Age::between('2020-02-29', '2023-02-28');

    expect($idade->format())->toBe('2 anos e 11 meses');
});

it('fecha 4 anos exatos quando nascido em 29/fev e o próximo bissexto cai igual', function () {
    $idade = Age::between('2020-02-29', '2024-02-29');

    expect($idade->format())->toBe('4 anos');
});

it('trata nascimento e referência no mesmo dia como recém-nascido', function () {
    $idade = Age::between('2024-01-01', '2024-01-01');

    expect($idade->format())->toBe('menos de 1 mês');
});

it('recusa referência anterior ao nascimento', function () {
    Age::between('2024-06-01', '2024-01-01');
})->throws(InvalidArgumentException::class);

it('aceita objetos DateTimeImmutable além de strings', function () {
    $idade = Age::between(
        new DateTimeImmutable('2020-03-15'),
        new DateTimeImmutable('2024-10-20'),
    );

    expect($idade->format())->toBe('4 anos e 7 meses');
});
