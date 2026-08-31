<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Remove os PDFs de formulário parcial com mais de 24 h.
 *
 * São artefatos de download de vida curta — o rascunho de hoje não serve
 * amanhã, já que o progresso mudou. O status no cache (1 h) já expirou bem
 * antes disso; esta limpeza é só do arquivo em disco.
 */
class PruneFormPdfs extends Command
{
    protected $signature = 'vbmapp:prune-form-pdfs';

    protected $description = 'Remove PDFs de formulário temporários com mais de 24 horas';

    private const DIRETORIO = 'temp/formularios';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $limite = now()->subHours(24)->timestamp;
        $removidos = 0;

        foreach ($disk->files(self::DIRETORIO) as $arquivo) {
            if ($disk->lastModified($arquivo) < $limite) {
                $disk->delete($arquivo);
                $removidos++;
            }
        }

        $this->info("{$removidos} formulário(s) temporário(s) removido(s).");

        return self::SUCCESS;
    }
}
