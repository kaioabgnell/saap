<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Catalog;

use Illuminate\Support\Collection;

/**
 * Memória do catálogo dentro de uma requisição.
 *
 * Registrado como `scoped` no container: zera a cada requisição (e a cada
 * teste, que reconstrói a aplicação), sem o risco de um `static` guardar
 * catálogo velho entre uma requisição e outra.
 *
 * Existe porque `Cache::rememberForever` **não** memoriza nada: com o driver
 * de produção (`database`), cada chamada é uma consulta mais o `unserialize`
 * do nível inteiro. A tela do nível monta um ItemCard por marco, e cada um
 * pedia o catálogo de novo — 65 idas ao banco numa tela só.
 */
final class CatalogMemo
{
    /** @var array<int, Collection> */
    public array $niveis = [];

    /** @var array<int, Collection> marcos do nível indexados por id */
    public array $marcosPorId = [];

    /** @var array<int, Collection> */
    public array $paginas = [];

    /** @var array<int, Collection> páginas agrupadas por "areaId:posicao" */
    public array $paginasPorMarco = [];

    public function esquecer(): void
    {
        $this->niveis = [];
        $this->marcosPorId = [];
        $this->paginas = [];
        $this->paginasPorMarco = [];
    }
}
