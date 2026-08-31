<?php

declare(strict_types=1);

use App\Domain\Vbmapp\Scoring\EntryTally;
use App\Domain\Vbmapp\Scoring\MatrixStrategy;
use App\Domain\Vbmapp\Scoring\ResponseType;

beforeEach(fn () => $this->tally = new EntryTally);

it('conta caixas de texto preenchidas em counter_free', function () {
    $entries = [
        ['position' => 1, 'text_value' => 'bola'],
        ['position' => 2, 'text_value' => 'água'],
        ['position' => 3, 'text_value' => ''],
        ['position' => 4, 'text_value' => null],
    ];

    expect($this->tally->count(ResponseType::CounterFree, $entries))->toBe(2);
});

it('ignora caixa preenchida só com espaço', function () {
    $entries = [['position' => 1, 'text_value' => '   ']];

    expect($this->tally->count(ResponseType::CounterFree, $entries))->toBe(0);
});

it('conta checks em counter_list e counter_stimuli', function () {
    $entries = [
        ['position' => 1, 'is_checked' => true],
        ['position' => 2, 'is_checked' => false],
        ['position' => 3, 'is_checked' => true],
    ];

    expect($this->tally->count(ResponseType::CounterList, $entries))->toBe(2)
        ->and($this->tally->count(ResponseType::CounterStimuli, $entries))->toBe(2);
});

it('lê o ordinal escolhido em binary_criteria', function () {
    // 2 = atingiu o critério de 1 ponto; 1 = o de ½; nada = não atingiu.
    expect($this->tally->count(ResponseType::BinaryCriteria, [['position' => 2, 'is_checked' => true]]))->toBe(2)
        ->and($this->tally->count(ResponseType::BinaryCriteria, [['position' => 1, 'is_checked' => true]]))->toBe(1)
        ->and($this->tally->count(ResponseType::BinaryCriteria, []))->toBe(0)
        ->and($this->tally->count(ResponseType::BinaryCriteria, [['position' => 2, 'is_checked' => false]]))->toBe(0);
});

it('conta linha de matrix só quando todos os exemplares estão marcados', function () {
    $entries = [
        ['list_key' => 'bola', 'column_key' => '1', 'is_checked' => true],
        ['list_key' => 'bola', 'column_key' => '2', 'is_checked' => true],
        ['list_key' => 'bola', 'column_key' => '3', 'is_checked' => true],
        ['list_key' => 'gato', 'column_key' => '1', 'is_checked' => true],
        ['list_key' => 'gato', 'column_key' => '2', 'is_checked' => false],
        ['list_key' => 'gato', 'column_key' => '3', 'is_checked' => true],
    ];

    expect($this->tally->count(ResponseType::Matrix, $entries))->toBe(1);
});

it('devolve zero sem entradas, em qualquer tipo', function () {
    foreach (ResponseType::cases() as $tipo) {
        expect($this->tally->count($tipo, []))->toBe(0, $tipo->value);
    }
});

it('conta total de células em matrix quando a estratégia é total_cells', function () {
    // Tato 11-M: 5 objetos x 3 características. O critério é sobre o total de
    // células certas, não sobre linhas completas — 10 de 15 pode vir de
    // qualquer combinação.
    $entries = [
        ['list_key' => 'maçã', 'column_key' => 'cor', 'is_checked' => true],
        ['list_key' => 'maçã', 'column_key' => 'forma', 'is_checked' => true],
        ['list_key' => 'maçã', 'column_key' => 'função', 'is_checked' => false],
        ['list_key' => 'lixeira', 'column_key' => 'cor', 'is_checked' => true],
        ['list_key' => 'lixeira', 'column_key' => 'forma', 'is_checked' => false],
        ['list_key' => 'lixeira', 'column_key' => 'função', 'is_checked' => false],
    ];

    expect($this->tally->count(ResponseType::Matrix, $entries, MatrixStrategy::TotalCells))
        ->toBe(3);
});

it('não conta linha incompleta como acerto em rows_complete', function () {
    // Tato 7-M: só conta quando os 3 exemplares do item estão marcados.
    $entries = [
        ['list_key' => 'maçã', 'column_key' => '1', 'is_checked' => true],
        ['list_key' => 'maçã', 'column_key' => '2', 'is_checked' => true],
        ['list_key' => 'maçã', 'column_key' => '3', 'is_checked' => false], // incompleto
        ['list_key' => 'gato', 'column_key' => '1', 'is_checked' => true],
        ['list_key' => 'gato', 'column_key' => '2', 'is_checked' => true],
        ['list_key' => 'gato', 'column_key' => '3', 'is_checked' => true], // completo
    ];

    expect($this->tally->count(ResponseType::Matrix, $entries, MatrixStrategy::RowsComplete))
        ->toBe(1);
});

it('usa rows_complete como padrão quando a estratégia não é informada', function () {
    $entries = [
        ['list_key' => 'a', 'column_key' => '1', 'is_checked' => true],
        ['list_key' => 'a', 'column_key' => '2', 'is_checked' => true],
    ];

    expect($this->tally->count(ResponseType::Matrix, $entries))->toBe(1);
});
