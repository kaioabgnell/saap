<?php

declare(strict_types=1);

use App\Models\Learner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * O que dá para verificar por automação. O resto — leitor de tela de verdade,
 * navegação por teclado numa sessão inteira, o iPad na mão da psicóloga — é a
 * validação em campo da F9, e nenhum teste substitui.
 */
beforeEach(function () {
    $this->psicologa = User::factory()->create();
    $this->actingAs($this->psicologa);
});

it('associa todo campo do cadastro de aprendiz a um rótulo', function () {
    $html = $this->get(route('aprendizes.create'))->assertOk()->getContent();

    preg_match_all('/<(?:input|textarea|select)\b[^>]*\bid="([^"]+)"/', $html, $campos);
    preg_match_all('/<label\b[^>]*\bfor="([^"]+)"/', $html, $rotulos);

    $semRotulo = array_diff($campos[1], $rotulos[1]);

    expect($semRotulo)->toBeEmpty('campos sem <label for>: '.implode(', ', $semRotulo))
        ->and($campos[1])->not->toBeEmpty();
});

it('associa a mensagem de erro ao campo por aria-describedby', function () {
    $resposta = $this->from(route('aprendizes.create'))
        ->post(route('aprendizes.store'), ['name' => '', 'birth_date' => ''])
        ->assertRedirect(route('aprendizes.create'));

    $html = $this->followingRedirects()->get(route('aprendizes.create'))->getContent();

    // O erro precisa ter id previsível e o campo precisa apontar para ele —
    // sem isso o leitor de tela anuncia o campo sem dizer o que houve.
    expect($html)->toContain('id="name-erro"')
        ->toContain('aria-describedby="name-erro"')
        ->toContain('aria-invalid="true"');

    expect($resposta)->not->toBeNull();
});

it('dá alternativa textual a toda imagem', function () {
    Learner::factory()->for($this->psicologa)->create();

    foreach ([route('painel'), route('aprendizes.index')] as $url) {
        $html = $this->get($url)->assertOk()->getContent();

        preg_match_all('/<img\b(?![^>]*\balt=)[^>]*>/', $html, $semAlt);

        expect($semAlt[0])->toBeEmpty("imagens sem alt em {$url}: ".implode(' ', $semAlt[0]));
    }
});

it('anuncia o estado do menu do usuário', function () {
    $html = $this->get(route('painel'))->assertOk()->getContent();

    expect($html)->toContain('aria-haspopup="menu"')
        ->toContain('aria-expanded');
});

it('não deixa controle interativo sem indicação de foco', function () {
    // `focus:outline-none` sem anel de substituição deixa quem navega por
    // teclado sem saber onde está.
    $arquivos = [];
    $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
    $vistos = 0;

    foreach ($views as $arquivo) {
        if (! str_ends_with((string) $arquivo, '.blade.php')) {
            continue;
        }

        $vistos++;
        $conteudo = file_get_contents((string) $arquivo);

        foreach (explode("\n", $conteudo) as $n => $linha) {
            if (str_contains($linha, 'focus:outline-none')
                && ! str_contains($linha, 'focus:ring')
                && ! str_contains($linha, 'focus-visible:ring')) {
                $arquivos[] = basename($arquivo).':'.($n + 1);
            }
        }
    }

    // Guarda contra o teste que passa por não ter varrido nada.
    expect($vistos)->toBeGreaterThan(30)
        ->and($arquivos)->toBeEmpty('sem anel de foco: '.implode(', ', $arquivos));
});

it('respeita quem pediu menos movimento ao sistema', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('prefers-reduced-motion');
});
