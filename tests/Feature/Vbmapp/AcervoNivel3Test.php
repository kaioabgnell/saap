<?php

declare(strict_types=1);

use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\Item;
use App\Models\Vbmapp\Stimulus;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);
    Storage::fake('public');
});

function marcoDoAcervo(string $areaCode, int $level, int $posicao): Item
{
    return Item::where('area_id', Area::where('code', $areaCode)->sole()->id)
        ->where('level', $level)->where('position', $posicao)->sole();
}

it('importa o acervo curado dos quatro marcos de tato do nível 3', function () {
    $this->artisan('vbmapp:import-stimuli --level=3')->assertSuccessful();

    foreach ([11 => 5, 12 => 5, 13 => 5, 14 => 20] as $posicao => $esperado) {
        expect(marcoDoAcervo('tato', 3, $posicao)->stimuli()->count())
            ->toBe($esperado, "tato:{$posicao}");
    }
});

it('deixa o Tato 14 com uma figura por tentativa exigida', function () {
    $this->artisan('vbmapp:import-stimuli --level=3')->assertSuccessful();

    $item = marcoDoAcervo('tato', 3, 14);

    // 20 figuras para as "20 vezes" do manual: sem isso o cartão cai nas
    // caixas de texto do fallback.
    expect($item->stimuli()->count())->toBe($item->threshold_full);
});

it('converte figura opaca em jpeg e poupa o disco', function () {
    $this->artisan('vbmapp:import-stimuli --level=3')->assertSuccessful();

    $figura = marcoDoAcervo('tato', 3, 14)->stimuli()->orderBy('position')->first();

    expect($figura->image_path)->toEndWith('.jpg');

    // As capturas originais somam 8,6 MB em PNG; convertidas, o acervo
    // inteiro do nível 3 fica abaixo de 3 MB.
    $bytes = collect(Storage::disk('public')->allFiles('vbmapp/estimulos/nivel-3'))
        ->sum(fn (string $f) => Storage::disk('public')->size($f));

    expect($bytes)->toBeLessThan(3 * 1024 * 1024);
});

it('recusa acervo cujo rótulo não seja uma linha da matriz', function () {
    // Numa matriz o rótulo é a identidade da linha. Se não bate, a figura
    // some da grade sem erro nenhum — falha que só apareceria na aplicação.
    $item = marcoDoAcervo('tato', 3, 11);
    $item->update(['fixed_list' => ['outra coisa']]);
    CatalogCache::flush();

    $this->artisan('vbmapp:import-stimuli --level=3')->assertFailed();
});

it('não duplica nem deixa arquivo órfão ao reimportar', function () {
    $this->artisan('vbmapp:import-stimuli --level=3')->assertSuccessful();
    $primeira = Stimulus::count();
    $arquivos = count(Storage::disk('public')->allFiles('vbmapp/estimulos/nivel-3'));

    $this->artisan('vbmapp:import-stimuli --level=3')->assertSuccessful();

    expect(Stimulus::count())->toBe($primeira)
        ->and(Storage::disk('public')->allFiles('vbmapp/estimulos/nivel-3'))->toHaveCount($arquivos);
});

it('não grava nada em dry-run', function () {
    $this->artisan('vbmapp:import-stimuli --level=3 --dry-run')->assertSuccessful();

    expect(Stimulus::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles('vbmapp/estimulos/nivel-3'))->toBeEmpty();
});

it('avisa quando não há acervo curado para o nível', function () {
    $this->artisan('vbmapp:import-stimuli --level=2')->assertFailed();
});

// ------------------------------------------------------- Ouvinte 11

it('importa as 34 figuras do acervo de Ouvinte 11', function () {
    $this->artisan('vbmapp:import-stimuli --level=3')->assertSuccessful();

    $item = marcoDoAcervo('ouvinte', 3, 11);

    expect($item->stimuli()->count())->toBe(34)
        // O marco pede 6 seleções; o acervo é maior de propósito, porque o
        // arranjo de 6 muda a cada tentativa — é o contraste entre figuras
        // parecidas que torna "o carro vermelho" uma pergunta e não uma dica.
        ->and($item->stimuli()->count())->toBeGreaterThan($item->threshold_full);
});

it('guarda a instrução do acervo no marco, vinda do JSON curado', function () {
    $this->artisan('vbmapp:import-stimuli --level=3')->assertSuccessful();

    expect(marcoDoAcervo('ouvinte', 3, 11)->stimulus_prompt)
        ->toBe('Solicitar algum desses falando cor ou forma');

    // Marco sem instrução declarada continua nulo — a coluna não inventa texto.
    expect(marcoDoAcervo('tato', 3, 14)->stimulus_prompt)->toBeNull();
});

it('tira a instrução quando ela sai do JSON curado', function () {
    $this->artisan('vbmapp:import-stimuli --level=3')->assertSuccessful();

    $item = marcoDoAcervo('ouvinte', 3, 11);
    expect($item->stimulus_prompt)->not->toBeNull();

    // Simula a instrução sendo removida do arquivo versionado: reimportar
    // tem de limpar a coluna, senão o texto fica órfão no banco.
    $arquivo = base_path('database/data/vbmapp-estimulos-nivel-3.json');
    $original = file_get_contents($arquivo);

    try {
        $dados = json_decode($original, true);
        foreach ($dados['marcos'] as $i => $marco) {
            if ($marco['area'] === 'ouvinte' && $marco['position'] === 11) {
                unset($dados['marcos'][$i]['instrucao']);
            }
        }
        file_put_contents($arquivo, json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->artisan('vbmapp:import-stimuli --level=3')->assertSuccessful();

        expect($item->fresh()->stimulus_prompt)->toBeNull();
    } finally {
        file_put_contents($arquivo, $original);
    }
});

it('não deixa rótulo repetido no acervo de Ouvinte 11', function () {
    $this->artisan('vbmapp:import-stimuli --level=3')->assertSuccessful();

    // O rótulo é como a psicóloga acha a figura na grade e como ela aparece
    // no detalhamento do laudo. Dois "morango" tornariam as duas ilegíveis.
    $rotulos = marcoDoAcervo('ouvinte', 3, 11)->stimuli()->pluck('label');

    expect($rotulos)->toHaveCount(34)
        ->and($rotulos->unique())->toHaveCount(34);
});
