<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Progress;

/**
 * Monta o progresso nas três granularidades que a interface exibe.
 *
 * Recebe números já apurados — quem consulta o banco é a camada de aplicação.
 * Assim a regra de "o que conta como respondido" fica testável sem banco.
 */
final class ProgressCounter
{
    /** Total de marcos por nível. Soma 170. */
    public const TOTAL_POR_NIVEL = [1 => 45, 2 => 60, 3 => 65];

    public const TOTAL_GERAL = 170;

    public const MARCOS_POR_AREA = 5;

    public static function totalForLevel(int $level): int
    {
        return self::TOTAL_POR_NIVEL[$level]
            ?? throw new \InvalidArgumentException("Nível inválido: {$level}.");
    }

    public function forLevel(int $answered, int $level): Progress
    {
        return new Progress($answered, self::totalForLevel($level));
    }

    public function forArea(int $answered): Progress
    {
        return new Progress($answered, self::MARCOS_POR_AREA);
    }

    /**
     * O denominador da avaliação é a soma dos níveis INICIADOS, não 170 fixo:
     * um aprendiz avaliado só no nível 2 chega a 100% com 60 marcos. Sem
     * nenhum nível iniciado, cai para os 170 como referência informativa.
     *
     * @param  list<int>  $startedLevels
     */
    public function forAssessment(int $answered, array $startedLevels): Progress
    {
        if ($startedLevels === []) {
            return new Progress($answered, self::TOTAL_GERAL);
        }

        $total = array_sum(array_map(self::totalForLevel(...), $startedLevels));

        return new Progress($answered, $total);
    }
}
