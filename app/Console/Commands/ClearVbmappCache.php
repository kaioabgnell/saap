<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Vbmapp\Catalog\CatalogCache;
use Illuminate\Console\Command;

class ClearVbmappCache extends Command
{
    protected $signature = 'vbmapp:cache-clear';

    protected $description = 'Invalida o cache do catálogo VB-MAPP';

    public function handle(): int
    {
        CatalogCache::flush();
        $this->info('Cache do catálogo invalidado.');

        return self::SUCCESS;
    }
}
