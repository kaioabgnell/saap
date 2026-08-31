<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Scoring;

/**
 * Os três valores que um marco pode valer. Nada além disso é pontuação válida.
 */
enum Score: string
{
    case Zero = '0.0';
    case Half = '0.5';
    case Full = '1.0';

    public static function fromFloat(float $valor): self
    {
        return match (true) {
            $valor >= 1.0 => self::Full,
            $valor >= 0.5 => self::Half,
            default => self::Zero,
        };
    }

    public function toFloat(): float
    {
        return (float) $this->value;
    }

    /** Rótulo curto para a interface: "1", "½", "0". */
    public function label(): string
    {
        return match ($this) {
            self::Zero => '0',
            self::Half => '½',
            self::Full => '1',
        };
    }
}
