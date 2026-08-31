<?php

declare(strict_types=1);

use App\Domain\Vbmapp\Report\MilestoneChart;

/**
 * O gráfico de marcos: uma cor só, e dados suficientes para o tooltip.
 */
function areaDeExemplo(): array
{
    return [
        'code' => 'mando',
        'name' => 'Mando',
        'short_name' => 'Mando',
        'marcos' => [
            ['posicao' => 1, 'codigo' => '1-M', 'enunciado' => 'Emite 2 palavras.',
                'respondido' => true, 'score' => 1.0, 'exemplares' => ['bola', 'água'], 'observacoes' => null],
            ['posicao' => 2, 'codigo' => '2-M', 'enunciado' => 'Emite 4 mandos.',
                'respondido' => true, 'score' => 0.5, 'exemplares' => ['pato'], 'observacoes' => 'criança dispersa'],
            ['posicao' => 3, 'codigo' => '3-M', 'enunciado' => 'Generaliza 6 mandos.',
                'respondido' => true, 'score' => 0.0, 'exemplares' => [], 'observacoes' => null],
            ['posicao' => 4, 'codigo' => '4-M', 'enunciado' => 'Emite 5 mandos.',
                'respondido' => false, 'score' => 0.0, 'exemplares' => [], 'observacoes' => null],
            ['posicao' => 5, 'codigo' => '5-M', 'enunciado' => 'Emite 10 mandos.',
                'respondido' => false, 'score' => 0.0, 'exemplares' => [], 'observacoes' => null],
        ],
    ];
}

it('leva ao tooltip a área, a pergunta e a resposta de cada marco', function () {
    $chart = MilestoneChart::fromAreas(1, [areaDeExemplo()]);
    $celulas = collect($chart->columns[0]['cells'])->keyBy('position');

    expect($celulas[1])
        ->area->toBe('Mando')
        ->code->toBe('1-M')
        ->statement->toBe('Emite 2 palavras.')
        ->score->toBe('1 ponto')
        ->answer->toBe('bola, água');

    expect($celulas[2]['answer'])->toBe('pato · Obs.: criança dispersa')
        ->and($celulas[2]['score'])->toBe('meio ponto');
});

it('não inventa resposta para marco não respondido', function () {
    // Zero é pontuação deliberada; ausência de resposta não é. Dizer "0 ponto"
    // num marco nunca aplicado seria afirmar algo que não aconteceu.
    $chart = MilestoneChart::fromAreas(1, [areaDeExemplo()]);
    $celulas = collect($chart->columns[0]['cells'])->keyBy('position');

    expect($celulas[4]['score'])->toBe('não respondido')
        ->and($celulas[4]['answer'])->toBe('')
        ->and($celulas[3]['score'])->toBe('0 ponto');
});

it('pinta meio ponto e ponto inteiro com a MESMA cor', function () {
    // A diferença entre ½ e 1 é a altura preenchida, não a cor. Se alguém
    // reintroduzir uma segunda cor, o gráfico volta a dizer a mesma coisa
    // duas vezes — e a legenda passa a mentir.
    $estilo = file_get_contents(resource_path('views/pdf/relatorio/_estilo.blade.php'));

    preg_match('/\.celula\.cheia\s*\{\s*background-color:\s*(#[0-9A-Fa-f]{6})/', $estilo, $cheia);
    preg_match('/\.celula\.meia\s*\{\s*background-color:\s*(#[0-9A-Fa-f]{6})/', $estilo, $meia);

    expect($cheia[1] ?? 'a')->toBe($meia[1] ?? 'b');
});

it('mantém o gráfico do PDF sem os atributos de tooltip', function () {
    // O PDF não tem Alpine nem foco por teclado: `interativo` desligado é o
    // que impede atributos inúteis de irem parar no papel.
    $pdf = file_get_contents(resource_path('views/pdf/relatorio/documento.blade.php'));

    expect($pdf)->toContain('<x-vbmapp.milestone-chart :chart="$chart" />')
        ->and($pdf)->not->toContain('interativo');
});
