<?php

declare(strict_types=1);

use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Application\Assessment\StartLevel;
use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Domain\Vbmapp\Progress\ProgressCounter;
use App\Livewire\Assessment\ItemCard;
use App\Models\Assessment;
use App\Models\Learner;
use App\Models\Response;
use App\Models\ResponseEntry;
use App\Models\User;
use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\Item;
use App\Models\Vbmapp\MaterialPage;
use App\Models\Vbmapp\Stimulus;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);

    $this->psicologo = User::factory()->create();
    $this->learner = Learner::factory()->for($this->psicologo)->create();
    $this->assessment = Assessment::factory()->for($this->learner)->for($this->psicologo)->create();
    $this->actingAs($this->psicologo);
});

function marcoDoNivel(string $areaCode, int $posicao): Item
{
    return Item::where('area_id', Area::where('code', $areaCode)->sole()->id)
        ->where('position', $posicao)->sole();
}

function cartaoDe(Item $item, ?Response $response = null)
{
    return Livewire::test(ItemCard::class, [
        'assessment' => test()->assessment,
        'item' => $item,
        'response' => $response,
    ]);
}

// --- Matrix: Tato 7-M (rows_complete, nível 2) ---

it('monta a grade do Tato 7 com 50 linhas e 3 colunas', function () {
    app(StartLevel::class)->handle($this->assessment, 2);
    $item = marcoDoNivel('tato', 7);

    $card = cartaoDe($item);

    expect($card->instance()->linhasDaMatriz())->toHaveCount(50)
        ->and($card->instance()->colunasDaMatriz())->toBe(['Exemplar 1', 'Exemplar 2', 'Exemplar 3']);
});

it('conta acerto de matrix apenas com todos os exemplares marcados', function () {
    app(StartLevel::class)->handle($this->assessment, 2);
    $item = marcoDoNivel('tato', 7); // rows_complete: ½ com 25 linhas, 1 com 50

    $card = cartaoDe($item);

    // Marca 2 dos 3 exemplares da primeira linha (Maçã) — linha incompleta.
    $card->set('matrizMarcadas.Maçã::Exemplar 1', true)
        ->set('matrizMarcadas.Maçã::Exemplar 2', true);

    expect($card->get('acertos'))->toBe(0);

    // Completa a linha — agora conta 1.
    $card->set('matrizMarcadas.Maçã::Exemplar 3', true);
    expect($card->get('acertos'))->toBe(1);
});

it('grava list_key e column_key da matrix', function () {
    app(StartLevel::class)->handle($this->assessment, 2);
    $item = marcoDoNivel('tato', 7);

    cartaoDe($item)
        ->set('matrizMarcadas.Gato::Exemplar 1', true)
        ->set('matrizMarcadas.Gato::Exemplar 2', true)
        ->set('matrizMarcadas.Gato::Exemplar 3', true);

    $entradas = ResponseEntry::where('is_checked', true)->get();

    expect($entradas)->toHaveCount(3);
    foreach ($entradas as $e) {
        expect($e->list_key)->toBe('Gato')->and($e->column_key)->toBeIn(['Exemplar 1', 'Exemplar 2', 'Exemplar 3']);
    }
});

it('acrescenta linha à matrix além da lista fixa', function () {
    app(StartLevel::class)->handle($this->assessment, 2);
    $item = marcoDoNivel('tato', 7);

    $card = cartaoDe($item)
        ->set('novaLinhaMatrix', 'Girafa de pelúcia')
        ->call('acrescentarLinhaMatriz');

    expect($card->instance()->linhasDaMatriz())->toContain('Girafa de pelúcia')
        ->and($card->instance()->linhasDaMatriz())->toHaveCount(51);
});

it('preserva a matrix ao recarregar', function () {
    app(StartLevel::class)->handle($this->assessment, 2);
    $item = marcoDoNivel('tato', 7);

    cartaoDe($item)
        ->set('matrizMarcadas.Bola::Exemplar 1', true)
        ->set('matrizMarcadas.Bola::Exemplar 2', true);

    $response = Response::with('entries')->sole();
    $recarregado = cartaoDe($item->fresh(), $response);

    expect($recarregado->get('matrizMarcadas')['Bola::Exemplar 1'])->toBeTrue()
        ->and($recarregado->get('matrizMarcadas')['Bola::Exemplar 2'])->toBeTrue();
});

// --- Matrix: Tato 11-M (total_cells, nível 3, 5 objetos x 3 perguntas) ---

it('conta total de células no Tato 11, não linhas completas', function () {
    app(StartLevel::class)->handle($this->assessment, 3);
    $item = marcoDoNivel('tato', 11); // total_cells: ½ com 10, 1 com 15

    $card = cartaoDe($item)
        ->set('novaLinhaMatrix', 'maçã')->call('acrescentarLinhaMatriz')
        ->set('novaLinhaMatrix', 'lixeira')->call('acrescentarLinhaMatriz');

    $card->set('matrizMarcadas.maçã::Cor', true)
        ->set('matrizMarcadas.maçã::Forma', true)
        ->set('matrizMarcadas.lixeira::Cor', true);

    // 3 células certas, nenhuma linha completa — mas total_cells conta assim mesmo.
    expect($card->get('acertos'))->toBe(3);
});

