<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança de toda resposta web.
 *
 * Sobre a Content-Security-Policy: **não** há `script-src` aqui, e é decisão,
 * não esquecimento. O Alpine avalia expressões de atributo em tempo de
 * execução (`x-on:click="..."`) e exige `'unsafe-eval'`; o Livewire injeta um
 * bloco inline com a própria configuração e exige `'unsafe-inline'`. Uma CSP
 * que concede os dois não barra praticamente nenhum XSS — seria teatro num
 * cabeçalho que passa a impressão de proteger.
 *
 * O que **tem** valor real está aqui: `frame-ancestors` (que substitui o
 * X-Frame-Options e é respeitado por navegador moderno), `object-src`,
 * `base-uri` e `form-action`. Nenhum depende do Alpine.
 *
 * Também não há `default-src`: ele é o fallback de `script-src`, e bastaria um
 * `default-src 'self'` para a tela de aplicação parar de responder — sem erro
 * visível, só os cliques deixando de funcionar.
 *
 * Uma CSP de script séria exige nonce por requisição propagado ao Livewire —
 * trabalho de uma fase própria, registrado como fora do escopo da v1.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // O sistema não usa câmera, microfone nem localização. Negar por
        // padrão fecha a porta caso alguma dependência futura tente abrir.
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()'
        );

        // Sem `default-src`: ele é o fallback de `script-src`, e um
        // `default-src 'self'` derrubaria o Alpine (avaliação de expressão) e
        // o bloco inline do Livewire — a tela de aplicação simplesmente
        // pararia de responder, sem erro visível para o usuário. As diretivas
        // abaixo são justamente as que não têm esse efeito colateral.
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",   // laudo de criança não vai em iframe alheio
        ]));

        // HSTS só faz sentido — e só é seguro — sobre HTTPS. Enviá-lo em HTTP
        // não protege nada e, em desenvolvimento, tranca o navegador em
        // https://localhost por meses.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
