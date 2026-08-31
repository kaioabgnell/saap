<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\Item;
use App\Models\Vbmapp\MaterialPage;
use App\Models\Vbmapp\Stimulus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Traz a saída de tools/slice_material.py para o banco.
 *
 * Os recortes entram como CANDIDATOS: só viram acervo depois de aprovados e
 * rotulados na tela de curadoria. O recorte automático acerta a maioria, mas
 * erra o suficiente para não poder ir direto — junta dois estímulos num
 * recorte, corta figura pela metade.
 */
final class MaterialImporter
{
    /** @return array{paginas: int, estimulos: int, sem_rotulo: int} */
    public function import(int $level): array
    {
        $recortes = $this->lerJson(storage_path("app/vbmapp/estimulos-nivel-{$level}.json"));
        $sugestoes = $this->lerSugestoes($level);

        $areas = Area::pluck('id', 'code')->all();

        return DB::transaction(function () use ($level, $recortes, $sugestoes, $areas) {
            $paginas = $this->importarPaginas($level, $recortes['paginas'] ?? [], $areas);
            [$estimulos, $semRotulo] = $this->importarEstimulos($recortes['recortes'] ?? [], $sugestoes, $areas);

            return ['paginas' => $paginas, 'estimulos' => $estimulos, 'sem_rotulo' => $semRotulo];
        });
    }

    private function lerJson(string $caminho): array
    {
        if (! is_file($caminho)) {
            throw new RuntimeException(
                "Recortes não gerados. Rode antes:\n  .venv-tools/bin/python tools/slice_material.py --level 1"
            );
        }

        return json_decode((string) file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, string> */
    private function lerSugestoes(int $level): array
    {
        $caminho = base_path("database/data/vbmapp-estimulos-nivel-{$level}.json");

        if (! is_file($caminho)) {
            return [];
        }

        $dados = json_decode((string) file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR);

        return $dados['sugestoes'] ?? [];
    }

    /** @param list<array<string, mixed>> $paginas */
    private function importarPaginas(int $level, array $paginas, array $areas): int
    {
        MaterialPage::where('level', $level)->delete();

        foreach ($paginas as $pagina) {
            $primeiro = $pagina['marcos'][0] ?? null;

            MaterialPage::create([
                'level' => $level,
                'area_id' => $primeiro === null ? null : ($areas[$primeiro['area_code']] ?? null),
                'item_position' => $primeiro['item_position'] ?? null,
                'image_path' => $pagina['image_path'],
                'page_number' => $pagina['page_number'],
                'source_file' => $pagina['source_file'],
            ]);
        }

        return count($paginas);
    }

    /**
     * @param  list<array<string, mixed>>  $recortes
     * @param  array<string, string>  $sugestoes
     * @return array{int, int}
     */
    private function importarEstimulos(array $recortes, array $sugestoes, array $areas): array
    {
        $itens = Item::whereIn('response_type', ['counter_stimuli'])->get()
            ->keyBy(fn (Item $i) => $this->chave($i, $areas));

        $criados = 0;
        $semRotulo = 0;
        $porItem = [];

        foreach ($recortes as $recorte) {
            $curto = substr($recorte['id'], 3); // "n1-p003-01" -> "p003-01"
            $rotulo = trim((string) ($sugestoes[$curto] ?? ''));

            if ($rotulo === '') {
                $semRotulo++;
            }

            // O mesmo recorte pode servir a mais de um marco — a página
            // "TATO 1 e 2" atende os dois.
            foreach ($recorte['marcos'] ?? [] as $marco) {
                $chave = "{$marco['area_code']}:{$marco['item_position']}";
                $item = $itens->get($chave);

                if ($item === null) {
                    continue;
                }

                $porItem[$item->id] = ($porItem[$item->id] ?? 0) + 1;

                Stimulus::updateOrCreate(
                    ['item_id' => $item->id, 'image_path' => $recorte['image_path']],
                    [
                        'label' => $rotulo,
                        'source_page' => $recorte['source_page'],
                        'position' => $porItem[$item->id],
                    ],
                );

                $criados++;
            }
        }

        return [$criados, $semRotulo];
    }

    private function chave(Item $item, array $areas): string
    {
        $code = array_search($item->area_id, $areas, true);

        return "{$code}:{$item->position}";
    }
}
