<?php

declare(strict_types=1);

/**
 * Contraste WCAG AA dos tokens do design system.
 *
 * Existe porque a F9 encontrou três reprovações que ninguém veria olhando a
 * tela: o âmbar do ½ ponto (3,19:1), o verde do 1 ponto (3,77:1) e o cinza do
 * texto secundário (2,56:1). Todos "parecem" legíveis para quem enxerga bem
 * num monitor bom.
 *
 * A distinção que o teste codifica: **preenchimento** (célula do gráfico) tem
 * mínimo 3:1 porque é objeto gráfico; **texto** tem mínimo 4,5:1. Por isso
 * `success`/`warning` têm um par DEFAULT (preenchimento) e `ink` (texto).
 */
function luminancia(string $hex): float
{
    $hex = ltrim($hex, '#');
    $canais = [];

    foreach ([0, 2, 4] as $i) {
        $c = hexdec(substr($hex, $i, 2)) / 255;
        $canais[] = $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }

    return 0.2126 * $canais[0] + 0.7152 * $canais[1] + 0.0722 * $canais[2];
}

function contraste(string $frente, string $fundo): float
{
    $a = luminancia($frente);
    $b = luminancia($fundo);

    return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
}

const SURFACE = '#FFFFFF';
const CANVAS = '#F8FAFC';

it('aprova em AA toda cor usada como texto', function () {
    $texto = [
        'primary' => '#4338CA',
        'danger' => '#BE123C',
        'ink' => '#0F172A',
        'ink-muted' => '#475569',
        'ink-subtle' => '#64748B',
        'success-ink' => '#047857',
        'warning-ink' => '#B45309',
    ];

    foreach ($texto as $nome => $cor) {
        foreach (['surface' => SURFACE, 'canvas' => CANVAS] as $fundo => $hex) {
            $razao = contraste($cor, $hex);

            expect($razao)->toBeGreaterThanOrEqual(
                4.5,
                sprintf('%s sobre %s: %.2f:1 — reprovado em AA para texto', $nome, $fundo, $razao),
            );
        }
    }
});

it('aprova em AA as cores de preenchimento do gráfico de marcos', function () {
    // Objeto gráfico: o mínimo é 3:1, não 4,5.
    //
    // O gráfico usa UMA cor só: meio ponto e ponto inteiro são o mesmo verde,
    // e o que os separa é a altura preenchida. O âmbar continua no sistema
    // (pendências, pontuação sobrescrita), mas não é mais preenchimento de
    // célula — por isso saiu daqui.
    foreach (['success' => '#059669'] as $nome => $cor) {
        $razao = contraste($cor, SURFACE);

        expect($razao)->toBeGreaterThanOrEqual(3.0, sprintf('%s: %.2f:1', $nome, $razao));
    }
});

it('mantém os tokens do Tailwind iguais aos verificados aqui', function () {
    // Sem isto, alguém muda o hex no tailwind.config.js e o teste continua
    // aprovando a cor antiga.
    $config = file_get_contents(base_path('tailwind.config.js'));

    foreach ([
        "success: { DEFAULT: '#059669', ink: '#047857'",
        "warning: { DEFAULT: '#D97706', ink: '#B45309'",
        "subtle: '#64748B'",
    ] as $trecho) {
        expect($config)->toContain($trecho);
    }
});

it('não deixa texto usar a cor de preenchimento', function () {
    // `text-success` e `text-warning` reprovam em AA. O par correto é
    // `text-success-ink` / `text-warning-ink`.
    $ofensores = [];
    $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($views as $arquivo) {
        if (! str_ends_with((string) $arquivo, '.blade.php')) {
            continue;
        }

        if (preg_match('/text-(success|warning)(?![-\w])/', file_get_contents((string) $arquivo))) {
            $ofensores[] = basename((string) $arquivo);
        }
    }

    expect($ofensores)->toBeEmpty('use text-success-ink/text-warning-ink em: '.implode(', ', $ofensores));
});

const PAINEL_ENTRADA = '#0B1026';

it('aprova em AA os tons do painel escuro da tela de entrada', function () {
    // O painel de entrada tem paleta própria — é o navy da logo, não o
    // `canvas` do sistema. Sem este teste, os tons dele ficariam sem nenhuma
    // guarda, que é justamente onde texto ilegível costuma entrar: cinza-azulado
    // sobre azul-escuro parece legível num monitor bom e some num ruim.
    $texto = [
        'branco (título e tópicos)' => '#FFFFFF',
        'corpo' => '#A9B4DA',
        'eyebrow e legenda' => '#8FA0D8',
        'rótulo de nível' => '#7E8CBB',
        'linha de licença' => '#6F7CAB',
    ];

    foreach ($texto as $nome => $cor) {
        $razao = contraste($cor, PAINEL_ENTRADA);

        expect($razao)->toBeGreaterThanOrEqual(
            4.5,
            sprintf('%s no painel escuro: %.2f:1 — reprovado em AA para texto', $nome, $razao),
        );
    }
});

it('aprova em AA os preenchimentos do gráfico no painel escuro', function () {
    // Sobre navy as semânticas usam o degrau mais claro (emerald-500 e
    // amber-500) em vez dos tokens de fundo claro — o mesmo que qualquer
    // paleta escura faz. Objeto gráfico: mínimo 3:1.
    foreach (['1 ponto' => '#10B981', 'meio ponto' => '#F59E0B', 'ícones' => '#7C8CFF'] as $nome => $cor) {
        $razao = contraste($cor, PAINEL_ENTRADA);

        expect($razao)->toBeGreaterThanOrEqual(3.0, sprintf('%s: %.2f:1', $nome, $razao));
    }
});

it('mantém o painel de entrada usando o navy verificado aqui', function () {
    $layout = file_get_contents(resource_path('views/layouts/guest.blade.php'));

    expect($layout)->toContain('bg-['.PAINEL_ENTRADA.']');
});