it('traz os 5 objetos do material como linhas do Tato 11', function () {
    // O manual fala em "5 objetos (15 tentativas)": são as 5 linhas vezes as
    // 3 colunas. Sem lista fixa o marco abria vazio — só o campo de
    // acrescentar — e não havia como pontuar os 15.
    app(StartLevel::class)->handle($this->assessment, 3);
    $item = marcoDoNivel('tato', 11);

    expect($item->fixed_list)->toHaveCount(5)
        ->and($item->fixed_list)->toContain('maçã', 'geladeira');

    $card = cartaoDe($item);

    expect($card->instance()->linhasDaMatriz())->toHaveCount(5)
        // 5 linhas x 3 colunas = as 15 tentativas do limiar.
        ->and(count($card->instance()->linhasDaMatriz()) * count($card->instance()->colunasDaMatriz()))
        ->toBe($item->threshold_full);
});

it('ainda aceita objeto fora dos 5 do material no Tato 11', function () {
    app(StartLevel::class)->handle($this->assessment, 3);
    $item = marcoDoNivel('tato', 11);

    $card = cartaoDe($item)->set('novaLinhaMatrix', 'lixeira')->call('acrescentarLinhaMatriz');

    expect($card->instance()->linhasDaMatriz())->toHaveCount(6)
        // O catálogo continua com os 5 — o acréscimo é da aplicação.
        ->and($item->fresh()->fixed_list)->toHaveCount(5);
});

it('mostra a figura de cada objeto na grade do Tato 11', function () {
    app(StartLevel::class)->handle($this->assessment, 3);
    $item = marcoDoNivel('tato', 11);

    foreach (['maçã', 'geladeira'] as $i => $rotulo) {
        Stimulus::factory()->for($item, 'item')->create([
            'label' => $rotulo, 'position' => $i + 1, 'image_path' => "figuras/{$i}.jpg",
        ]);
    }
    CatalogCache::flush();

    $html = cartaoDe($item->fresh())->html();

    expect($html)->toContain('figuras/0.jpg')->toContain('figuras/1.jpg')
        ->toContain('Mostrar ao aprendiz');
});

it('deixa registrar cor, forma e função sem sair da apresentação', function () {
    app(StartLevel::class)->handle($this->assessment, 3);
    $item = marcoDoNivel('tato', 11);

    Stimulus::factory()->for($item, 'item')->create([
        'label' => 'maçã', 'position' => 1, 'image_path' => 'figuras/maca.jpg',
    ]);
    CatalogCache::flush();

    $html = cartaoDe($item->fresh())->html();

    // Uma pergunta por coluna, e cada uma marcável dos dois lados: na grade e
    // na apresentação. Daí dois checkboxes por célula.
    foreach (['Cor', 'Forma', 'Função'] as $coluna) {
        expect(substr_count($html, 'wire:model.live="matrizMarcadas.maçã::'.$coluna.'"'))->toBe(2);
    }
});

it('não oferece apresentação a marco de matriz sem acervo', function () {
    // Tato 7 do nível 2 tem 50 linhas de texto e nenhuma figura: um botão
    // "Mostrar ao aprendiz" ali abriria uma tela vazia.
    app(StartLevel::class)->handle($this->assessment, 2);

    $html = cartaoDe(marcoDoNivel('tato', 7))->html();

    expect($html)->not->toContain('Mostrar ao aprendiz');
});

it('esconde a página de referência do Tato 11 quando já há acervo próprio', function () {
    app(StartLevel::class)->handle($this->assessment, 3);
    $item = marcoDoNivel('tato', 11);

    Stimulus::factory()->for($item, 'item')->create(['label' => 'maçã', 'position' => 1]);
    MaterialPage::create([
        'level' => 3, 'area_id' => $item->area_id, 'item_position' => 11,
        'image_path' => 'paginas/x.png', 'page_number' => 2, 'source_file' => 'x.pdf',
    ]);
    CatalogCache::flush();

    $html = cartaoDe($item->fresh())->html();

    expect($html)->not->toContain('do material')
        ->and($html)->toContain('Mostrar ao aprendiz');
});

it('mantém a página de referência num marco de matriz sem acervo', function () {
    // Tato 7 do nível 2: 50 linhas de texto, nenhuma figura própria — a
    // página do PDF continua sendo o único material visual disponível.
    app(StartLevel::class)->handle($this->assessment, 2);
    $item = marcoDoNivel('tato', 7);

    MaterialPage::create([
        'level' => 2, 'area_id' => $item->area_id, 'item_position' => 7,
        'image_path' => 'paginas/y.png', 'page_number' => 40, 'source_file' => 'y.pdf',
    ]);
    CatalogCache::flush();

    $html = cartaoDe($item->fresh())->html();

    expect($html)->toContain('Página 40 do material');
});

