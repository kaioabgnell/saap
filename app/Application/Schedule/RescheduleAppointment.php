<?php

declare(strict_types=1);

namespace App\Application\Schedule;

use App\Domain\Schedule\TimeSlot;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Move um agendamento no tempo. Mesma checagem de conflito da criação —
 * ignorando o próprio, que senão conflitaria consigo mesmo.
 *
 * Move UM. Não existe "e as próximas": a repetição criou linhas
 * independentes, e essa independência é a decisão, não um efeito colateral.
 */
final class RescheduleAppointment
{
    public function __construct(private readonly DetectConflicts $conflitos) {}

    /** @return list<ScheduleConflict> vazio quando gravou */
    public function handle(
        Appointment $appointment,
        string $startsAt,
        int $durationMinutes,
        bool $permitirConflito = false,
    ): array {
        $faixa = TimeSlot::deDuracao($startsAt, $durationMinutes);

        return DB::transaction(function () use ($appointment, $faixa, $permitirConflito) {
            $fresco = Appointment::lockForUpdate()->findOrFail($appointment->id);

            if ($fresco->status->isTerminal()) {
                throw new RuntimeException(
                    'Atendimento '.mb_strtolower($fresco->status->label()).' não pode ser remarcado.',
                );
            }

            $encontrados = $this->conflitos->para($fresco->user_id, [$faixa], ignorando: $fresco->id);

            if ($encontrados !== [] && ! $permitirConflito) {
                return $encontrados;
            }

            $fresco->update(['starts_at' => $faixa->starts, 'ends_at' => $faixa->ends]);

            $appointment->setRawAttributes($fresco->getAttributes(), sync: true);

            return [];
        });
    }
}
