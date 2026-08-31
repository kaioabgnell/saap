<?php

declare(strict_types=1);

use App\Livewire\Admin\StimulusCuration;
use App\Models\User;
use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\Item;
use App\Models\Vbmapp\Stimulus;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
});

function marcoComMaterial(string $areaCode, int $posicao): Item
{
    return Item::where('area_id', Area::where('code', $areaCode)->sole()->id)
        ->where('position', $posicao)->sole();
}

it('lista apenas os marcos que usam material de aplicação', function () {
    $tela = Livewire::test(StimulusCuration::class);

    // No nível 1 são sete: Tato 1, 2, 3, 5 · Ouvinte 3, 5 · VP/MTS 5.
    expect($tela->instance()->marcos)->toHaveCount(7);

    foreach ($tela->instance()->marcos as $m) {
        expect($m->response_type->value)->toBe('counter_stimuli');
    }
});

it('rotula um estímulo e persiste', function () {
    $item = marcoComMaterial('tato', 1);
    $estimulo = Stimulus::factory()->for($item, 'item')->create(['label' => '']);

    Livewire::test(StimulusCuration::class)
        ->call('selecionarMarco', $item->id)
        ->set("rotulos.{$estimulo->id}", 'bola');

    expect($estimulo->fresh()->label)->toBe('bola');
});

it('descarta um recorte ruim', function () {
    $item = marcoComMaterial('tato', 1);
    $estimulo = Stimulus::factory()->for($item, 'item')->create();

    Livewire::test(StimulusCuration::class)
        ->call('selecionarMarco', $item->id)
        ->call('descartar', $estimulo->id);

    expect(Stimulus::find($estimulo->id))->toBeNull();
});

it('aceita imagem enviada à mão quando o recorte falha', function () {
    $item = marcoComMaterial('tato', 1);

    Livewire::test(StimulusCuration::class)
        ->call('selecionarMarco', $item->id)
        ->set('novaImagem', UploadedFile::fake()->image('estimulo.jpg', 600, 600))
        ->call('subirImagem');

    $novo = Stimulus::where('item_id', $item->id)->sole();

    // source_page 0 marca origem manual, distinta do recorte automático.
    expect($novo->source_page)->toBe(0);
    Storage::disk('public')->assertExists($novo->image_path);
});

it('sinaliza quando o marco ainda não tem estímulos rotulados suficientes', function () {
    $item = marcoComMaterial('tato', 3); // precisa de 6
    Stimulus::factory()->for($item, 'item')->create(['label' => 'sofá']);

    Livewire::test(StimulusCuration::class)
        ->call('selecionarMarco', $item->id)
        ->assertSee('1/6');
});

it('exige autenticação', function () {
    auth()->logout();

    $this->get(route('admin.estimulos'))->assertRedirect(route('login'));
});
