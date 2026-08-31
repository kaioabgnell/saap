<?php

declare(strict_types=1);

namespace App\Application\Assessment;

use App\Domain\Assessment\AssessmentStatus;
use App\Models\Assessment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancela a avaliação. Exige motivo e NÃO apaga respostas — o registro do que
 * foi apurado até ali continua, só não vira laudo.
 */
final class CancelAssessment
{
    public function handle(Assessment $assessment, string $motivo): Assessment
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new RuntimeException('Informe o motivo do cancelamento.');
        }

        return DB::transaction(function () use ($assessment, $motivo) {
            $fresco = Assessment::lockForUpdate()->findOrFail($assessment->id);

            if ($fresco->isLocked()) {
                throw new RuntimeException('Esta avaliação foi concluída e não pode ser cancelada.');
            }

            $fresco->update([
                'status' => AssessmentStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancel_reason' => $motivo,
            ]);

            return $fresco;
        });
    }
}
