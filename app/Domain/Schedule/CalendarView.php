<?php

declare(strict_types=1);

namespace App\Domain\Schedule;

/**
 * As três visões da agenda, e a unidade em que cada uma anda.
 *
 * O mês é o padrão: é a visão de chegada, no formato que o Google Calendar
 * acostumou todo mundo a ver primeiro.
 */
enum CalendarView: string
{
    case Mes = 'mes';
    case Semana = 'semana';
    case Dia = 'dia';

    public function label(): string
    {
        return match ($this) {
            self::Mes => 'Mês',
            self::Semana => 'Semana',
            self::Dia => 'Dia',
        };
    }

    /** A unidade em que os botões `‹ ›` andam nesta visão. */
    public function unidade(): string
    {
        return match ($this) {
            self::Mes => 'month',
            self::Semana => 'week',
            self::Dia => 'day',
        };
    }
}
