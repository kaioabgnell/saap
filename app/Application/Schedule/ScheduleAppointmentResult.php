<?php

declare(strict_types=1);

namespace App\Application\Schedule;

use App\Models\Appointment;

/**
 * Ou gravou, ou trouxe os conflitos para a tela perguntar. Nunca os dois.
 */
final class ScheduleAppointmentResult
{
    /**
     * @param  list<Appointment>  $criados
     * @param  list<ScheduleConflict>  $conflitos
     */
    private function __construct(
        public readonly array $criados,
        public readonly array $conflitos,
    ) {}

    /** @param  list<Appointment>  $criados */
    public static function gravado(array $criados): self
    {
        return new self($criados, []);
    }

    /** @param  list<ScheduleConflict>  $conflitos */
    public static function pendenteDeConfirmacao(array $conflitos): self
    {
        return new self([], $conflitos);
    }

    public function precisaConfirmacao(): bool
    {
        return $this->criados === [];
    }
}
