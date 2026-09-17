<?php

declare(strict_types=1);

use App\Domain\Contact\PhoneNumber;

it('normaliza celular escrito como a psicóloga digita', function (string $bruto) {
    expect(PhoneNumber::doBrasil($bruto)?->paraWhatsApp())->toBe('5511900000000');
})->with([
    '(11) 90000-0000',
    '11900000000',
    '11 90000 0000',
    '+55 11 90000-0000',
    '5511900000000',
    '+5511900000000',
    '  (11)  90000 . 0000  ',
]);

it('normaliza telefone fixo', function () {
    $fixo = PhoneNumber::doBrasil('(11) 3000-0000');

    expect($fixo?->paraWhatsApp())->toBe('551130000000')
        ->and($fixo?->isCelular())->toBeFalse();
});

it('devolve o E.164 com o mais', function () {
    expect(PhoneNumber::doBrasil('(11) 90000-0000')?->paraE164())->toBe('+5511900000000');
});

it('formata para exibição', function () {
    expect(PhoneNumber::doBrasil('11900000000')?->formatado())->toBe('(11) 90000-0000');
    expect(PhoneNumber::doBrasil('1130000000')?->formatado())->toBe('(11) 3000-0000');
});

it('devolve nulo em vez de chutar um número', function (?string $bruto) {
    expect(PhoneNumber::doBrasil($bruto))->toBeNull();
})->with([
    'nulo' => null,
    'vazio' => '',
    'só texto' => 'não tem',
    'curto demais' => '99999',
    'longo demais' => '1199000000000000',
    'DDD começando em zero' => '(01) 90000-0000',
    'DDD terminando em zero' => '(10) 90000-0000',
    'celular de 9 dígitos sem o 9' => '(11) 80000-0000',
    'formato americano do faker' => '1-555-555-5555',
]);

/**
 * O caso ambíguo, e o motivo de ele ser recusado: um número de 8 dígitos
 * começando em 9 é celular no formato antigo. Completar com o nono dígito
 * seria inventar um destinatário — e a mensagem fala de uma criança.
 */
it('recusa celular antigo de oito dígitos em vez de inserir o nono', function () {
    expect(PhoneNumber::doBrasil('(11) 9000-0000'))->toBeNull();
});
