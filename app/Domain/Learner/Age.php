<?php

declare(strict_types=1);

namespace App\Domain\Learner;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Idade do aprendiz no formato do instrumento: anos e meses.
 *
 * A idade aparece no cabeçalho de aplicação, no laudo e no gráfico, sempre
 * calculada na data da aplicação — nunca "hoje". Um diffForHumans() espalhado
 * pelas views produziria laudos com idades diferentes conforme o dia em que o
 * PDF fosse aberto. Por isso é regra de domínio, não helper de view.
 *
 * O cálculo usa DateTimeImmutable::diff(), que já resolve corretamente os
 * casos de borda de calendário — nascido em 31/jan avaliado em 28/fev não
 * completa o mês, porque fevereiro (mesmo bissexto) não chega ao dia 31.
 */
final class Age
{
    private function __construct(
        public readonly int $years,
        public readonly int $months,
    ) {}

    public static function between(
        DateTimeInterface|string $birthDate,
        DateTimeInterface|string $reference,
    ): self {
        $birth = self::toDate($birthDate);
        $ref = self::toDate($reference);

        if ($ref < $birth) {
            throw new InvalidArgumentException(
                'A data de referência não pode ser anterior à data de nascimento.',
            );
        }

        $diff = $birth->diff($ref);

        return new self($diff->y, $diff->m);
    }

    private static function toDate(DateTimeInterface|string $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable($value);
    }

    /**
     * "4 anos e 7 meses" · "1 ano" · "1 ano e 1 mês" · "3 anos" · "8 meses"
     */
    public function format(): string
    {
        if ($this->years === 0 && $this->months === 0) {
            return 'menos de 1 mês';
        }

        if ($this->years === 0) {
            return $this->monthsLabel($this->months);
        }

        $yearsLabel = $this->yearsLabel($this->years);

        return $this->months === 0
            ? $yearsLabel
            : "{$yearsLabel} e {$this->monthsLabel($this->months)}";
    }

    private function yearsLabel(int $years): string
    {
        return $years === 1 ? '1 ano' : "{$years} anos";
    }

    private function monthsLabel(int $months): string
    {
        return $months === 1 ? '1 mês' : "{$months} meses";
    }
}
