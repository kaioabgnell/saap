<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\MaterialPage;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Importa as páginas do material dos níveis 2 e 3 — inteiras, sem recorte.
 *
 * A curadoria em estímulos individuais desses níveis está fora do escopo da
 * v1: o modelo já suporta, basta rodar o pipeline da F4 depois.
 */
class ImportVbmappPages extends Command
{
    protected $signature = 'vbmapp:import-pages {--levels=2,3 : Níveis a importar, separados por vírgula}';

    protected $description = 'Importa as páginas do material de aplicação dos níveis 2 e 3';

    public function handle(): int
    {
        $niveis = array_map('intval', explode(',', (string) $this->option('levels')));
        $caminho = storage_path('app/vbmapp/paginas-niveis-2-3.json');

        if (! is_file($caminho)) {
            $this->error(
                "Páginas não renderizadas. Rode antes:\n  .venv-tools/bin/python tools/import_material_pages.py"
            );

            return self::FAILURE;
        }

        $dados = json_decode((string) file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR);
        $areas = Area::pluck('id', 'code')->all();

        $importadas = 0;

        foreach ($niveis as $level) {
            MaterialPage::where('level', $level)->delete();

            foreach ($dados['paginas'] as $pagina) {
                if ((int) $pagina['level'] !== $level) {
                    continue;
                }

                $marcos = $pagina['marcos'] ?? [];

                if ($marcos === []) {
                    // Página sem marco identificado (capa) — ainda vale
                    // importar, para não perder a numeração de página.
                    MaterialPage::create([
                        'level' => $level,
                        'area_id' => null,
                        'item_position' => null,
                        'image_path' => $pagina['image_path'],
                        'page_number' => $pagina['page_number'],
                        'source_file' => $pagina['source_file'],
                    ]);
                    $importadas++;

                    continue;
                }

                // Uma página pode anunciar mais de um marco ("TATO 6 e 7"):
                // cria uma linha por marco, todas apontando para a mesma imagem.
                foreach ($marcos as $marco) {
                    $areaId = $areas[$marco['area_code']] ?? null;

                    if ($areaId === null) {
                        throw new RuntimeException("Área desconhecida: {$marco['area_code']}");
                    }

                    MaterialPage::create([
                        'level' => $level,
                        'area_id' => $areaId,
                        'item_position' => $marco['item_position'],
                        'image_path' => $pagina['image_path'],
                        'page_number' => $pagina['page_number'],
                        'source_file' => $pagina['source_file'],
                    ]);
                    $importadas++;
                }
            }
        }

        CatalogCache::flush();

        $this->info("{$importadas} páginas importadas para os níveis ".implode(', ', $niveis).'.');

        return self::SUCCESS;
    }
}
