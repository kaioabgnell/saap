<?php

declare(strict_types=1);

use App\Support\Content\ThresholdInferrer;

function bloco(array $campos = []): array
{
    return [
        'area' => 'mando',
        'position' => 1,
        'level' => 1,
        'statement' => null,
        'objective' => null,
        'materials' => null,
        'examples' => null,
        'criteria_full' => null,
        'criteria_half' => null,
        'observation_minutes' => null,
        ...$campos,
    ];
}

it('extrai o limiar do critério de um ponto', function () {
    $r = (new ThresholdInferrer)->infer(bloco([
        'statement' => 'Emite 4 mandos diferentes sem dicas.',
        'criteria_full' => 'Dê 1 ponto à criança se ela emitir mandos para 4 reforçadores diferentes.',
        'criteria_half' => 'Dê 1/2 ponto se criança que emitir 3 mandos deste tipo.',
    ]));

    expect($r['threshold_full'])->toBe(4)->and($r['threshold_half'])->toBe(3);
});

it('não confunde o valor do ponto com o limiar', function () {
    // "Dê 1 ponto" e "Dê 1/2 ponto" trazem números que não são limiar.
    $r = (new ThresholdInferrer)->infer(bloco([
        'statement' => 'Emite tatos para 10 itens.',
        'criteria_full' => 'Dê 1 ponto à criança se ela emitir tatos para 10 itens sem dicas ecóicas.',
        'criteria_half' => 'Dê 1/2 ponto à criança se ela emitir tatos para 8 itens.',
    ]));

    expect($r['threshold_full'])->toBe(10)->and($r['threshold_half'])->toBe(8);
});

it('devolve limiar nulo quando o manual nega o meio ponto', function () {
    $r = (new ThresholdInferrer)->infer(bloco([
        'statement' => 'Responde ao ouvir seu nome 5 vezes.',
        'criteria_full' => 'Dê 1 ponto à criança se ela olhar para o adulto em 5 tentativas.',
        'criteria_half' => 'Não há ½ ponto para esta habilidade.',
    ]));

    expect($r['threshold_half'])->toBeNull()->and($r['criteria_half'])->toBeNull();
});

it('reconhece a negativa lacônica de meio ponto', function () {
    $r = (new ThresholdInferrer)->infer(bloco([
        'criteria_full' => 'Dê 1 ponto à criança se ela imitar 20 ações.',
        'criteria_half' => 'Não tem',
    ]));

    expect($r['threshold_half'])->toBeNull()->and($r['criteria_half'])->toBeNull();
});

it('ignora tempo e percentual ao buscar o limiar', function () {
    $r = (new ThresholdInferrer)->infer(bloco([
        'statement' => 'Imita 8 movimentos motores.',
        'criteria_full' => 'Dê 1 ponto se ela imitar 8 movimentos em 30 min, em 80% das tentativas.',
        'criteria_half' => 'Dê 1/2 ponto se ela imitar 4 movimentos.',
    ]));

    expect($r['threshold_full'])->toBe(8)->and($r['threshold_half'])->toBe(4);
});

it('usa o meio ponto para descartar número acessório', function () {
    // Caso imitacao:6 — "arranjo de 3 itens" concorre com o limiar real.
    $r = (new ThresholdInferrer)->infer(bloco([
        'statement' => 'Imita10 ações que exigem selecionar de um arranjo de 3 itens.',
        'criteria_full' => 'Dê 1 ponto se ela imitar 10 ações com objeto selecionado de um arranjo de 3 itens.',
        'criteria_half' => 'Dê ½ ponto se ela imitar 5 ações com objeto de um arranjo de 3 itens.',
    ]));

    expect($r['threshold_full'])->toBe(10)->and($r['threshold_half'])->toBe(5);
});

it('reconhece número escrito por extenso', function () {
    $r = (new ThresholdInferrer)->infer(bloco([
        'statement' => 'Repete uma brincadeira em duas atividades diferentes.',
        'criteria_full' => 'Dê 1 ponto se ela repetir a brincadeira em duas atividades diferentes.',
    ]));

    expect($r['threshold_full'])->toBe(2);
});

it('trata critério de sentido invertido como escolha, não contagem', function () {
    // grupo:7 — menos dicas é melhor, então contar não serve.
    $r = (new ThresholdInferrer)->infer(bloco([
        'area' => 'grupo',
        'position' => 7,
        'statement' => 'Guarda objetos pessoais com apenas 1 dica verbal.',
        'criteria_full' => 'Dê 1 ponto se ela atender com apenas uma dica verbal.',
        'criteria_half' => 'Dê ½ ponto se ela necessitar de duas ou mais dicas verbais.',
    ]));

    expect($r['response_type'])->toBe('binary_criteria')
        ->and($r['threshold_full'])->toBe(2)
        ->and($r['threshold_half'])->toBe(1)
        ->and($r['confidence'])->toBe('low');
});

it('trata marco qualitativo como escolha', function () {
    $r = (new ThresholdInferrer)->infer(bloco([
        'area' => 'leitura',
        'position' => 14,
        'statement' => 'Lê seu próprio nome.',
        'criteria_full' => 'Dê 1 ponto à criança se ela ler seu próprio nome.',
    ]));

    expect($r['response_type'])->toBe('binary_criteria')
        ->and($r['threshold_full'])->toBe(2)
        ->and($r['threshold_half'])->toBeNull();
});

it('marca assisted quando os dois critérios pedem a mesma contagem', function () {
    // Mando 4-M: 5 mandos diferentes = 1 ponto; 5 sempre iguais = ½.
    $r = (new ThresholdInferrer)->infer(bloco([
        'area' => 'tato',
        'position' => 12,
        'statement' => 'Nomeia 5 itens diferentes.',
        'criteria_full' => 'Dê 1 ponto se ela nomear 5 itens diferentes.',
        'criteria_half' => 'Dê ½ ponto se ela nomear 5 itens, sempre os mesmos.',
    ]));

    expect($r['threshold_full'])->toBe($r['threshold_half'])
        ->and($r['scoring_mode'])->toBe('assisted');
});

it('classifica marco com material como counter_stimuli', function () {
    $r = (new ThresholdInferrer)->infer(bloco([
        'area' => 'tato',
        'position' => 3,
        'statement' => 'Nomeia 6 itens não reforçadores.',
        'criteria_full' => 'Dê 1 ponto se ela emitir tatos para 6 itens.',
        'criteria_half' => 'Dê 1/2 ponto se ela emitir tatos para 5 itens.',
    ]));

    expect($r['response_type'])->toBe('counter_stimuli')->and($r['confidence'])->toBe('high');
});
