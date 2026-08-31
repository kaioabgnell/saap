<?php

declare(strict_types=1);

use App\Domain\Vbmapp\Progress\ProgressCounter;

beforeEach(fn () => $this->counter = new ProgressCounter);

it('usa 45, 60 e 65 como total de cada nível', function () {
    expect($this->counter->forLevel(0, 1)->total)->toBe(45)
        ->and($this->counter->forLevel(0, 2)->total)->toBe(60)
        ->and($this->counter->forLevel(0, 3)->total)->toBe(65);
});

it('soma 170 marcos entre os três níveis', function () {
    expect(array_sum(ProgressCounter::TOTAL_POR_NIVEL))->toBe(ProgressCounter::TOTAL_GERAL);
});

it('recusa nível inexistente', function () {
    $this->counter->forLevel(0, 4);
})->throws(InvalidArgumentException::class);

it('formata o progresso do nível e da área', function () {
    expect($this->counter->forLevel(32, 1)->format())->toBe('32 de 45')
        ->and($this->counter->forArea(3)->compact())->toBe('3/5');
});

it('conta pendências', function () {
    expect($this->counter->forLevel(32, 1)->pending())->toBe(13)
        ->and($this->counter->forLevel(45, 1)->pending())->toBe(0);
});

it('marca completo só quando alcança o total', function () {
    expect($this->counter->forLevel(44, 1)->isComplete())->toBeFalse()
        ->and($this->counter->forLevel(45, 1)->isComplete())->toBeTrue();
});

it('usa a soma dos níveis iniciados como total da avaliação', function () {
    // Um aprendiz avaliado só no nível 2 chega a 100% com 60 marcos.
    expect($this->counter->forAssessment(60, [2])->isComplete())->toBeTrue()
        ->and($this->counter->forAssessment(60, [2])->total)->toBe(60)
        ->and($this->counter->forAssessment(0, [1, 2])->total)->toBe(105)
        ->and($this->counter->forAssessment(0, [1, 2, 3])->total)->toBe(170);
});

it('cai para 170 quando nenhum nível foi iniciado', function () {
    expect($this->counter->forAssessment(0, [])->total)->toBe(170);
});

it('calcula percentual', function () {
    expect($this->counter->forLevel(0, 1)->percent())->toBe(0)
        ->and($this->counter->forLevel(45, 1)->percent())->toBe(100)
        ->and($this->counter->forArea(1)->percent())->toBe(20);
});
