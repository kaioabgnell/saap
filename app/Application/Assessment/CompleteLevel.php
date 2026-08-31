<?php

declare(strict_types=1);

namespace App\Application\Assessment;

use App\Domain\Assessment\LevelStatus;
use App\Models\AssessmentLevel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Conclui um nível. A conclusão da AVALIAÇÃO — com travamento e relatório —
 * é da F7; aqui só o nível.
 */
final class CompleteLevel
{
    public function handle(AssessmentLevel $level): AssessmentLevel
    {
        if ($level->assessment->isLocked()) {
            throw new RuntimeException('Esta avaliação foi concluída e não pode ser alterada.');
        }

        return DB::transaction(function () use ($level) {
            $fresco = AssessmentLevel::lockForUpdate()->findOrFail($level->id);

            // Revalidação no servidor: um botão habilitado por tela
            // desatualizada não pode concluir um nível incompleto.
            if (! $fresco->isComplete()) {
                throw new RuntimeException(
                    "O nível {$fresco->level} tem {$fresco->answered_count} de {$fresco->total_count} marcos respondidos."
                );
            }

            $fresco->update([
                'status' => LevelStatus::Completed->value,
                'completed_at' => now(),
            ]);

            return $fresco;
        });
    }
}
