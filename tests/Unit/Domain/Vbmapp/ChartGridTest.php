<?php

declare(strict_types=1);

use App\Domain\Vbmapp\Chart\ChartGrid;

/** Duas áreas de mentira, cinco marcos cada — o suficiente para a montagem. */
function areasDeLancamento(): array
{
    $monta = fn (string $code, string $nome, string $curto, int $base, array $semMeio = []) => [
        'code' => $code,
        'name' => $nome,
        'short_name' => $curto,
        'items' => array_map(fn (int $p) => [
            'id' => $base + $p,
            'position' => $p,
            'code' => "{$code}:{$p}",
            'statement' => "Enunciado {$code} {$p}",
            'has_half_point' => ! in_array($p, $semMeio, true),
        ], [1, 2, 3, 4, 5]),
    ];

    return [
        $monta('mando', 'Mando', 'Mando', 100),
        $monta('ouvinte', 'Comportamento de ouvinte', 'Ouvinte', 200, semMeio: [2]),
    ];
}

it('monta uma coluna por área com o marco 1 embaixo', function () {
    $grade = ChartGrid::build(1, areasDeLancamento(), []);

    expect($grade->columns)->toHaveCount(2)
        ->and($grade->columns[0]['short_name'])->toBe('Mando')
        ->and(array_column($grade->columns[0]['cells'], 'position'))->toBe([5, 4, 3, 2, 1]);
});

it('deriva o estado da célula a partir da pontuação', function () {
    $grade = ChartGrid::build(1, areasDeLancamento(), [101 => 1.0, 102 => 0.5, 103 => 0.0]);

    $porMarco = collect($grade->columns[0]['cells'])->keyBy('item_id');

    expect($porMarco[101]['state'])->toBe(ChartGrid::ESTADO_CHEIO)
        ->and($porMarco[102]['state'])->toBe(ChartGrid::ESTADO_MEIO)
        ->and($porMarco[103]['state'])->toBe(ChartGrid::ESTADO_ZERO)
        ->and($porMarco[104]['state'])->toBe(ChartGrid::ESTADO_PENDENTE);
});

it('distingue zero marcado de célula nunca tocada', function () {
    $grade = ChartGrid::build(1, areasDeLancamento(), [101 => 0.0]);

    expect($grade->markedCount())->toBe(1)
        ->and($grade->pendingCount())->toBe(9)
        ->and($grade->scoreTotal())->toBe(0.0);
});

it('rotula cada célula para quem não enxerga a cor', function () {
    $grade = ChartGrid::build(1, areasDeLancamento(), [102 => 0.5]);

    $celula = collect($grade->columns[0]['cells'])->firstWhere('item_id', 102);

    expect($celula['label'])->toBe('Mando, marco 2: meio ponto');
});

it('soma a pontuação do nível', function () {
    $grade = ChartGrid::build(1, areasDeLancamento(), [101 => 1.0, 102 => 0.5, 201 => 1.0]);

    expect($grade->scoreTotal())->toBe(2.5)
        ->and($grade->total())->toBe(10);
});

it('empilha os marcos do nível 3 de 15 a 11', function () {
    expect(ChartGrid::build(3, [], [])->positionsTopDown())->toBe([15, 14, 13, 12, 11]);
});

describe('regra do clique', function () {
    it('sobe meio ponto pela metade de baixo', function (?float $atual, float $esperado) {
        expect(ChartGrid::nextScore($atual, ChartGrid::METADE_BAIXO, true))->toBe($esperado);
    })->with([
        'pendente vira ½' => [null, 0.5],
        '½ vira zero' => [0.5, 0.0],
        '1 vira zero' => [1.0, 0.0],
        'zero vira ½' => [0.0, 0.5],
    ]);

    it('fecha o ponto pela metade de cima', function (?float $atual, float $esperado) {
        expect(ChartGrid::nextScore($atual, ChartGrid::METADE_CIMA, true))->toBe($esperado);
    })->with([
        'pendente vira 1' => [null, 1.0],
        '½ vira 1' => [0.5, 1.0],
        '1 volta a ½' => [1.0, 0.5],
        'zero vira 1' => [0.0, 1.0],
    ]);

    it('nunca produz meio ponto em marco que o manual não prevê', function (string $metade) {
        // Ouvinte 2: threshold_half nulo. As duas metades viram um
        // interruptor só — é o catálogo mandando na interface.
        expect(ChartGrid::nextScore(null, $metade, false))->toBe(1.0)
            ->and(ChartGrid::nextScore(1.0, $metade, false))->toBe(0.0)
            ->and(ChartGrid::nextScore(0.0, $metade, false))->toBe(1.0);
    })->with([ChartGrid::METADE_CIMA, ChartGrid::METADE_BAIXO]);

    it('recusa metade que não existe', function () {
        ChartGrid::nextScore(null, 'meio', true);
    })->throws(InvalidArgumentException::class);
});
