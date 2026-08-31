<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Support\ImageUploader;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Única porta de leitura das fotos de aprendiz.
 *
 * A foto vive no disco `local` (privado, sem symlink). A rota é assinada
 * (`URL::temporarySignedRoute`, ver Learner::photoUrl()) e, mesmo com
 * assinatura válida, ainda checa a policy — a assinatura garante que o link
 * não foi adulterado e não expirou, não que quem o abriu tem permissão.
 */
class LearnerPhotoController extends Controller
{
    private const TAMANHOS = ['padrao', 'miniatura'];

    public function show(Learner $learner, string $tamanho): HttpResponse
    {
        $this->authorize('view', $learner);

        if (! in_array($tamanho, self::TAMANHOS, true) || $learner->photo_path === null) {
            abort(404);
        }

        $path = $tamanho === 'miniatura'
            ? ImageUploader::thumbnailPathFor($learner->photo_path)
            : $learner->photo_path;

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            abort(404);
        }

        return response($disk->get($path), Response::HTTP_OK, [
            'Content-Type' => $disk->mimeType($path) ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
