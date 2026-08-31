<?php

declare(strict_types=1);

use App\Domain\Vbmapp\Scoring\ResponseType;
use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\Item;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);
});

it('semeia exatamente 16 áreas', function () {
    expect(Area::count())->toBe(16);
});

it('semeia exatamente 170 marcos', function () {
    expect(Item::count())->toBe(170);
});

it('distribui 45, 60 e 65 marcos por nível', function () {
    expect(Item::where('level', 1)->count())->toBe(45)
        ->and(Item::where('level', 2)->count())->toBe(60)
        ->and(Item::where('level', 3)->count())->toBe(65);
});

it('dá 5 marcos a cada par área/nível', function () {
    $desviantes = Item::query()
        ->selectRaw('area_id, level, COUNT(*) as total')
        ->groupBy('area_id', 'level')
        ->havingRaw('COUNT(*) <> 5')
        ->get();

    expect($desviantes)->toBeEmpty();
});

it('respeita a matriz de áreas por nível', function () {
    foreach (VbmappAreaSeeder::AREAS as $code => [, , $niveis]) {
        $area = Area::where('code', $code)->sole();

        foreach ([1, 2, 3] as $nivel) {
            $total = Item::where('area_id', $area->id)->where('level', $nivel)->count();
            $esperado = in_array($nivel, $niveis, true) ? 5 : 0;

            expect($total)->toBe($esperado, "{$code} no nível {$nivel}");
        }
    }
});

it('numera os marcos continuamente entre níveis', function () {
    foreach ([1 => [1, 5], 2 => [6, 10], 3 => [11, 15]] as $nivel => [$de, $ate]) {
        $fora = Item::where('level', $nivel)
            ->where(fn ($q) => $q->where('position', '<', $de)->orWhere('position', '>', $ate))
            ->count();

        expect($fora)->toBe(0, "nível {$nivel} deve usar posições {$de}-{$ate}");
    }
});

it('preenche todo campo obrigatório', function () {
    $incompletos = Item::query()
        ->whereNull('statement')
        ->orWhereNull('criteria_full')
        ->orWhereNull('response_type')
        ->orWhereNull('threshold_full')
        ->pluck('code');

    expect($incompletos)->toBeEmpty();
});

it('mantém threshold_half menor ou igual a threshold_full', function () {
    // Igualdade é legítima: significa critério qualitativo (ScoringMode::Assisted).
    $invalidos = Item::whereNotNull('threshold_half')
        ->whereColumn('threshold_half', '>', 'threshold_full')
        ->pluck('code');

    expect($invalidos)->toBeEmpty();
});

it('permite marco sem meio ponto', function () {
    // Ouvinte 2-M: "Não há ½ ponto para esta habilidade" (manual, p. 80).
    $ouvinte = Area::where('code', 'ouvinte')->sole();
    $marco = Item::where('area_id', $ouvinte->id)->where('position', 2)->sole();

    expect($marco->threshold_half)->toBeNull()
        ->and($marco->criteria_half)->toBeNull()
        ->and($marco->hasHalfPoint())->toBeFalse();
});

it('mantém criteria_half e threshold_half sempre juntos', function () {
    $divergentes = Item::query()
        ->where(fn ($q) => $q->whereNull('criteria_half')->whereNotNull('threshold_half'))
        ->orWhere(fn ($q) => $q->whereNotNull('criteria_half')->whereNull('threshold_half'))
        ->pluck('code');

    expect($divergentes)->toBeEmpty();
});

it('marca como assisted quando os limiares coincidem', function () {
    Item::whereColumn('threshold_half', 'threshold_full')->get()
        ->each(fn (Item $i) => expect($i->scoring_mode->requiresConfirmation())->toBeTrue($i->code));
});

it('usa apenas tipos de resposta conhecidos', function () {
    $tipos = array_map(fn (ResponseType $t) => $t->value, ResponseType::cases());

    expect(Item::whereNotIn('response_type', $tipos)->count())->toBe(0);
});

it('confere os limiares do nível 1 verificados no manual', function () {
    // Conferidos linha a linha no manual traduzido durante o planejamento.
    $esperado = [
        ['mando', 1, 1, 2], ['mando', 2, 3, 4], ['mando', 3, 3, 6], ['mando', 5, 8, 10],
        ['tato', 1, 1, 2], ['tato', 2, 3, 4], ['tato', 3, 5, 6], ['tato', 5, 8, 10],
        ['ouvinte', 3, 2, 5], ['ouvinte', 4, 2, 4], ['ouvinte', 5, 15, 20],
    ];

    foreach ($esperado as [$code, $posicao, $meio, $cheio]) {
        $area = Area::where('code', $code)->sole();
        $marco = Item::where('area_id', $area->id)->where('position', $posicao)->sole();

        expect($marco->threshold_half)->toBe($meio, "{$code} {$posicao} meio ponto")
            ->and($marco->threshold_full)->toBe($cheio, "{$code} {$posicao} um ponto");
    }
});

it('marca os marcos com material de aplicação como counter_stimuli', function () {
    // Os sete do nível 1 que o registro marca "Tem no material de aplicação".
    $comMaterial = [['tato', 1], ['tato', 2], ['tato', 3], ['tato', 5], ['ouvinte', 3], ['ouvinte', 5], ['vpmts', 5]];

    foreach ($comMaterial as [$code, $posicao]) {
        $area = Area::where('code', $code)->sole();
        $marco = Item::where('area_id', $area->id)->where('position', $posicao)->sole();

        expect($marco->response_type)->toBe(ResponseType::CounterStimuli, "{$code} {$posicao}");
    }
});
