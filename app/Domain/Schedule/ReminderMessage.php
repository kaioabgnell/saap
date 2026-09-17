<?php

declare(strict_types=1);

namespace App\Domain\Schedule;

use DateTimeInterface;

/**
 * O texto do lembrete de atendimento.
 *
 * Mora no domínio, e não na view, por dois motivos: ele vai embora para a
 * família — é a única coisa que o sistema escreve para fora — e precisa de
 * teste. Um lembrete com dia da semana errado manda uma criança à clínica no
 * dia errado.
 *
 * Os nomes de dia vêm do CalendarLabels, a mesma tabela que a agenda usa —
 * o lembrete e a tela precisam falar do mesmo dia com a mesma palavra.
 */
final class ReminderMessage
{
    /**
     * @param  string|null  $clinica  Nome da clínica, quando houver
     * @param  string  $psicologo  Usado quando não há clínica cadastrada
     */
    public static function montar(
        string $aprendiz,
        DateTimeInterface $quando,
        ?string $clinica,
        string $psicologo,
    ): string {
        $onde = self::onde($clinica, $psicologo);
        $dia = CalendarLabels::diaDaSemana($quando);
        $data = $quando->format('d/m');
        $hora = self::hora($quando);

        return "Olá! Lembrando do atendimento de {$aprendiz} {$onde}, "
            ."{$dia} ({$data}) às {$hora}. Qualquer imprevisto, é só avisar.";
    }

    private static function onde(?string $clinica, string $psicologo): string
    {
        $clinica = $clinica === null ? '' : trim($clinica);

        return $clinica === '' ? "com {$psicologo}" : "na {$clinica}";
    }

    /** "15h" na hora cheia, "15h30" fora dela — como se fala, não como se grava. */
    private static function hora(DateTimeInterface $quando): string
    {
        $minutos = $quando->format('i');

        return $minutos === '00'
            ? $quando->format('G').'h'
            : $quando->format('G').'h'.$minutos;
    }
}
