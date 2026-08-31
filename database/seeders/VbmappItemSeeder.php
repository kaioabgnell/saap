<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\Item;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Semeia os 170 marcos a partir do catálogo congelado.
 *
 * Falha alto de propósito. Um marco sem limiar, um total diferente de 170 ou
 * um limiar incoerente não podem passar em silêncio: eles não quebram nada
 * visível, apenas produzem pontuação errada em todo laudo emitido.
 */
class VbmappItemSeeder extends Seeder
{
    private const ARQUIVO = 'database/data/vbmapp-catalogo.json';

    public function run(): void
    {
        $caminho = base_path(self::ARQUIVO);

        if (! is_file($caminho)) {
            throw new RuntimeException(
                'Catálogo não congelado. Rode: php artisan vbmapp:import-manual && php artisan vbmapp:freeze --pendente',
            );
        }

        $dados = json_decode((string) file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR);
        $marcos = $dados['marcos'] ?? [];

        if (count($marcos) !== 170) {
            throw new RuntimeException('O catálogo tem '.count($marcos).' marcos; são esperados 170.');
        }

        $areas = Area::pluck('id', 'code')->all();

        foreach ($marcos as $chave => $m) {
            $this->validar($chave, $m);

            Item::updateOrCreate(
                [
                    'area_id' => $areas[$m['area']] ?? throw new RuntimeException("Área desconhecida: {$m['area']}"),
                    'level' => $m['level'],
                    'position' => $m['position'],
                ],
                [
                    'code' => $m['code'],
                    'statement' => $m['statement'],
                    'objective' => $m['objective'] ?? null,
                    'materials' => $m['materials'] ?? null,
                    'examples' => $m['examples'] ?? null,
                    'criteria_full' => $m['criteria_full'],
                    'criteria_half' => $m['criteria_half'] ?? null,
                    'response_type' => $m['response_type'],
                    'threshold_full' => $m['threshold_full'],
                    'threshold_half' => $m['threshold_half'] ?? null,
                    'scoring_mode' => $m['scoring_mode'],
                    'observation_minutes' => $m['observation_minutes'] ?? null,
                    'fixed_list' => $m['fixed_list'] ?? null,
                    'matrix_columns' => $m['matrix_columns'] ?? null,
                ],
            );
        }

        // Semear sem invalidar o cache deixa o catálogo VELHO na tela: um
        // `migrate:fresh --seed` recria as tabelas e a aplicação continua
        // servindo o que estava cacheado. É o defeito que faz uma correção de
        // enunciado "não pegar".
        CatalogCache::flush();

        if (($dados['revisao_clinica']['status'] ?? null) !== 'concluida') {
            $this->command?->warn(
                'Catálogo semeado SEM revisão clínica. Os limiares são inferência de parser — '.
                'não emita laudo antes de concluir a revisão (php artisan vbmapp:review-sheet).',
            );
        }
    }

    /** @param array<string, mixed> $m */
    private function validar(string $chave, array $m): void
    {
        foreach (['area', 'level', 'position', 'code', 'statement', 'criteria_full', 'response_type', 'threshold_full'] as $campo) {
            if (($m[$campo] ?? null) === null) {
                throw new RuntimeException("Marco {$chave}: campo obrigatório ausente: {$campo}");
            }
        }

        $meio = $m['threshold_half'] ?? null;
        $cheio = $m['threshold_full'];

        // Igualdade é legítima e significa critério qualitativo — ver Mando 4-M
        // e o enum ScoringMode. Meio maior que cheio nunca é legítimo.
        if ($meio !== null && $meio > $cheio) {
            throw new RuntimeException("Marco {$chave}: threshold_half ({$meio}) maior que threshold_full ({$cheio}).");
        }

        // criteria_half e threshold_half andam juntos: ou os dois existem, ou nenhum.
        $temCriterio = ($m['criteria_half'] ?? null) !== null;
        if ($temCriterio !== ($meio !== null)) {
            throw new RuntimeException(
                "Marco {$chave}: criteria_half e threshold_half divergem (critério: ".
                var_export($temCriterio, true).', limiar: '.var_export($meio !== null, true).').',
            );
        }
    }
}
