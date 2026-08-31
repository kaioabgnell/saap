<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Content\ManualImporter;
use App\Support\Content\ThresholdInferrer;
use Database\Seeders\VbmappAreaSeeder;
use Illuminate\Console\Command;

/**
 * Monta o catálogo VB-MAPP a partir do manual traduzido.
 *
 * A saída é storage/app/vbmapp/catalogo.json — arquivo intermediário, legível
 * e revisável. É ele que a psicóloga revisa, nunca o banco. Só depois de
 * revisado o catálogo é congelado em database/data/vbmapp-catalogo.json.
 */
class ImportVbmappManual extends Command
{
    protected $signature = 'vbmapp:import-manual
                            {--report : Só exibe o relatório de cobertura, sem gravar}';

    protected $description = 'Importa os 170 marcos do manual VB-MAPP para catalogo.json';

    private const MANUAL = 'vbmapp/manual.txt';

    private const SAIDA = 'vbmapp/catalogo.json';

    private const CORRECOES = 'database/data/vbmapp-correcoes.json';

    public function handle(): int
    {
        $manual = storage_path('app/'.self::MANUAL);

        if (! is_file($manual)) {
            $this->error("Manual não extraído. Rode primeiro:\n  .venv-tools/bin/python tools/extract_manual.py");

            return self::FAILURE;
        }

        $resultado = ManualImporter::fromFile($manual)->parse();
        $blocos = $this->aplicarCorrecoes($resultado['blocos']);

        $inferrer = new ThresholdInferrer;
        $catalogo = [];

        foreach ($blocos as $chave => $bloco) {
            $final = [...$bloco, ...$inferrer->infer($bloco)];

            // Correções em campos que o infer() recalcula (response_type,
            // thresholds, scoring_mode) seriam sobrescritas pela inferência —
            // o infer() roda depois e vence por último. _pos_inferencia
            // reaplica esses campos específicos por cima do resultado final.
            foreach ($bloco['_pos_inferencia'] ?? [] as $campo) {
                $final[$campo] = $bloco[$campo];
            }
            unset($final['_pos_inferencia']);

            $catalogo[$chave] = $final;
        }

        $this->relatorio($catalogo, $resultado['ignorados']);

        if ($this->option('report')) {
            return self::SUCCESS;
        }

        $destino = storage_path('app/'.self::SAIDA);
        @mkdir(dirname($destino), 0775, true);
        file_put_contents($destino, json_encode(
            ['gerado_em' => now()->toIso8601String(), 'marcos' => $catalogo],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        $this->newLine();
        $this->info('Catálogo gravado em storage/app/'.self::SAIDA);
        $this->line('Próximo passo: revisão clínica (php artisan vbmapp:review-sheet)');

        return self::SUCCESS;
    }

    /** @param array<string, array<string, mixed>> $blocos */
    private function aplicarCorrecoes(array $blocos): array
    {
        $caminho = base_path(self::CORRECOES);

        if (! is_file($caminho)) {
            return $blocos;
        }

        $dados = json_decode((string) file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR);
        $aplicadas = 0;

        foreach ($dados['correcoes'] ?? [] as $chave => $campos) {
            if (! isset($blocos[$chave])) {
                $this->warn("Correção para marco inexistente: {$chave}");

                continue;
            }

            foreach ($campos as $campo => $valor) {
                if (! str_starts_with($campo, '_')) {
                    $blocos[$chave][$campo] = $valor;
                    $blocos[$chave]['corrigido_manualmente'] = true;
                }
            }

            // _pos_inferencia é metadado, não campo do marco — precisa
            // sobreviver ao filtro de underscore acima para chegar ao handle().
            if (isset($campos['_pos_inferencia'])) {
                $blocos[$chave]['_pos_inferencia'] = $campos['_pos_inferencia'];
            }

            $aplicadas++;
        }

        if ($aplicadas > 0) {
            $this->line("Correções manuais aplicadas: {$aplicadas}");
        }

        return $blocos;
    }

    /** @param array<string, array<string, mixed>> $catalogo */
    private function relatorio(array $catalogo, array $ignorados): void
    {
        $this->newLine();
        $this->line(sprintf('<info>%d/170</info> marcos reconhecidos', count($catalogo)));

        $lacunas = $this->lacunas($catalogo);
        if ($lacunas !== []) {
            $this->error('Faltando: '.implode(', ', $lacunas));
        }

        if ($ignorados !== []) {
            $this->warn(count($ignorados).' códigos sem área identificada');
        }

        $porConfianca = array_count_values(array_column($catalogo, 'confidence'));
        $porTipo = array_count_values(array_column($catalogo, 'response_type'));

        $this->newLine();
        $this->line('<comment>Confiança da inferência</comment>');
        foreach (['high' => 'alta', 'medium' => 'média', 'low' => 'baixa', 'missing' => 'sem limiar'] as $k => $rotulo) {
            if (isset($porConfianca[$k])) {
                $this->line(sprintf('  %-12s %3d', $rotulo, $porConfianca[$k]));
            }
        }

        $this->newLine();
        $this->line('<comment>Tipo de resposta inferido</comment>');
        ksort($porTipo);
        foreach ($porTipo as $tipo => $n) {
            $this->line(sprintf('  %-18s %3d', $tipo, $n));
        }

        $revisar = array_keys(array_filter(
            $catalogo,
            fn (array $m) => in_array($m['confidence'], ['low', 'missing'], true),
        ));

        if ($revisar !== []) {
            $this->newLine();
            $this->warn('Exigem atenção na revisão clínica ('.count($revisar).'):');
            $this->line('  '.implode(', ', $revisar));
        }
    }

    /** @return list<string> */
    private function lacunas(array $catalogo): array
    {
        $faltando = [];

        foreach (VbmappAreaSeeder::AREAS as $code => [, , $niveis]) {
            foreach ($niveis as $nivel) {
                foreach (range(($nivel - 1) * 5 + 1, $nivel * 5) as $posicao) {
                    if (! isset($catalogo["{$code}:{$posicao}"])) {
                        $faltando[] = "{$code}:{$posicao}";
                    }
                }
            }
        }

        return $faltando;
    }
}
