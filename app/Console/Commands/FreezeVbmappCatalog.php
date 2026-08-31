<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Congela o catálogo revisado em database/data/vbmapp-catalogo.json.
 *
 * Esse arquivo é versionado no git e é o dado mais valioso do projeto: dele
 * saem os limiares que decidem toda pontuação. O congelamento carimba quem
 * revisou e quando — ou, na ausência disso, marca a revisão como pendente,
 * para que ninguém confunda saída de parser com catálogo conferido.
 */
class FreezeVbmappCatalog extends Command
{
    protected $signature = 'vbmapp:freeze
                            {--revisor= : Nome de quem fez a revisão clínica}
                            {--pendente : Congela sem revisão, apenas para destravar o desenvolvimento}';

    protected $description = 'Congela o catálogo em database/data/vbmapp-catalogo.json';

    public function handle(): int
    {
        $origem = storage_path('app/vbmapp/catalogo.json');

        if (! is_file($origem)) {
            $this->error('Catálogo não gerado. Rode antes: php artisan vbmapp:import-manual');

            return self::FAILURE;
        }

        $revisor = $this->option('revisor');

        if ($revisor === null && ! $this->option('pendente')) {
            $this->error('Informe --revisor="Nome" ou use --pendente para congelar sem revisão clínica.');

            return self::FAILURE;
        }

        $dados = json_decode((string) file_get_contents($origem), true, 512, JSON_THROW_ON_ERROR);

        $dados['revisao_clinica'] = $revisor !== null
            ? ['status' => 'concluida', 'revisor' => $revisor, 'data' => now()->toDateString()]
            : ['status' => 'pendente', 'revisor' => null, 'data' => null];

        $dados['congelado_em'] = now()->toIso8601String();

        $destino = base_path('database/data/vbmapp-catalogo.json');
        @mkdir(dirname($destino), 0775, true);
        file_put_contents($destino, json_encode(
            $dados,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        $this->info('Congelado em database/data/vbmapp-catalogo.json');

        if ($revisor !== null) {
            $this->line("  revisão clínica: {$revisor}, ".now()->toDateString());
        } else {
            $this->warn('  revisão clínica: PENDENTE — não emita laudo com este catálogo');
        }

        return self::SUCCESS;
    }
}
