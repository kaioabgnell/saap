<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

enum LevelStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Em andamento',
            self::Completed => 'Concluído',
        };
    }
}
