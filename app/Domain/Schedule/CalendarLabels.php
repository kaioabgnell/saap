<?php

declare(strict_types=1);

namespace App\Domain\Schedule;

use DateTimeInterface;

/**
 * Os nomes de dia e mês em português, como tabela fixa.
 *
 * Não vêm do locale: o `app.locale` do projeto é 'en' e mudá-lo mexeria nas
 * mensagens de validação de todo o sistema, para resolver um problema de
 * calendário. Também não vêm de `strftime` e parentes, que mudam de
 * comportamento conforme o que está instalado na máquina.
 *
 * Uma agenda que escreve "Thursday" ou "setembro" com a inicial errada faz a
 * psicóloga desconfiar do resto da tela.
 */
final class CalendarLabels
{
    /** ISO-8601: 1 = segunda ... 7 = domingo */
    private const DIAS = [
        1 => 'segunda-feira',
        2 => 'terça-feira',
        3 => 'quarta-feira',
        4 => 'quinta-feira',
        5 => 'sexta-feira',
        6 => 'sábado',
        7 => 'domingo',
    ];

    private const DIAS_CURTOS = [
        1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom',
    ];

    private const MESES = [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
        5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
        9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
    ];

    /** As sete colunas da grade do mês, de domingo a sábado. */
    public const CABECALHO_DA_SEMANA = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    public static function diaDaSemana(DateTimeInterface $data): string
    {
        return self::DIAS[(int) $data->format('N')];
    }

    public static function diaCurto(DateTimeInterface $data): string
    {
        return self::DIAS_CURTOS[(int) $data->format('N')];
    }

    public static function mes(int $mes): string
    {
        return self::MESES[$mes];
    }

    /** "Setembro de 2026" — título da visão de mês. */
    public static function mesEAno(DateTimeInterface $data): string
    {
        $mes = self::MESES[(int) $data->format('n')];

        return mb_strtoupper(mb_substr($mes, 0, 1)).mb_substr($mes, 1).' de '.$data->format('Y');
    }

    /** "quinta-feira, 10 de setembro" — título da visão de dia. */
    public static function porExtenso(DateTimeInterface $data): string
    {
        return self::diaDaSemana($data).', '.(int) $data->format('j')
            .' de '.self::MESES[(int) $data->format('n')];
    }

    /** "8 a 14 de setembro de 2026" — título da visão de semana. */
    public static function intervalo(DateTimeInterface $de, DateTimeInterface $ate): string
    {
        $mesDe = self::MESES[(int) $de->format('n')];
        $mesAte = self::MESES[(int) $ate->format('n')];
        $ano = $ate->format('Y');

        if ($de->format('Y-n') === $ate->format('Y-n')) {
            return (int) $de->format('j').' a '.(int) $ate->format('j')." de {$mesAte} de {$ano}";
        }

        return (int) $de->format('j')." de {$mesDe} a ".(int) $ate->format('j')." de {$mesAte} de {$ano}";
    }
}
