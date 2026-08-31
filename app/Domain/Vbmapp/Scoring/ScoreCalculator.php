<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Scoring;

use InvalidArgumentException;

/**
 * A régua de pontuação do VB-MAPP, uniforme para os 170 marcos.
 *
 * PHP puro: recebe números e devolve pontuação. Não conhece Eloquent, não
 * conhece HTTP. É o único lugar do sistema que decide quanto um marco vale.
 */
final class ScoreCalculator
{
    /**
     * @param  int  $acertos  Quantidade apurada pelo EntryTally.
     * @param  int  $thresholdFull  Acertos para 1 ponto.
     * @param  int|null  $thresholdHalf  Acertos para ½. Nulo quando o marco não
     *                                   admite meio ponto — caso real: Ouvinte
     *                                   2-M, "Não há ½ ponto para esta
     *                                   habilidade" (manual, p. 80).
     */
    public function score(int $acertos, int $thresholdFull, ?int $thresholdHalf): Score
    {
        if ($acertos < 0) {
            throw new InvalidArgumentException('A contagem de acertos não pode ser negativa.');
        }

        if ($thresholdFull < 1) {
            // Nunca invente limiar: um marco sem meta é defeito de catálogo,
            // e defeito de catálogo contamina todo laudo em silêncio.
            throw new InvalidArgumentException('O marco não tem limiar de 1 ponto definido.');
        }

        if ($thresholdHalf !== null && $thresholdHalf > $thresholdFull) {
            throw new InvalidArgumentException(
                "Limiar de meio ponto ({$thresholdHalf}) maior que o de um ponto ({$thresholdFull})."
            );
        }

        if ($acertos >= $thresholdFull) {
            return Score::Full;
        }

        if ($thresholdHalf !== null && $acertos >= $thresholdHalf) {
            return Score::Half;
        }

        return Score::Zero;
    }
}
