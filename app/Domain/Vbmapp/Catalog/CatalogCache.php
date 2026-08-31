<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Catalog;

use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\Item;
use App\Models\Vbmapp\MaterialPage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * O catálogo é imutável em runtime: só muda por deploy com seeder novo.
 * Guardá-lo em cache evita reler 170 marcos a cada render da tela do nível.
 *
 * Duas camadas, e as duas são necessárias:
 *   1. `Cache` — atravessa requisições, evita ir ao banco.
 *   2. `CatalogMemo` — atravessa as chamadas de UMA requisição, evita ir ao
 *      driver de cache. Sem ela, os 65 ItemCards da tela do nível 3 pedem o
 *      catálogo 65 vezes; no driver `database` da produção isso é 65
 *      consultas e 65 `unserialize` do nível inteiro (~1,8 s medidos). Os
 *      testes rodam com `CACHE_STORE=array` e não enxergam esse N+1.
 *
 * Nunca cachear respostas, progresso ou qualquer dado de aplicação.
 */
final class CatalogCache
{
    private const PREFIXO = 'vbmapp.level.';

    private const PREFIXO_PAGINAS = 'vbmapp.paginas.';

    private static function memo(): CatalogMemo
    {
        return app(CatalogMemo::class);
    }

    /** Áreas do nível, com os marcos já carregados e ordenados. */
    public static function level(int $level): Collection
    {
        $memo = self::memo();

        return $memo->niveis[$level] ??= Cache::rememberForever(self::PREFIXO.$level, function () use ($level) {
            $areas = Area::query()
                ->whereHas('items', fn ($q) => $q->where('level', $level))
                ->with(['items' => fn ($q) => $q->where('level', $level)->orderBy('position')
                    ->with('stimuli')])  // sem isso, cada cartão da grade relê os estímulos
                ->orderBy('position')
                ->get();

            // Resolve a relação inversa antes de cachear. Sem isso, cada
            // $item->area na tela dispararia uma consulta — o N+1 volta pela
            // porta dos fundos, agora com o catálogo em cache.
            foreach ($areas as $area) {
                foreach ($area->items as $item) {
                    $item->setRelation('area', $area);
                }
            }

            return $areas;
        });
    }

    /**
     * Um marco pelo id, sem varrer as áreas.
     *
     * O ItemCard chamava `flatMap()->firstWhere()` — 4,3 ms por cartão, 282 ms
     * numa tela de 65. O índice é montado uma vez por requisição.
     */
    public static function item(int $level, int $itemId): ?Item
    {
        $memo = self::memo();

        $indice = $memo->marcosPorId[$level] ??= self::level($level)
            ->flatMap(fn (Area $area) => $area->items)
            ->keyBy('id');

        return $indice->get($itemId);
    }

    public static function itemCount(int $level): int
    {
        return self::level($level)->sum(fn (Area $area) => $area->items->count());
    }

    /**
     * Todas as páginas do material de um nível, para o ItemCard filtrar em
     * memória por área+posição — em vez de consultar o banco por cartão, o
     * que multiplicaria a mesma consulta por até 65 vezes na tela do nível.
     */
    public static function materialPages(int $level): Collection
    {
        $memo = self::memo();

        return $memo->paginas[$level] ??= Cache::rememberForever(
            self::PREFIXO_PAGINAS.$level,
            fn () => MaterialPage::where('level', $level)->orderBy('page_number')->get(),
        );
    }

    /** Páginas de um marco específico, por índice pronto em vez de filtro por cartão. */
    public static function materialPagesFor(int $level, int $areaId, int $position): Collection
    {
        $memo = self::memo();

        $indice = $memo->paginasPorMarco[$level] ??= self::materialPages($level)
            ->groupBy(fn (MaterialPage $p) => $p->area_id.':'.$p->item_position);

        return $indice->get($areaId.':'.$position) ?? collect();
    }

    public static function flush(): void
    {
        foreach ([1, 2, 3] as $level) {
            Cache::forget(self::PREFIXO.$level);
            Cache::forget(self::PREFIXO_PAGINAS.$level);
        }

        self::memo()->esquecer();
    }
}
