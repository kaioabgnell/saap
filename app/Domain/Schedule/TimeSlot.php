<?php

declare(strict_types=1);

namespace App\Domain\Schedule;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Uma faixa de tempo — e a regra do conflito de horário.
 *
 * A regra mora aqui, e não numa cláusula SQL solta, porque ela tem uma
 * sutileza que precisa de teste próprio: as desigualdades são ESTRITAS.
 * 15h–16h e 16h–17h não conflitam. Encostar não é sobrepor, e alertar nesse
 * caso treinaria a psicóloga a ignorar o alerta — que é o pior resultado
 * possível para um aviso.
 */
final class TimeSlot
{
    public readonly DateTimeImmutable $starts;

    public readonly DateTimeImmutable $ends;

    public function __construct(
        DateTimeInterface|string $starts,
        DateTimeInterface|string $ends,
    ) {
        $this->starts = self::toDateTime($starts);
        $this->ends = self::toDateTime($ends);

        if ($this->ends <= $this->starts) {
            throw new InvalidArgumentException('O fim do atendimento precisa ser depois do início.');
        }
    }

    /** Construtor do formulário: início mais uma duração em minutos. */
    public static function deDuracao(DateTimeInterface|string $starts, int $minutos): self
    {
        if ($minutos < 1) {
            throw new InvalidArgumentException('A duração precisa ser de pelo menos 1 minuto.');
        }

        $inicio = self::toDateTime($starts);

        return new self($inicio, $inicio->modify("+{$minutos} minutes"));
    }

    /**
     * Há sobreposição? A pergunta que a agenda faz antes de gravar.
     *
     *     existente.starts < novo.ends  E  existente.ends > novo.starts
     */
    public function overlaps(self $outro): bool
    {
        return $this->starts < $outro->ends && $this->ends > $outro->starts;
    }

    /** Duração em minutos. */
    public function duration(): int
    {
        return intdiv($this->ends->getTimestamp() - $this->starts->getTimestamp(), 60);
    }

    /**
     * A mesma faixa N semanas adiante — a repetição simples da agenda.
     *
     * Anda em semanas de calendário, não em 604.800 segundos: se um dia o
     * país voltar a ter horário de verão, quinta às 15h continua sendo
     * quinta às 15h.
     */
    public function deslocadoEmSemanas(int $semanas): self
    {
        if ($semanas === 0) {
            return $this;
        }

        return new self(
            $this->starts->modify("+{$semanas} weeks"),
            $this->ends->modify("+{$semanas} weeks"),
        );
    }

    private static function toDateTime(DateTimeInterface|string $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable($value);
    }
}
