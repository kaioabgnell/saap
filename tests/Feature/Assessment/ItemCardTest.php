<?php

declare(strict_types=1);

use App\Application\Assessment\StartLevel;
use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Livewire\Assessment\ItemCard;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use App\Models\Learner;
use App\Models\Response;
use App\Models\ResponseEntry;
use App\Models\User;
use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\Item;
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

    app(StartLevel::class)->handle($this->assessment, 1);
    $this->actingAs($this->psicologo);
});

function marcoDe(string $areaCode, int $posicao): Item
{
    return Item::where('area_id', Area::where('code', $areaCode)->sole()->id)
        ->where('position', $posicao)->sole();
}

function cartao(Item $item, ?Response $response = null)
{
    return Livewire::test(ItemCard::class, [
        'assessment' => test()->assessment,
        'item' => $item,
        'response' => $response,
    ]);
}

it('monta counter_free com uma caixa por acerto exigido', function () {
    // Mando 5-M: 10 acertos para 1 ponto.
    $card = cartao(marcoDe('mando', 5));

    expect($card->get('caixas'))->toHaveCount(10);
});

it('salva ao preencher uma caixa e recalcula o contador', function () {
    $item = marcoDe('mando', 2); // ½ com 3, 1 ponto com 4

    $card = cartao($item)
        ->set('caixas.1', 'bola')
        ->set('caixas.2', 'água')
        ->set('caixas.3', 'pipa');

    expect($card->get('acertos'))->toBe(3)
        ->and($card->get('score'))->toBe(0.5)
        ->and($card->get('respondido'))->toBeTrue()
        ->and($card->get('salvoEm'))->not->toBeNull();

    expect(Response::sole()->score)->toBe(0.5);
});

it('grava binary_criteria pelo critério escolhido', function () {
    $item = marcoDe('brincar', 1);

    expect(cartao($item)->set('ordinal', 2)->get('score'))->toBe(1.0);
    expect(cartao($item->fresh())->set('ordinal', 1)->get('score'))->toBe(0.5);
    expect(cartao($item->fresh())->set('ordinal', 0)->get('score'))->toBe(0.0);
});

it('não oferece meio ponto no marco que não admite', function () {
    // Ouvinte 2-M: "Não há ½ ponto para esta habilidade" (manual, p. 80).
    $item = marcoDe('ouvinte', 2);
    expect($item->criteria_half)->toBeNull()
        ->and($item->threshold_half)->toBeNull();

    // O painel de critérios diz isso ao psicólogo, em vez de deixar em branco.
    cartao($item)
        ->call('alternarCriterios')
        ->assertSee('Este marco não admite meio ponto.', escape: false);

    // E não existe caminho pela interface que produza ½ neste marco: com
    // qualquer contagem abaixo do limiar cheio, a pontuação é zero.
    foreach ([1, 2, 3, 4] as $acertos) {
        $card = cartao($item->fresh(), Response::with('entries')->first());
        foreach (range(1, $acertos) as $i) {
            $card->set("caixas.{$i}", "tentativa {$i}");
        }
        expect($card->get('score'))->toBe(0.0, "com {$acertos} acertos");
    }

    $card = cartao($item->fresh(), Response::with('entries')->first());
    foreach (range(1, 5) as $i) {
        $card->set("caixas.{$i}", "tentativa {$i}");
    }
    expect($card->get('score'))->toBe(1.0);
});

it('monta counter_list com as 25 palavras do subteste', function () {
    $card = cartao(marcoDe('ecoico', 3));

    expect($card->get('marcadas'))->toHaveCount(25)
        ->and(array_keys($card->get('marcadas')))->toContain('tchau', 'miau', 'auau');
});

it('conta os checks da lista', function () {
    $item = marcoDe('ecoico', 2); // ½ com 3, 1 ponto com 5

    $card = cartao($item)
        ->set('marcadas.Ah', true)
        ->set('marcadas.eu', true)
        ->set('marcadas.tchau', true);

    expect($card->get('acertos'))->toBe(3)->and($card->get('score'))->toBe(0.5);
});

it('exige confirmação em marco assisted', function () {
    // Mando 4-M: os dois critérios pedem os mesmos 5 mandos.
    $item = marcoDe('mando', 4);

    $card = cartao($item);
    foreach (range(1, 5) as $i) {
        $card->set("caixas.{$i}", "mando {$i}");
    }

    expect($card->get('pedeConfirmacao'))->toBeTrue()
        ->and($card->get('respondido'))->toBeFalse();

    $card->assertSee('Qual foi atingido?');

    $card->call('confirmar', '0.5');

    expect($card->get('score'))->toBe(0.5)
        ->and($card->get('respondido'))->toBeTrue()
        ->and($card->get('pedeConfirmacao'))->toBeFalse()
        ->and($card->get('sobrescrito'))->toBeTrue();
});

