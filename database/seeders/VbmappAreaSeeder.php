<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Vbmapp\Area;
use Illuminate\Database\Seeder;

/**
 * As 16 áreas do VB-MAPP.
 *
 * short_name é o rótulo do gráfico e precisa bater com refs/Grafico VB-MAPP.xlsx
 * ("VP/MTS", não "Percepção Visual"). Ver .claude/specs/04-catalogo-vbmapp.md
 */
class VbmappAreaSeeder extends Seeder
{
    /**
     * Em quais níveis cada área existe. Fonte: os três PDFs de registro,
     * o manual traduzido e a planilha de gráfico — conferidos entre si.
     *
     * @var array<string, array{string, string, list<int>}>
     */
    public const AREAS = [
        'mando' => ['Mando', 'Mando', [1, 2, 3]],
        'tato' => ['Tato', 'Tato', [1, 2, 3]],
        'ouvinte' => ['Ouvinte', 'Ouvinte', [1, 2, 3]],
        'vpmts' => ['Percepção Visual e Pareamento ao Modelo', 'VP/MTS', [1, 2, 3]],
        'brincar' => ['Brincar Independente', 'Brincar', [1, 2, 3]],
        'social' => ['Comportamento Social e Brincar Social', 'Social', [1, 2, 3]],
        'imitacao' => ['Imitação Motora', 'Imitação', [1, 2]],
        'ecoico' => ['Ecóico', 'Ecóico', [1, 2]],
        'vocal' => ['Comportamento Vocal', 'Vocal', [1]],
        'lrffc' => ['Resposta de Ouvinte por Função, Característica ou Classe', 'LRFFC', [2, 3]],
        'intraverbal' => ['Intraverbal', 'Intraverbal', [2, 3]],
        'grupo' => ['Comportamento em Grupo e Rotinas de Sala', 'Grupo', [2, 3]],
        'linguistica' => ['Estrutura Linguística', 'Linguística', [2, 3]],
        'leitura' => ['Leitura', 'Leitura', [3]],
        'escrita' => ['Escrita', 'Escrita', [3]],
        'matematica' => ['Matemática', 'Matemática', [3]],
    ];

    public function run(): void
    {
        $position = 1;

        foreach (self::AREAS as $code => [$name, $shortName]) {
            Area::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'short_name' => $shortName, 'position' => $position++],
            );
        }
    }

    /** Os códigos de área presentes num nível, na ordem de exibição. */
    public static function codesForLevel(int $level): array
    {
        return array_keys(array_filter(
            self::AREAS,
            fn (array $area) => in_array($level, $area[2], true),
        ));
    }
}
