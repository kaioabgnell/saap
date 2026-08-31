<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\GenerateFormPdf;
use App\Models\Assessment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Baixa o PDF do formulário depois que a fila termina.
 *
 * O token prova que o pedido passou pelo PrintForm deste psicólogo — a
 * autorização em cima disso confirma que a avaliação também é dele.
 */
class PrintFormController extends Controller
{
    public function show(Assessment $assessment, string $token): Response
    {
        $this->authorize('view', $assessment);

        $info = GenerateFormPdf::status($token);

        if ($info === null || $info['status'] !== 'ready') {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($info['path'])) {
            abort(404);
        }

        return response($disk->get($info['path']), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$info['filename'].'"',
        ]);
    }
}
