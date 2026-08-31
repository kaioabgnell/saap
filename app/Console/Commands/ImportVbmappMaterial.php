<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Models\Vbmapp\Item;
use App\Support\Content\MaterialImporter;
use Illuminate\Console\Command;
use RuntimeException;

class ImportVbmappMaterial extends Command
{
    protected $signature = 'vbmapp:import-material {--level=1 : Nível do material}';

    protected $description = 'Importa os recortes do material de aplicação como candidatos a estímulo';

    public function handle(MaterialImporter $importer): int
    {
        $level = (int) $this->option('level');

        try {
            $resultado = $importer->import($level);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        CatalogCache::flush();

        $this->info(sprintf(
            '%d páginas e %d estímulos importados no nível %d.',
            $resultado['paginas'], $resultado['estimulos'], $level,
        ));

        if ($resultado['sem_rotulo'] > 0) {
            $this->warn("{$resultado['sem_rotulo']} recortes sem rótulo — resolva em /admin/estimulos");
        }

        $this->newLine();
        $this->line('<comment>Cobertura por marco</comment>');

        foreach (Item::with('area')->where('level', $level)->where('response_type', 'counter_stimuli')
            ->orderBy('area_id')->orderBy('position')->get() as $item) {
            $total = $item->stimuli()->count();
            $rotulados = $item->stimuli()->where('label', '<>', '')->count();
            $suficiente = $rotulados >= $item->threshold_full ? 'ok  ' : 'FALTA';

            $this->line(sprintf(
                '  %s %-10s %-2d  %2d rotulados de %2d recortes  (precisa %d)',
                $suficiente, $item->area->code, $item->position, $rotulados, $total, $item->threshold_full,
            ));
        }

        return self::SUCCESS;
    }
}
