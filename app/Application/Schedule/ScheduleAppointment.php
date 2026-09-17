<?php

declare(strict_types=1);

namespace App\Application\Schedule;

use App\Domain\Schedule\AppointmentStatus;
use App\Domain\Schedule\TimeSlot;
use App\Models\Appointment;
use App\Models\Learner;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Marca de 1 a 52 atendimentos — a porta única de criação da agenda.
 *
 * Duas regras que valem a pena ler juntas:
 *
 *   O conflito AVISA, não impede. Atender dois irmãos na mesma hora é decisão
 *   clínica legítima; bloquear obrigaria a psicóloga a mentir para o sistema,
 *   marcando 15h05 para o segundo.
 *
 *   A repetição é SIMPLES. "Por N semanas" cria N linhas independentes — não
 *   existe série, e remarcar uma não toca nas outras. Por isso a confirmação
 *   do conflito vale pelo lote inteiro: perguntar oito vezes seria transformar
 *   um aviso útil em ruído.
 */
final class ScheduleAppointment
{
    public const DURACAO_PADRAO = 50;

    public const MAXIMO_DE_SEMANAS = 52;

    public function __construct(private readonly DetectConflicts $conflitos) {}

    public function handle(ScheduleAppointmentCommand $command): ScheduleAppointmentResult
    {
        $faixas = $this->ocorrencias($command);

        // Só aprendiz cadastrado, e só do próprio psicólogo. A agenda nunca
        // aceita nome digitado — ver "Cadastro rápido" na spec.
        $learner = Learner::where('user_id', $command->userId)
            ->findOr($command->learnerId, fn () => throw new RuntimeException(
                'Este aprendiz não está cadastrado nesta conta.',
            ));

        return DB::transaction(function () use ($command, $faixas, $learner) {
            // Revalidação dentro da transação: uma tela aberta há dez minutos
            // não pode gravar sobre um horário que acabou de ser ocupado.
            // Fecha a janela do dado velho — uma corrida entre dois cliques
            // simultâneos ainda cairia no comportamento que o sistema já
            // permite mediante confirmação, e não no que ele proíbe.
            $encontrados = $this->conflitos->para($command->userId, $faixas);

            if ($encontrados !== [] && ! $command->permitirConflito) {
                return ScheduleAppointmentResult::pendenteDeConfirmacao($encontrados);
            }

            $criados = [];

            foreach ($faixas as $faixa) {
                $criados[] = Appointment::create([
                    'user_id' => $command->userId,
                    'learner_id' => $learner->id,
                    'starts_at' => $faixa->starts,
                    'ends_at' => $faixa->ends,
                    'status' => AppointmentStatus::Scheduled->value,
                    'booking_note' => $this->limpar($command->bookingNote),
                ]);
            }

            return ScheduleAppointmentResult::gravado($criados);
        });
    }

    /** @return list<TimeSlot> */
    private function ocorrencias(ScheduleAppointmentCommand $command): array
    {
        if ($command->repeatWeeks < 1 || $command->repeatWeeks > self::MAXIMO_DE_SEMANAS) {
            throw new RuntimeException('A repetição vai de 1 a '.self::MAXIMO_DE_SEMANAS.' semanas.');
        }

        $primeira = TimeSlot::deDuracao($command->startsAt, $command->durationMinutes);

        return array_map(
            fn (int $semana) => $primeira->deslocadoEmSemanas($semana),
            range(0, $command->repeatWeeks - 1),
        );
    }

    private function limpar(?string $texto): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : $texto;
    }
}
