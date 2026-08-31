<?php

declare(strict_types=1);

use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Application\Assessment\StartLevel;
use App\Livewire\Assessment\LevelBoard;
use App\Models\Assessment;
use App\Models\Learner;
use App\Models\User;
use App\Models\Vbmapp\Item;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);

    $this->psicologo = User::factory()->create();
    $this->learner = Learner::factory()->for($this->psicologo)->create();
    $this->assessment = Assessment::factory()->for($this->learner)->for($this->psicologo)->create();

    app(StartLevel::class)->handle($this->assessment, 1);
    $this->actingAs($this->psicologo);

    // Cenário de referência da spec: os 45 marcos do nível 1 respondidos.
    $save = app(SaveResponse::class);
    foreach (Item::where('level', 1)->get() as $item) {
        $save->handle(new SaveResponseCommand(
            assessmentId: $this->assessment->id, itemId: $item->id, explicitScore: 1.0,
        ));
    }
});

it('não dispara consulta por marco ao renderizar uma área', function () {
    $consultas = [];
    DB::listen(function ($q) use (&$consultas) {
        $consultas[] = $q->sql;
    });

    Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 1])
        ->set('areaCode', 'mando');

    // Sem N+1, o número de consultas não escala com a quantidade de marcos.
    // O teto é generoso de propósito: o que importa é não crescer com os itens.
    expect(count($consultas))->toBeLessThan(30, implode("\n", $consultas));
});

it('renderiza as 9 áreas sem consulta proporcional aos 45 marcos', function () {
    $consultas = [];
    DB::listen(function ($q) use (&$consultas) {
        $consultas[] = $q->sql;
    });

    Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 1])
        ->call('selecionarArea', null);

    // 45 cartões montados. Se houvesse N+1 por marco, passaria de 45 fácil.
    expect(count($consultas))->toBeLessThan(60, implode("\n", $consultas));
});

// O alvo de tempo da tela do nível saiu daqui na F9. Este teste media a
// PRIMEIRA renderização do processo — que paga o boot do framework e a
// compilação das views — e por isso oscilava entre 350 e 540 ms sem que nada
// no código tivesse mudado. A medição por mediana, com aquecimento e pelo
// caminho HTTP real, está em tests/Feature/Performance/AlvosDeDesempenhoTest.
// Os testes de N+1 abaixo continuam aqui: esses são estáveis e específicos
// do nível 1.

it('usa o cache do catálogo em vez de reler os marcos', function () {
    Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 1]);

    $consultas = [];
    DB::listen(function ($q) use (&$consultas) {
        $consultas[] = $q->sql;
    });

    Livewire::test(LevelBoard::class, ['assessment' => $this->assessment, 'level' => 1]);

    $doCatalogo = array_filter($consultas, fn ($sql) => str_contains($sql, 'vbmapp_areas'));

    expect($doCatalogo)->toBeEmpty('o catálogo deveria vir do cache');
});
