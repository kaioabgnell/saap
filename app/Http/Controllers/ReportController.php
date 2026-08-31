<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\GenerateReportPdf;
use App\Models\Assessment;
use App\Models\ReportAccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * O relatório só existe depois da conclusão.
 *
 * Antes disso a rota devolve 404 — não 403: durante o preenchimento o
 * relatório simplesmente não existe como recurso, e a interface nem oferece
 * o link.
 */
class ReportController extends Controller
{
    public function show(Assessment $assessment, Request $request): View
    {
        $this->authorize('view', $assessment);

        $snapshot = $assessment->reportSnapshot;

        abort_if($snapshot === null, 404);

        // Laudo de criança é dado sensível: quem leu fica registrado.
        ReportAccessLog::registrar($snapshot, $request->user(), ReportAccessLog::NA_TELA, $request);

        return view('assessments.relatorio', [
            'assessment' => $assessment,
            'snapshot' => $snapshot,
            'payload' => $snapshot->reportPayload(),
        ]);
    }

    public function download(Assessment $assessment, Request $request): HttpResponse
    {
        $this->authorize('view', $assessment);

        $snapshot = $assessment->reportSnapshot;

        abort_if($snapshot === null || $snapshot->pdf_path === null, 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($snapshot->pdf_path), 404);

        // Registra só depois de confirmar que o arquivo existe: um 404 não é
        // acesso ao laudo, e poluir o registro com eles esconde o que importa.
        ReportAccessLog::registrar($snapshot, $request->user(), ReportAccessLog::DOWNLOAD, $request);

        return response($disk->get($snapshot->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.GenerateReportPdf::nomeDoArquivo($snapshot).'"',
        ]);
    }
}
