<?php

declare(strict_types=1);

namespace App\Application\Schedule;

use App\Domain\Schedule\TimeSlot;
use App\Models\Appointment;

/**
 * Quais das faixas pretendidas colidem com o que já está marcado.
 *
 * Carrega a janela do banco e decide a sobreposição no domínio — a regra é do
 * TimeSlot, e escrevê-la também em SQL criaria duas verdades sobre o que é um
 * conflito. Consultar por janela é barato: um psicólogo tem dezenas de
 * agendamentos por mês, não milhares.
 */
final class DetectConflicts
{
    /**
     * @param  list<TimeSlot>  $faixas
     * @param  int|null  $ignorando  id do próprio agendamento, ao remarcar
     * @return list<ScheduleConflict>
     */
    public function para(int $userId, array $faixas, ?int $ignorando = null): array
    {
        if ($faixas === []) {
            return [];
        }

        $inicio = min(array_map(fn (TimeSlot $f) => $f->starts, $faixas));
        $fim = max(array_map(fn (TimeSlot $f) => $f->ends, $faixas));

        $candidatos = Appointment::query()
            ->with('learner')
            ->where('user_id', $userId)
            ->ativos()
            ->when($ignorando !== null, fn ($q) => $q->whereKeyNot($ignorando))
            // A janela: nada que termina antes do primeiro início, nem que
            // começa depois do último fim, pode sobrepor qualquer faixa.
            ->where('ends_at', '>', $inicio)
            ->where('starts_at', '<', $fim)
            ->orderBy('starts_at')
            ->get();

        if ($candidatos->isEmpty()) {
            return [];
        }

        $conflitos = [];

        foreach ($faixas as $faixa) {
            $colidem = $candidatos
                ->filter(fn (Appointment $a) => $faixa->overlaps($a->faixa()))
                ->values()
                ->all();

            if ($colidem !== []) {
                $conflitos[] = new ScheduleConflict($faixa, $colidem);
            }
        }

        return $conflitos;
    }
}
