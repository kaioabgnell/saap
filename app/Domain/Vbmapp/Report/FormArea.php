<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Report;

final class FormArea
{
    /** @param list<FormItem> $items */
    public function __construct(
        public readonly string $name,
        public readonly string $shortName,
        public readonly array $items,
    ) {}
}
