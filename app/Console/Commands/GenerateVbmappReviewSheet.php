<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Vbmapp\Area;
use Illuminate\Console\Command;

/**
 * Gera a planilha da revisão clínica.
 *
 * A revisão não é tarefa de engenharia: o limiar decide se a criança pontua ½
 * ou 1, e um erro aqui contamina todo laudo emitido sem gerar erro visível.
 * A planilha sai ordenada por prioridade — o que menos confiança tem vem
 * primeiro, para que a revisão comece pelo que mais importa.
 */
class GenerateVbmappReviewSheet extends Command
{
    protected $signature = 'vbmapp:review-sheet {--saida=storage/app/vbmapp/revisao-clinica.csv}';

    protected $description = 'Gera a planilha CSV para revisão clínica dos 170 marcos';

    private const PRIORIDADE = ['low' => 0, 'missing' => 0, 'medium' => 1, 'high' => 2];

    public function handle(): int
    {
        $origem = storage_path('app/vbmapp/catalogo.json');

        if (! is_file($origem)) {
            $this->error('Catálogo não gerado. Rode antes: php artisan vbmapp:import-manual');

            return self::FAILURE;
        }

        $marcos = json_decode((string) file_get_contents($origem), true, 512, JSON_THROW_ON_ERROR)['marcos'];
        $nomes = Area::pluck('name', 'code')->all();

        uasort($marcos, function (array $a, array $b) {
            $pa = self::PRIORIDADE[$a['confidence']] ?? 1;
            $pb = self::PRIORIDADE[$b['confidence']] ?? 1;

            return [$pa, $a['area'], $a['position']] <=> [$pb, $b['area'], $b['position']];
        });

        $destino = base_path((string) $this->option('saida'));
        @mkdir(dirname($destino), 0775, true);
        $csv = fopen($destino, 'w');

        // BOM para o Excel abrir UTF-8 sem estragar os acentos
        fwrite($csv, "\u{FEFF}");

        fputcsv($csv, [
            // area_code é o identificador estável que o vbmapp:apply-review usa
            // para casar a linha de volta. O nome da área é para o humano ler;
            // casar por nome quebraria à primeira correção de grafia.
            'prioridade', 'confianca', 'area', 'area_code', 'nivel', 'marco', 'codigo',
            'enunciado', 'criterio_1_ponto', 'criterio_meio_ponto',
            'tipo_inferido', 'limiar_meio', 'limiar_cheio', 'modo',
            'obs_minutos', 'corrigido_manualmente',
            'CONFERIDO_SN', 'TIPO_CORRIGIDO', 'MEIO_CORRIGIDO', 'CHEIO_CORRIGIDO', 'MODO_CORRIGIDO', 'COMENTARIO',
        ]);

        $prioritarios = 0;

        foreach ($marcos as $m) {
            $prioridade = self::PRIORIDADE[$m['confidence']] ?? 1;
            if ($prioridade === 0) {
                $prioritarios++;
            }

            fputcsv($csv, [
                ['revisar primeiro', 'conferir', 'amostragem'][$prioridade],
                $m['confidence'],
                $nomes[$m['area']] ?? $m['area'],
                $m['area'],
                $m['level'],
                $m['position'],
                $m['code'],
                $m['statement'],
                $m['criteria_full'],
                $m['criteria_half'] ?? '(sem meio ponto)',
                $m['response_type'],
                $m['threshold_half'] ?? '',
                $m['threshold_full'],
                $m['scoring_mode'],
                $m['observation_minutes'] ?? '',
                ! empty($m['corrigido_manualmente']) ? 'sim' : '',
                '', '', '', '', '', '',
            ]);
        }

        fclose($csv);

        $this->info('Planilha gerada: '.$this->option('saida'));
        $this->line(sprintf('  %d marcos, %d marcados para revisar primeiro', count($marcos), $prioritarios));
        $this->newLine();
        $this->line('<comment>Como preencher</comment>');
        $this->line('  CONFERIDO_SN     S quando o marco estiver correto como está');
        $this->line('  TIPO_CORRIGIDO   preencher só se o tipo inferido estiver errado');
        $this->line('  MEIO/CHEIO       preencher só se o limiar estiver errado; "nulo" se não há meio ponto');
        $this->line('  MODO_CORRIGIDO   auto ou assisted; assisted quando ½ e 1 têm a MESMA contagem');
        $this->line('  COMENTARIO       qualquer observação para o desenvolvimento');
        $this->newLine();
        $this->line('Não mexa nas colunas <comment>area_code</comment> e <comment>marco</comment>: são elas que');
        $this->line('identificam a linha na volta. Célula em branco significa "não mexer".');
        $this->newLine();
        $this->line('Quando terminar:');
        $this->line('  php artisan vbmapp:apply-review --dry-run   # confere o que mudaria');
        $this->line('  php artisan vbmapp:apply-review');
        $this->line('  php artisan vbmapp:freeze --revisor="Seu nome"');
        $this->line('  php artisan migrate:fresh --seed');

        return self::SUCCESS;
    }
}
