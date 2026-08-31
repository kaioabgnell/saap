<?php

declare(strict_types=1);

namespace App\Application\Assessment;

use App\Domain\Assessment\AssessmentStatus;
use App\Domain\Assessment\LevelStatus;
use App\Domain\Vbmapp\Progress\ProgressCounter;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Inicia um nível da avaliação.
 *
 * Níveis são independentes: começar pelo 2 ou pelo 3 é caminho normal, não
 * exceção. Nada aqui exige que o nível 1 tenha sido feito.
 */
final class StartLevel
{
    public function handle(Assessment $assessment, int $level): AssessmentLevel
    {
        if ($assessment->isLocked()) {
            throw new RuntimeException('Esta avaliação foi concluída e não pode ser alterada.');
        }

        // Caminho rápido. A tela do nível chama isto a cada carregamento, e o
        // nível quase sempre já existe: abrir transação e travar a linha nesse
        // caso custa três idas ao banco e, pior, põe um lock numa linha quente
        // enquanto o psicólogo grava respostas. A transação abaixo continua
        // guardando a criação concorrente — só não roda quando já não há o que
        // criar.
        $jaIniciado = AssessmentLevel::where('assessment_id', $assessment->id)
            ->where('level', $level)
            ->first();

        if ($jaIniciado !== null) {
            return $jaIniciado;
        }

        return DB::transaction(function () use ($assessment, $level) {
            $existente = AssessmentLevel::where('assessment_id', $assessment->id)
                ->where('level', $level)
                ->lockForUpdate()
                ->first();

            if ($existente !== null) {
                return $existente;
            }

            $nivel = AssessmentLevel::create([
                'assessment_id' => $assessment->id,
                'level' => $level,
                'status' => LevelStatus::InProgress->value,
                'started_at' => now(),
                'answered_count' => 0,
                'total_count' => ProgressCounter::totalForLevel($level),
                'score_total' => 0,
            ]);

            // O primeiro nível iniciado tira a avaliação de "não iniciada".
            if ($assessment->status === AssessmentStatus::NotStarted) {
                $assessment->update([
                    'status' => AssessmentStatus::InProgress->value,
                    'started_at' => now(),
                ]);
            }

            return $nivel;
        });
    }
}
