<?php

test('a raiz redireciona para o login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('a tela de login responde', function () {
    $this->get('/login')->assertOk();
});