it('registra zero deliberado como respondido', function () {
    $card = cartao(marcoDe('mando', 1))->call('confirmar', '0');

    expect($card->get('score'))->toBe(0.0)
        ->and($card->get('respondido'))->toBeTrue()
        ->and(AssessmentLevel::sole()->answered_count)->toBe(1);
});

it('preserva tudo ao recarregar a página', function () {
    $item = marcoDe('mando', 2);

    cartao($item)->set('caixas.1', 'bola')->set('caixas.2', 'água');

    // Nova montagem, como num F5 do navegador.
    $response = Response::with('entries')->sole();
    $recarregado = cartao($item->fresh(), $response);

    expect($recarregado->get('caixas')[1])->toBe('bola')
        ->and($recarregado->get('caixas')[2])->toBe('água')
        ->and($recarregado->get('acertos'))->toBe(2)
        ->and($recarregado->get('respondido'))->toBeTrue();
});

it('preserva o critério escolhido ao recarregar', function () {
    $item = marcoDe('brincar', 1);
    cartao($item)->set('ordinal', 1);

    $recarregado = cartao($item->fresh(), Response::with('entries')->sole());

    expect($recarregado->get('ordinal'))->toBe(1)->and($recarregado->get('score'))->toBe(0.5);
});

it('salva a observação do marco', function () {
    cartao(marcoDe('mando', 1))->set('observacao', 'Criança dispersa hoje.');

    expect(Response::sole()->notes)->toBe('Criança dispersa hoje.');
});

it('mostra o tempo de observação quando o marco exige', function () {
    // Vocal 1-M: OC de 60 min.
    cartao(marcoDe('vocal', 1))->assertSee('60 min de observação');
});

it('não mostra selo de observação em marco que não exige', function () {
    cartao(marcoDe('mando', 1))->assertDontSee('min de observação');
});

it('renderiza a grade de imagens do marco com material', function () {
    $item = marcoDe('tato', 1); // counter_stimuli, precisa de 2

    foreach (['bola', 'gato', 'pato'] as $i => $rotulo) {
        Stimulus::factory()->for($item, 'item')
            ->create(['label' => $rotulo, 'position' => $i + 1]);
    }
    CatalogCache::flush();

    cartao($item->fresh())
        ->assertSee('bola')->assertSee('gato')->assertSee('pato')
        ->assertSee('Mostrar ao aprendiz');
});

it('grava o estímulo acertado na grade', function () {
    $item = marcoDe('tato', 1); // ½ com 1 acerto, 1 ponto com 2

    $estimulos = collect(['bola', 'gato', 'pato'])->map(
        fn ($rotulo, $i) => Stimulus::factory()->for($item, 'item')
            ->create(['label' => $rotulo, 'position' => $i + 1]),
    );
    CatalogCache::flush();

    $card = cartao($item->fresh())->set("estimulos.{$estimulos[0]->id}", true);
    expect($card->get('acertos'))->toBe(1)->and($card->get('score'))->toBe(0.5);

    $card->set("estimulos.{$estimulos[1]->id}", true);
    expect($card->get('acertos'))->toBe(2)->and($card->get('score'))->toBe(1.0);

    // O relatório detalha exemplar a exemplar, então o estímulo fica registrado.
    $marcados = ResponseEntry::where('is_checked', true)->pluck('stimulus_id');
    expect($marcados)->toContain($estimulos[0]->id, $estimulos[1]->id);
});

it('completa com caixas de texto quando faltam imagens', function () {
    $item = marcoDe('tato', 5); // precisa de 10

    Stimulus::factory()->for($item, 'item')->create(['label' => 'única']);
    CatalogCache::flush();

    // Falta de imagem nunca bloqueia a aplicação.
    $card = cartao($item->fresh())->assertSee('menos imagens que este marco exige');

    expect($card->get('caixas'))->toHaveCount(9);
});

it('mostra o erro quando a avaliação está travada', function () {
    $this->assessment->update(['locked_at' => now()]);

    $card = cartao(marcoDe('mando', 1))->set('caixas.1', 'bola');

    expect($card->get('erro'))->toContain('concluída')
        ->and(Response::count())->toBe(0);
});
