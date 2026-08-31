<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Report\BuildFormPayload;
use App\Models\Assessment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Gera o PDF do formulário parcial em fila — 45 a 65 marcos, alguns com
 * imagem, estouram o tempo de uma requisição HTTP normal.
 *
 * O status vai para o cache (chave por token), não para uma tabela: é um
 * artefato de UI de vida curta, não um dado do domínio. `GerarFormularioPdf`
 * é quem cria o token e faz o polling.
 */
class GenerateFormPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const DIRETORIO = 'temp/formularios';

    public function __construct(
        public readonly string $token,
        public readonly int $assessmentId,
        public readonly int $level,
        public readonly bool $onlyPending,
        public readonly bool $includeCriteria,
        public readonly bool $includeExamples,
        public readonly string $slugAprendiz,
    ) {}

    public function handle(BuildFormPayload $builder): void
    {
        // A fila 'sync' (usada nos testes) não intercepta exceção nenhuma —
        // ela propagaria direto para quem chamou o dispatch. $this->fail()
        // funciona nos dois modos: marca o job como falho e chama failed()
        // explicitamente, em vez de depender do worker fazer isso por fora.
        try {
            $assessment = Assessment::with('learner', 'user')->findOrFail($this->assessmentId);

            $payload = $builder->handle(
                $assessment,
                $this->level,
                $this->onlyPending,
                $this->includeCriteria,
                $this->includeExamples,
            );

            $pdf = Pdf::loadView('pdf.formulario.documento', ['payload' => $payload])
                ->setPaper('a4', 'portrait');

            // A contagem total de páginas só existe depois do render — por
            // isso o rodapé não usa {PAGE_COUNT} na própria view (a view não
            // sabe o total antecipadamente). page_text() é a API do dompdf
            // para isso: registra o texto por página, resolvido no render.
            $pdf->render();
            // A4 portrait = 595.28 x 841.89 pt. Alinhado à margem direita
            // (36px de @page ≈ 27pt), perto do rodapé.
            $pdf->getCanvas()->page_text(478, 806, 'Página {PAGE_NUM} de {PAGE_COUNT}', 'DejaVu Sans', 8, [0.39, 0.45, 0.55]);

            $nomeArquivo = sprintf(
                'formulario-%s-nivel-%d-%s.pdf',
                $this->slugAprendiz,
                $this->level,
                now()->format('Y-m-d'),
            );

            $caminho = self::DIRETORIO."/{$this->token}.pdf";
            Storage::disk('local')->put($caminho, $pdf->output());

            $this->marcarStatus(['status' => 'ready', 'path' => $caminho, 'filename' => $nomeArquivo]);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    public function failed(Throwable $e): void
    {
        $this->marcarStatus(['status' => 'failed', 'error' => $e->getMessage()]);
    }

    private function marcarStatus(array $dados): void
    {
        Cache::put(self::chaveDoToken($this->token), $dados, now()->addHour());
    }

    public static function status(string $token): ?array
    {
        return Cache::get(self::chaveDoToken($token));
    }

    public static function iniciar(string $token): void
    {
        Cache::put(self::chaveDoToken($token), ['status' => 'queued'], now()->addHour());
    }

    public static function caminhoTemporario(string $token): string
    {
        return self::DIRETORIO."/{$token}.pdf";
    }

    private static function chaveDoToken(string $token): string
    {
        return "form-pdf:{$token}";
    }
}
