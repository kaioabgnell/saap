<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

/**
 * Como a avaliação é conduzida. Definido na criação, nunca alterado.
 *
 *     guided  — o psicólogo aplica na tela; o exemplar registrado é contado
 *               pelo EntryTally e o ScoreCalculator decide a pontuação
 *     chart   — o psicólogo transcreve um formulário de papel já aplicado,
 *               informando a pontuação direto na célula do gráfico
 *
 * Os dois modos são exclusivos por avaliação. Não é preferência de estilo: a
 * tela do nível gravaria uma transcrição com zero exemplares e o
 * ScoreCalculator devolveria 0, apagando em silêncio o que foi transcrito.
 */
enum EntryMode: string
{
    case Guided = 'guided';
    case Chart = 'chart';

    public function label(): string
    {
        return match ($this) {
            self::Guided => 'Aplicação na tela',
            self::Chart => 'Transcrição de papel',
        };
    }

    /** Como o laudo se refere à própria origem. Vai congelado no snapshot. */
    public function reportMode(): string
    {
        return match ($this) {
            self::Guided => 'aplicacao',
            self::Chart => 'transcricao',
        };
    }
}
