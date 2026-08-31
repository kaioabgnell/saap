<?php

declare(strict_types=1);

namespace App\Http\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Formato de erro único da API.
 *
 * Mensagens em português porque vão direto para a interface do app — não são
 * log, são texto que o psicólogo lê. Os códigos são estáveis e o cliente pode
 * ramificar neles.
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        [$status, $code, $mensagem] = $this->classificar($e);

        return response()->json(array_filter([
            'message' => $mensagem,
            'code' => $code,
            'errors' => $e instanceof ValidationException ? $e->errors() : null,
        ], fn ($v) => $v !== null), $status);
    }

    /** @return array{int, string, string} */
    private function classificar(Throwable $e): array
    {
        return match (true) {
            $e instanceof AuthenticationException => [401, 'unauthenticated', 'Autenticação necessária.'],
            $e instanceof AuthorizationException => [403, 'forbidden', 'Você não tem acesso a este recurso.'],
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => [404, 'not_found', 'Recurso não encontrado.'],
            $e instanceof ValidationException => [422, 'validation_failed', 'Dados inválidos.'],

            // Precisa vir ANTES de RuntimeException: o Laravel converte
            // AuthorizationException em AccessDeniedHttpException, e toda
            // HttpException do Symfony estende RuntimeException — sem esta
            // linha, um 403 sairia como 409.
            $e instanceof HttpExceptionInterface => $this->classificarHttp($e),

            $e instanceof RuntimeException => $this->classificarConflito($e),
            default => [500, 'server_error', 'Erro interno.'],
        };
    }

    /** @return array{int, string, string} */
    private function classificarHttp(HttpExceptionInterface $e): array
    {
        $status = $e->getStatusCode();

        return match ($status) {
            401 => [401, 'unauthenticated', 'Autenticação necessária.'],
            403 => [403, 'forbidden', 'Você não tem acesso a este recurso.'],
            404 => [404, 'not_found', 'Recurso não encontrado.'],
            429 => [429, 'too_many_requests', 'Muitas requisições. Tente novamente em instantes.'],
            default => [$status, 'http_error', 'Não foi possível concluir a requisição.'],
        };
    }

    /**
     * Os casos de uso sinalizam conflito de estado com RuntimeException. A
     * mensagem já é escrita para o usuário final; aqui só derivamos o código
     * estável a partir dela.
     *
     * @return array{int, string, string}
     */
    private function classificarConflito(RuntimeException $e): array
    {
        $mensagem = $e->getMessage();

        $code = match (true) {
            str_contains($mensagem, 'concluída') => 'assessment_locked',
            str_contains($mensagem, 'não foi iniciado') => 'level_not_started',
            str_contains($mensagem, 'incompleto') => 'level_incomplete',
            str_contains($mensagem, 'já tem uma avaliação em aberto') => 'assessment_already_open',
            str_contains($mensagem, 'cancelada') => 'assessment_cancelled',
            default => 'conflict',
        };

        return [409, $code, $mensagem];
    }
}
