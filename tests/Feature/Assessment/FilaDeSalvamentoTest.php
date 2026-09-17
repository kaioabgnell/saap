<?php

declare(strict_types=1);

/**
 * Guardas sobre `resources/js/fila-salvamento.js`.
 *
 * O projeto não tem executor de teste de JS, e montar um só para este arquivo
 * seria desproporcional. Estas asserções cobrem o que já quebrou de verdade —
 * na forma que o resto da suíte já usa para código que não é PHP (ver os
 * testes de acessibilidade, que varrem as Blades).
 */
function filaDeSalvamentoJs(): string
{
    return file_get_contents(resource_path('js/fila-salvamento.js'));
}

/**
 * O defeito: o indicador era semeado com o número guardado no localStorage,
 * mas a fila em memória nasce vazia a cada carregamento. Resultado — um único
 * incidente passado deixava "Sem conexão — tentando salvar de novo (1
 * alteração)" fixo na tela, imune a recarregamento e a salvamentos bem
 * sucedidos, porque `succeed()` não tinha o que remover e nunca notificava.
 */
it('não semeia o indicador com número guardado do navegador', function () {
    $js = filaDeSalvamentoJs();

    expect($js)->toContain('pendentes: 0')
        ->toContain('travados: 0')
        // A leitura do armazenamento saiu inteira; sobrou só o removeItem
        // que limpa resíduo de versões anteriores.
        ->and($js)->not->toContain('lerPersistido')
        ->and($js)->not->toContain('localStorage.getItem');
});

it('um indicador de estado precisa poder voltar a zero sozinho', function () {
    $js = filaDeSalvamentoJs();

    // Quem alimenta o número na tela é o evento, e quem dispara o evento é
    // sempre a fila viva — nunca o armazenamento.
    expect($js)->toContain("new CustomEvent('saap:fila-alterada'")
        ->toContain('detail: { pendentes: pendentes.size, travados: travados.size }');
});

it('reenvia a ação que falhou, não uma ação fixa', function () {
    $js = filaDeSalvamentoJs();

    // Chamar '.call("salvar")' cegamente quebrava em todo componente sem esse
    // método — o LevelBoard é um —, e a tentativa falhava para sempre.
    expect($js)->toContain('componente.call(method, ...(params ?? []))')
        ->and($js)->not->toContain("call('salvar')");
});

it('desiste depois de um limite, em vez de insistir para sempre', function () {
    $js = filaDeSalvamentoJs();

    // Sessão vencida devolve o mesmo erro sempre: reenviar nunca converge, e
    // insistir em silêncio é o que deixava a psicóloga sem caminho de volta.
    expect($js)->toContain('LIMITE_DE_TENTATIVAS')
        ->toContain('travados.add(component.id)');
});
