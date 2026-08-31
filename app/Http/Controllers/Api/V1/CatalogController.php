<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * O catálogo do nível, para o app baixar uma vez e operar sem conexão.
 *
 * Cache-Control é `private`, nunca `public`: é conteúdo licenciado do
 * VB-MAPP e não pode ficar em cache compartilhado.
 */
class CatalogController extends Controller
{
    public function show(Request $request, int $level): JsonResponse|HttpResponse
    {
        abort_unless(in_array($level, [1, 2, 3], true), 404);

        $areas = CatalogCache::level($level);

        // ETag derivado do conteúdo: muda quando o seeder muda, e só então.
        $etag = '"'.substr(hash('sha256', $areas->toJson()), 0, 32).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response()->json([
            'level' => $level,
            'areas' => $areas->map(fn ($area) => [
                'code' => $area->code,
                'name' => $area->name,
                'short_name' => $area->short_name,
                'items' => ItemResource::collection($area->items)->resolve(),
            ])->values(),
        ])->withHeaders([
            'ETag' => $etag,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
