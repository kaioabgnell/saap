<?php

declare(strict_types=1);

use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Application\Assessment\StartLevel;
use App\Jobs\GenerateFormPdf;
use App\Livewire\Assessment\PrintForm;
use App\Models\Assessment;
use App\Models\Learner;
use App\Models\User;
use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\Item;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);
    Storage::fake('local');

    $this->psicologo = User::factory()->create();
    $this->learner = Learner::factory()->for($this->psicologo)->create([
        'name' => 'Kaleo Teste', 'birth_date' => '2020-03-15',
    ]);
    $this->assessment = Assessment::factory()->for($this->learner)->for($this->psicologo)
        ->create(['applied_on' => '2026-07-08']);

    $this->actingAs($this->psicologo);
});

function itemPdf(string $areaCode, int $posicao): Item
{
    return Item::where('area_id', Area::where('code', $areaCode)->sole()->id)
        ->where('position', $posicao)->sole();
}

it('gera PDF de formulário parcial', function () {
    app(StartLevel::class)->handle($this->assessment, 1);

    $tela = Livewire::test(PrintForm::class, ['assessment' => $this->assessment, 'level' => 1])
        ->call('gerar');

    expect($tela->get('status'))->toBe('ready');

    $info = GenerateFormPdf::status($tela->get('token'));
    Storage::disk('local')->assertExists($info['path']);

    $conteudo = Storage::disk('local')->get($info['path']);
    expect(substr($conteudo, 0, 4))->toBe('%PDF');
});

it('marca o PDF como rascunho', function () {
    app(StartLevel::class)->handle($this->assessment, 1);

    $tela = Livewire::test(PrintForm::class, ['assessment' => $this->assessment, 'level' => 1])
        ->call('gerar');

    $info = GenerateFormPdf::status($tela->get('token'));
    $texto = extrairTextoDoPdf(Storage::disk('local')->get($info['path']));

    expect($texto)->toContain('RASCUNHO');
});

it('inclui apenas não respondidas quando filtrado', function () {
    app(StartLevel::class)->handle($this->assessment, 1);
    $item = itemPdf('mando', 1);

    app(SaveResponse::class)->handle(new SaveResponseCommand(
        assessmentId: $this->assessment->id, itemId: $item->id, explicitScore: 1.0,
    ));

    $tela = Livewire::test(PrintForm::class, ['assessment' => $this->assessment, 'level' => 1])
        ->set('somentePendentes', true)
        ->call('gerar');

    $info = GenerateFormPdf::status($tela->get('token'));
    $texto = normalizarEspacos(extrairTextoDoPdf(Storage::disk('local')->get($info['path'])));

    // O respondido (Mando 1) some; os outros 44 continuam. O PDF quebra
    // linha dentro da frase, então compara os dois lados normalizados.
    expect($texto)->not->toContain(normalizarEspacos($item->statement))
        ->and($texto)->toContain(normalizarEspacos(itemPdf('mando', 2)->statement));
});

function normalizarEspacos(string $texto): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $texto));
}

it('usa a idade na data da aplicação', function () {
    app(StartLevel::class)->handle($this->assessment, 1);

    $tela = Livewire::test(PrintForm::class, ['assessment' => $this->assessment, 'level' => 1])
        ->call('gerar');

    $info = GenerateFormPdf::status($tela->get('token'));
    $texto = extrairTextoDoPdf(Storage::disk('local')->get($info['path']));

    expect($texto)->toContain('6 anos e 3 meses');
});

it('não gera formulário de nível não iniciado', function () {
    $tela = Livewire::test(PrintForm::class, ['assessment' => $this->assessment, 'level' => 2])
        ->call('gerar');

    expect($tela->get('status'))->toBe('failed')
        ->and($tela->get('erro'))->toContain('não foi iniciado');
});

it('gera nome de arquivo previsível e faz o download', function () {
    app(StartLevel::class)->handle($this->assessment, 1);

    $tela = Livewire::test(PrintForm::class, ['assessment' => $this->assessment, 'level' => 1])
        ->call('gerar');

    $token = $tela->get('token');

    $resposta = $this->get(route('avaliacoes.formulario', ['assessment' => $this->assessment, 'token' => $token]));

    $resposta->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="formulario-kaleo-teste-nivel-1-'.now()->format('Y-m-d').'.pdf"');
});

it('recusa baixar formulário de avaliação de outro psicólogo', function () {
    app(StartLevel::class)->handle($this->assessment, 1);
    $tela = Livewire::test(PrintForm::class, ['assessment' => $this->assessment, 'level' => 1])->call('gerar');
    $token = $tela->get('token');

    $this->actingAs(User::factory()->create());

    $this->get(route('avaliacoes.formulario', ['assessment' => $this->assessment, 'token' => $token]))
        ->assertForbidden();
});

it('devolve 404 para token inexistente', function () {
    $resposta = $this->get(route('avaliacoes.formulario', ['assessment' => $this->assessment, 'token' => 'inexistente']));
    $resposta->assertNotFound();
});
