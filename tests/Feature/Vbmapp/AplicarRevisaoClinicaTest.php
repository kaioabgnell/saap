<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * O ciclo da revisão clínica: planilha sai, humano preenche, correções voltam.
 *
 * É o caminho por onde os 170 limiares serão corrigidos. Um erro aqui grava
 * limiar errado no catálogo sem gerar erro visível — e limiar errado contamina
 * todo laudo emitido.
 */
beforeEach(function () {
    $this->catalogo = storage_path('app/vbmapp/catalogo.json');
    $this->original = File::exists($this->catalogo) ? File::get($this->catalogo) : null;

    $this->planilha = storage_path('app/vbmapp/revisao-teste.csv');
});

afterEach(function () {
    if ($this->original !== null) {
        File::put($this->catalogo, $this->original);
    }

    File::delete($this->planilha);
});

function planilhaCom(string $caminho, array $linhas): void
{
    $colunas = [
        'prioridade', 'confianca', 'area', 'area_code', 'nivel', 'marco', 'codigo',
        'enunciado', 'criterio_1_ponto', 'criterio_meio_ponto',
        'tipo_inferido', 'limiar_meio', 'limiar_cheio', 'modo',
        'obs_minutos', 'corrigido_manualmente',
        'CONFERIDO_SN', 'TIPO_CORRIGIDO', 'MEIO_CORRIGIDO', 'CHEIO_CORRIGIDO', 'COMENTARIO',
    ];

    $handle = fopen($caminho, 'w');
    fwrite($handle, "\u{FEFF}");
    fputcsv($handle, $colunas);

    foreach ($linhas as $linha) {
        fputcsv($handle, array_map(fn ($c) => $linha[$c] ?? '', $colunas));
    }

    fclose($handle);
}

function marcoDoCatalogo(string $chave): array
{
    return json_decode(File::get(storage_path('app/vbmapp/catalogo.json')), true)['marcos'][$chave];
}

it('aplica a correção de limiar que o psicólogo preencheu', function () {
    $antes = marcoDoCatalogo('mando:2');

    planilhaCom($this->planilha, [
        ['area_code' => 'mando', 'marco' => '2', 'CONFERIDO_SN' => 'S', 'CHEIO_CORRIGIDO' => '9'],
    ]);

    $this->artisan('vbmapp:apply-review', ['--planilha' => $this->planilha])->assertSuccessful();

    expect(marcoDoCatalogo('mando:2')['threshold_full'])->toBe(9)
        ->and($antes['threshold_full'])->not->toBe(9);
});

it('trata célula vazia como "não mexer", nunca como "apagar"', function () {
    $antes = marcoDoCatalogo('mando:2');

    planilhaCom($this->planilha, [
        ['area_code' => 'mando', 'marco' => '2', 'CONFERIDO_SN' => 'S'],
    ]);

    $this->artisan('vbmapp:apply-review', ['--planilha' => $this->planilha])->assertSuccessful();

    expect(marcoDoCatalogo('mando:2'))
        ->threshold_full->toBe($antes['threshold_full'])
        ->threshold_half->toBe($antes['threshold_half'])
        ->response_type->toBe($antes['response_type']);
});

it('entende "nulo" como marco sem meio ponto', function () {
    // O caso do Ouvinte 2: o manual diz que não há ½. Sem uma palavra para
    // isso, seria indistinguível de célula não preenchida.
    planilhaCom($this->planilha, [
        ['area_code' => 'mando', 'marco' => '2', 'MEIO_CORRIGIDO' => 'nulo'],
    ]);

    $this->artisan('vbmapp:apply-review', ['--planilha' => $this->planilha])->assertSuccessful();

    expect(marcoDoCatalogo('mando:2')['threshold_half'])->toBeNull();
});

it('recusa a planilha inteira quando uma linha está errada', function () {
    $antes = marcoDoCatalogo('mando:2');

    planilhaCom($this->planilha, [
        ['area_code' => 'mando', 'marco' => '2', 'CHEIO_CORRIGIDO' => '9'],
        ['area_code' => 'ecoico', 'marco' => '1', 'MEIO_CORRIGIDO' => '9', 'CHEIO_CORRIGIDO' => '2'],
    ]);

    $this->artisan('vbmapp:apply-review', ['--planilha' => $this->planilha])->assertFailed();

    // Tudo ou nada: a correção boa também não entra, para o psicólogo não
    // ficar com metade da revisão aplicada sem saber qual metade.
    expect(marcoDoCatalogo('mando:2')['threshold_full'])->toBe($antes['threshold_full']);
});

it('recusa tipo de resposta que não existe', function () {
    planilhaCom($this->planilha, [
        ['area_code' => 'mando', 'marco' => '2', 'TIPO_CORRIGIDO' => 'contador_livre'],
    ]);

    $this->artisan('vbmapp:apply-review', ['--planilha' => $this->planilha])->assertFailed();
});

it('recusa marco que não existe no catálogo', function () {
    planilhaCom($this->planilha, [
        ['area_code' => 'inexistente', 'marco' => '1', 'CHEIO_CORRIGIDO' => '3'],
    ]);

    $this->artisan('vbmapp:apply-review', ['--planilha' => $this->planilha])->assertFailed();
});

it('não grava nada em dry-run', function () {
    $antes = marcoDoCatalogo('mando:2');

    planilhaCom($this->planilha, [
        ['area_code' => 'mando', 'marco' => '2', 'CHEIO_CORRIGIDO' => '9'],
    ]);

    $this->artisan('vbmapp:apply-review', ['--planilha' => $this->planilha, '--dry-run' => true])
        ->assertSuccessful();

    expect(marcoDoCatalogo('mando:2')['threshold_full'])->toBe($antes['threshold_full']);
});

it('protege o limiar revisado de uma nova rodada do parser', function () {
    planilhaCom($this->planilha, [
        ['area_code' => 'mando', 'marco' => '2', 'CHEIO_CORRIGIDO' => '9'],
    ]);

    $this->artisan('vbmapp:apply-review', ['--planilha' => $this->planilha])->assertSuccessful();

    expect(marcoDoCatalogo('mando:2'))
        ->toHaveKey('_pos_inferencia')
        ->toHaveKey('revisado_clinicamente');
});
