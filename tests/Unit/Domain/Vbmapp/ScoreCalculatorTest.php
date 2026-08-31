<?php

declare(strict_types=1);

use App\Domain\Vbmapp\Scoring\Score;
use App\Domain\Vbmapp\Scoring\ScoreCalculator;

beforeEach(fn () => $this->calc = new ScoreCalculator);

it('dá 1 ponto quando atinge o limiar cheio', function () {
    expect($this->calc->score(4, 4, 3))->toBe(Score::Full)
        ->and($this->calc->score(9, 4, 3))->toBe(Score::Full);
});

it('dá meio ponto entre os limiares', function () {
    expect($this->calc->score(3, 4, 3))->toBe(Score::Half);
});

it('dá zero abaixo do limiar de meio ponto', function () {
    expect($this->calc->score(2, 4, 3))->toBe(Score::Zero)
        ->and($this->calc->score(0, 4, 3))->toBe(Score::Zero);
});

it('nunca dá meio ponto quando threshold_half é nulo', function () {
    // Ouvinte 2-M: "Não há ½ ponto para esta habilidade" (manual, p. 80).
    foreach (range(0, 4) as $acertos) {
        expect($this->calc->score($acertos, 5, null))->toBe(Score::Zero, "acertos={$acertos}");
    }

    expect($this->calc->score(5, 5, null))->toBe(Score::Full);
});

it('trata limiares iguais como 1 ponto ao atingir a contagem', function () {
    // Marcos assisted (Mando 4-M) têm meio == cheio. A régua sozinha não
    // distingue os critérios — por isso exigem confirmação explícita.
    expect($this->calc->score(5, 5, 5))->toBe(Score::Full)
        ->and($this->calc->score(4, 5, 5))->toBe(Score::Zero);
});

it('recusa contagem negativa', function () {
    $this->calc->score(-1, 4, 3);
})->throws(InvalidArgumentException::class);

it('recusa marco sem limiar de 1 ponto', function () {
    // Defeito de catálogo não pode virar default silencioso.
    $this->calc->score(3, 0, null);
})->throws(InvalidArgumentException::class);

it('recusa meio ponto maior que um ponto', function () {
    $this->calc->score(3, 4, 5);
})->throws(InvalidArgumentException::class);

it('converte pontuação para float e rótulo', function () {
    expect(Score::Full->toFloat())->toBe(1.0)
        ->and(Score::Half->toFloat())->toBe(0.5)
        ->and(Score::Zero->toFloat())->toBe(0.0)
        ->and(Score::Half->label())->toBe('½');
});
