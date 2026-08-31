<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ReportSnapshot;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gera o PDF do laudo a partir do snapshot congelado.
 *
 * Ao contrário do formulário (F6), este PDF é permanente: fica em
 * storage/app/private/relatorios e nunca é limpo por rotina.
 */
class GenerateReportPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const DIRETORIO = 'relatorios';

    public function __construct(public readonly int $snapshotId) {}

    public function handle(): void
    {
        try {
            $snapshot = ReportSnapshot::with('assessment.learner')->findOrFail($this->snapshotId);

            $pdf = Pdf::loadView('pdf.relatorio.documento', [
                'payload' => $snapshot->reportPayload(),
                'hash' => $snapshot->content_hash,
                'geradoEm' => $snapshot->generated_at->format('d/m/Y H:i'),
            ])->setPaper('a4', 'portrait');

            $pdf->render();
            $pdf->getCanvas()->page_text(
                478, 806, 'Página {PAGE_NUM} de {PAGE_COUNT}',
                'DejaVu Sans', 8, [0.39, 0.45, 0.55],
            );

            $caminho = sprintf(
                '%s/%d-%s.pdf',
                self::DIRETORIO,
                $snapshot->assessment_id,
                Str::slug($snapshot->assessment->learner->name) ?: 'aprendiz',
            );

            Storage::disk('local')->put($caminho, $pdf->output());

            $snapshot->update(['pdf_path' => $caminho]);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /** Nome que o psicólogo vê ao baixar. */
    public static function nomeDoArquivo(ReportSnapshot $snapshot): string
    {
        return sprintf(
            'relatorio-vbmapp-%s-%s.pdf',
            Str::slug($snapshot->assessment->learner->name) ?: 'aprendiz',
            $snapshot->generated_at->format('Y-m-d'),
        );
    }
}