it('esconde a página de referência da grade quando já há acervo próprio', function () {
    app(StartLevel::class)->handle($this->assessment, 3);
    $item = marcoDoNivel('tato', 12); // counter_stimuli

    Stimulus::factory()->for($item, 'item')->create(['label' => 'bola', 'position' => 1]);
    MaterialPage::create([
        'level' => 3, 'area_id' => $item->area_id, 'item_position' => 12,
        'image_path' => 'paginas/z.png', 'page_number' => 4, 'source_file' => 'z.pdf',
    ]);
    CatalogCache::flush();

    $html = cartaoDe($item->fresh())->html();

    expect($html)->not->toContain('Página 4')
        ->and($html)->toContain('Mostrar ao aprendiz');
});

it('mantém a página de referência da grade quando não há acervo próprio', function () {
    app(StartLevel::class)->handle($this->assessment, 1);
    $item = marcoDoNivel('tato', 5); // counter_stimuli sem estímulos cadastrados

    MaterialPage::create([
        'level' => 1, 'area_id' => $item->area_id, 'item_position' => 5,
        'image_path' => 'paginas/w.png', 'page_number' => 12, 'source_file' => 'w.pdf',
    ]);
    CatalogCache::flush();

    $html = cartaoDe($item->fresh())->html();

    expect($html)->toContain('Página 12');
});

it('abre a apresentação em tela cheia ao clicar na miniatura da linha', function () {
    app(StartLevel::class)->handle($this->assessment, 3);
    $item = marcoDoNivel('tato', 11);

    Stimulus::factory()->for($item, 'item')->create(['label' => 'maçã', 'position' => 1]);
    CatalogCache::flush();

    $html = cartaoDe($item->fresh())->html();

    // A miniatura é o único elemento clicável dentro da célula que dispara
    // o mesmo estado Alpine do botão "Mostrar ao aprendiz".
    expect(substr_count($html, 'x-on:click="apresentando = true"'))->toBe(2);
});

// --- counter_list: acréscimo ---

it('acrescenta item fora da lista fixa em counter_list', function () {
    app(StartLevel::class)->handle($this->assessment, 1);
    $item = marcoDoNivel('ecoico', 2); // 25 palavras, ½ com 3, 1 com 5

    $card = cartaoDe($item)
        ->set('marcadas.Ah', true)
        ->set('marcadas.eu', true)
        ->set('novoItemLista', 'mamãe')
        ->call('acrescentarItemLista');

    expect($card->get('acertos'))->toBe(3)->and($card->get('score'))->toBe(0.5);

    $extra = ResponseEntry::whereNull('list_key')->where('text_value', 'mamãe')->first();
    expect($extra)->not->toBeNull();
});

it('não altera o catálogo ao acrescentar item', function () {
    app(StartLevel::class)->handle($this->assessment, 1);
    $item = marcoDoNivel('ecoico', 2);
    $listaOriginal = $item->fixed_list;

    cartaoDe($item)->set('novoItemLista', 'extra')->call('acrescentarItemLista');

    expect($item->fresh()->fixed_list)->toBe($listaOriginal);
});

// --- Navegação entre níveis / progresso global ---

it('soma 170 respostas com os três níveis completos', function () {
    $save = app(SaveResponse::class);

    foreach ([1, 2, 3] as $nivel) {
        app(StartLevel::class)->handle($this->assessment, $nivel);

        foreach (Item::where('level', $nivel)->get() as $item) {
            $save->handle(new SaveResponseCommand(
                assessmentId: $this->assessment->id,
                itemId: $item->id,
                explicitScore: 1.0,
            ));
        }
    }

    $total = Response::where('assessment_id', $this->assessment->id)
        ->whereNotNull('answered_at')->count();

    expect($total)->toBe(170);

    $progresso = app(ProgressCounter::class)
        ->forAssessment($total, [1, 2, 3]);

    expect($progresso->format())->toBe('170 de 170')->and($progresso->isComplete())->toBeTrue();
});

it('mostra a instrução do acervo acima da grade', function () {
    app(StartLevel::class)->handle($this->assessment, 3);
    $item = marcoDoNivel('ouvinte', 11);

    $item->update(['stimulus_prompt' => 'Solicitar algum desses falando cor ou forma']);
    Stimulus::factory()->for($item, 'item')->create(['label' => 'carro vermelho', 'position' => 1]);
    CatalogCache::flush();

    $html = cartaoDe($item->fresh())->html();

    expect($html)->toContain('Solicitar algum desses falando cor ou forma');

    // UMA vez só: a grade tem a instrução, a apresentação não. Repeti-la lá
    // a poria na frente da criança — e "falando cor ou forma" entrega o
    // critério do que está sendo testado.
    expect(substr_count($html, 'Solicitar algum desses falando cor ou forma'))->toBe(1);
});

it('não abre espaço para instrução em marco que não tem uma', function () {
    app(StartLevel::class)->handle($this->assessment, 3);
    $item = marcoDoNivel('tato', 12);

    Stimulus::factory()->for($item, 'item')->create(['label' => 'bola', 'position' => 1]);
    CatalogCache::flush();

    expect($item->fresh()->stimulus_prompt)->toBeNull()
        ->and(cartaoDe($item->fresh())->html())->toContain('Mostrar ao aprendiz');
});
