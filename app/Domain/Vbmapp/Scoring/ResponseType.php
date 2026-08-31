<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Scoring;

/**
 * Como o psicólogo registra o desempenho num marco.
 * Todo marco vale 0, ½ ou 1 — o que muda é a forma de chegar lá.
 */
enum ResponseType: string
{
    /** Escolhe entre dois critérios textuais. Ex.: contato visual 2× = ½, 5× = 1. */
    case BinaryCriteria = 'binary_criteria';

    /** Preenche N caixas de texto, uma por exemplar observado. */
    case CounterFree = 'counter_free';

    /** Marca o check de cada acerto na grade de imagens do material. */
    case CounterStimuli = 'counter_stimuli';

    /** Marca acertos numa lista fixa vinda do instrumento. */
    case CounterList = 'counter_list';

    /** Grade de item × exemplares. */
    case Matrix = 'matrix';

    /** A pontuação vem de contar entradas, e não de escolher um critério. */
    public function isCounted(): bool
    {
        return $this !== self::BinaryCriteria;
    }

    public function label(): string
    {
        return match ($this) {
            self::BinaryCriteria => 'Critério',
            self::CounterFree => 'Contagem livre',
            self::CounterStimuli => 'Contagem por imagem',
            self::CounterList => 'Contagem em lista',
            self::Matrix => 'Grade',
        };
    }
}
