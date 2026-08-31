<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('envia os cabeçalhos de segurança em toda resposta web', function () {
    $resposta = $this->actingAs(User::factory()->create())->get(route('painel'));

    $resposta->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($resposta->headers->get('Permissions-Policy'))->toContain('camera=()')
        ->and($resposta->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
});

it('envia os mesmos cabeçalhos na API', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('não declara default-src — derrubaria o Alpine e o Livewire', function () {
    // Regressão deliberada: `default-src` é o fallback de `script-src`. Com
    // ele, a tela de aplicação para de responder aos cliques sem erro visível.
    $csp = $this->actingAs(User::factory()->create())
        ->get(route('painel'))->headers->get('Content-Security-Policy');

    expect($csp)->not->toContain('default-src')
        ->and($csp)->not->toContain('script-src');
});

it('não envia HSTS fora de HTTPS', function () {
    // Em HTTP o cabeçalho não protege nada e tranca o navegador em
    // https://localhost por um ano.
    $this->actingAs(User::factory()->create())
        ->get(route('painel'))
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('exige senha de pelo menos 10 caracteres com letra e número', function () {
    $this->post(route('register'), [
        'name' => 'Ana', 'email' => 'ana@teste.test', 'phone' => '(11) 99999-0000',
        'password' => 'senha', 'password_confirmation' => 'senha',
    ])->assertSessionHasErrors('password');

    $this->post(route('register'), [
        'name' => 'Ana', 'email' => 'ana@teste.test', 'phone' => '(11) 99999-0000',
        'password' => 'senhaforte1', 'password_confirmation' => 'senhaforte1',
    ])->assertSessionHasNoErrors();
});
