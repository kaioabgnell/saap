<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Learner;
use App\Models\User;
use App\Support\ImageUploader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Remove fotos que não pertencem mais a ninguém.
 *
 * Um upload vira órfão quando o aprendiz é apagado, quando a foto é trocada e
 * a remoção do par anterior falha, ou quando a transação de cadastro é
 * revertida depois de o arquivo já estar em disco. São poucos arquivos, mas
 * são **fotos de criança**: retenção sem base legal é justamente o que a LGPD
 * proíbe. Ver docs/operacao.md.
 *
 * A varredura é conservadora de propósito: um arquivo só é removido se não
 * estiver referenciado E tiver mais de 24 h. A janela protege o upload que
 * está no meio de uma requisição, gravado em disco antes de o `photo_path`
 * chegar ao banco.
 */
class PruneOrphanUploads extends Command
{
    protected $signature = 'saap:prune-orphan-uploads {--dry-run : Lista sem apagar}';

    protected $description = 'Remove fotos de aprendiz e de perfil sem dono no banco';

    private const IDADE_MINIMA_HORAS = 24;

    public function handle(): int
    {
        $removidos = 0;

        $removidos += $this->varrer(
            disco: 'local',
            raiz: 'aprendizes',
            referenciados: $this->caminhosDe(Learner::withTrashed()->whereNotNull('photo_path')->pluck('photo_path')),
        );

        $removidos += $this->varrer(
            disco: 'public',
            raiz: 'perfis',
            referenciados: $this->caminhosDe(User::whereNotNull('photo_path')->pluck('photo_path')),
        );

        $this->info($this->option('dry-run')
            ? "{$removidos} arquivo(s) órfão(s) encontrado(s) — nada foi apagado."
            : "{$removidos} arquivo(s) órfão(s) removido(s).");

        return self::SUCCESS;
    }

    /** Cada foto no banco responde por dois arquivos: a foto e a miniatura. */
    private function caminhosDe(iterable $caminhos): array
    {
        $tudo = [];

        foreach ($caminhos as $caminho) {
            $tudo[$caminho] = true;
            $tudo[ImageUploader::thumbnailPathFor($caminho)] = true;
        }

        return $tudo;
    }

    private function varrer(string $disco, string $raiz, array $referenciados): int
    {
        $storage = Storage::disk($disco);

        if (! $storage->exists($raiz)) {
            return 0;
        }

        $limite = now()->subHours(self::IDADE_MINIMA_HORAS)->timestamp;
        $removidos = 0;

        foreach ($storage->allFiles($raiz) as $arquivo) {
            if (isset($referenciados[$arquivo]) || $storage->lastModified($arquivo) >= $limite) {
                continue;
            }

            $this->line("  órfão: {$disco}:{$arquivo}");

            if (! $this->option('dry-run')) {
                $storage->delete($arquivo);
            }

            $removidos++;
        }

        return $removidos;
    }
}
