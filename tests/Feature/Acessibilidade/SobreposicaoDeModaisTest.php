<?php

declare(strict_types=1);

use App\Application\Assessment\OpenChartAssessment;
use App\Application\Assessment\StartLevel;
use App\Livewire\Assessment\ChartEntry;
use App\Livewire\Assessment\LevelBoard;
use App\Livewire\Assessment\PrintForm;
use App\Models\Assessment;
use App\Models\Learner;
use App\Models\User;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Modal preso dentro de um ancestral com `backdrop-filter`.
 *
 * `backdrop-filter` (e também `filter`, `transform`, `perspective`) cria
 * **bloco de contenção** para descendentes `position: fixed`: o `inset-0`
 * passa a valer contra a caixa do ancestral em vez da janela. E cria stacking
 * context próprio, então o z-index do modal fica aprisionado no do ancestral.
 *
 * A barra de ações do rodapé tem `backdrop-blur` e hospeda o botão de imprimir.
 * O modal aparecia espremido na faixa do rodapé e atrás do resto da tela.
 * Aumentar o z-index não resolve — o problema é o bloco de contenção. A saída
 * é tirar o modal de lá com `@teleport('body')`.
 */
beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);

    $psicologo = User::factory()->create();
    $this->actingAs($psicologo);

    $this->psicologo = $psicologo;
    $this->learner = Learner::factory()->for($psicologo)->create();
    $this->assessment = Assessment::factory()->for($this->learner)->for($psicologo)->create();

    app(StartLevel::class)->handle($this->assessment, 1);
});

/** Procura modal preso em ancestral que crie bloco de contenção. */
function modaisPresos(string $html): array
{
    $doc = new DOMDocument;
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);
    $sobreposicoes = $xpath->query("//*[contains(@class,'fixed') and contains(@class,'inset-0')]");

    // Silêncio não é aprovação: sem sobreposição para examinar, quem chama
    // tem de falhar em vez de passar sem ter olhado nada.
    if ($sobreposicoes->length === 0) {
        return ['nenhum modal no HTML — o teste não verificou nada'];
    }

    $presos = [];

    foreach ($sobreposicoes as $modal) {
        foreach ($xpath->query('ancestor::*', $modal) as $ancestral) {
            if ($ancestral->nodeName === 'template') {
                continue 2;   // teleportado: sai do lugar em runtime
            }

            $classe = $ancestral->getAttribute('class');

            if (preg_match('/\b(backdrop-blur|backdrop-filter|transform|filter-)/', $classe)) {
                $presos[] = substr($modal->getAttribute('class'), 0, 50)
                    .'  →  dentro de: '.substr($classe, 0, 50);
            }
        }
    }

    return $presos;
}

it('teleporta o modal de impressão para fora da barra com backdrop-blur', function () {
    $html = Livewire::test(PrintForm::class, ['assessment' => $this->assessment, 'level' => 1])
        ->call('abrir')
        ->html();

    expect($html)->toContain('x-teleport="body"');

    // O modal tem de estar DENTRO do template teleportado, não solto.
    $posTemplate = strpos($html, 'x-teleport="body"');
    $posModal = strpos($html, 'fixed inset-0');

    expect($posModal)->toBeGreaterThan($posTemplate);
});

it('não deixa o modal de conclusão preso sob um ancestral com backdrop-filter', function () {
    // Precisa de um modal ABERTO: com a tela em repouso não há sobreposição no
    // HTML, e o teste passaria sem olhar nada. O de conclusão do nível é irmão
    // da barra hoje — este teste é o que impede alguém de movê-lo para dentro
    // dela e reintroduzir o mesmo defeito.
    $presos = modaisPresos(
        Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 1])
            ->call('confirmarConclusao')
            ->html()
    );

    expect($presos)->toBeEmpty("modal preso em bloco de contenção:\n".implode("\n", $presos));
});

it('não deixa o modal do lançamento preso sob a barra com backdrop-blur', function () {
    // A tela de lançamento tem DOIS ancestrais com backdrop-blur: o cabeçalho
    // fixo e a barra de conclusão. O modal nasce ao lado do botão que o abre,
    // que fica na barra — sem teleporte, ele apareceria espremido no rodapé.
    $transcricao = app(OpenChartAssessment::class)->handle(
        $this->learner, $this->psicologo, '2025-03-12', [1],
    );

    $presos = modaisPresos(
        Livewire::test(ChartEntry::class, ['assessment' => $transcricao])
            ->call('confirmarConclusao')
            ->html()
    );

    expect($presos)->toBeEmpty("modal preso em bloco de contenção:\n".implode("\n", $presos));
});
