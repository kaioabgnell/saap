<?php

declare(strict_types=1);

namespace App\Support\Ia;

use App\Domain\Vbmapp\Report\SummaryBriefing;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente do Google AI Studio (Gemini) para o resumo de nível.
 *
 * Uma responsabilidade só: mandar o briefing e devolver texto. Não decide o
 * que enviar (isso é do SummaryBriefing), não decide se pode gerar (isso é do
 * GenerateAiSummary) e não grava nada.
 *
 * Erro aqui sempre vira RuntimeException com mensagem curta e SEM eco do
 * corpo da resposta: a resposta pode repetir o que foi enviado, e essa
 * mensagem termina em tela e em log. Ver a regra de "nenhum dado de aprendiz
 * vai para log" em docs/operacao.md.
 */
class GeminiSummarizer
{
    private const ENDERECO = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function configurado(): bool
    {
        return filled(config('services.gemini.key'));
    }

    public function resumir(SummaryBriefing $briefing): SummaryDraft
    {
        if (! $this->configurado()) {
            throw new RuntimeException('Resumo por IA não configurado.');
        }

        $modelo = (string) config('services.gemini.model');

        $resposta = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
            ->timeout((int) config('services.gemini.timeout'))
            // Uma repetição só: o PDF está esperando. Insistir mais seria
            // segurar a fila por um texto que é acessório.
            ->retry(2, 500, throw: false)
            ->post(self::ENDERECO."/{$modelo}:generateContent", [
                'systemInstruction' => ['parts' => [['text' => $briefing->instrucaoDoSistema()]]],
                'contents' => [[
                    'role' => 'user',
                    'parts' => [['text' => $briefing->dadosDaAvaliacao()]],
                ]],
                'generationConfig' => [
                    // Temperatura baixa: prontuário não é lugar de variação
                    // criativa. Dois resumos dos mesmos números devem se
                    // parecer.
                    'temperature' => 0.2,
                    'maxOutputTokens' => 1200,
                ],
            ]);

        if ($resposta->failed()) {
            throw new RuntimeException("Gemini respondeu {$resposta->status()}.");
        }

        $texto = $this->extrairTexto($resposta->json());

        if ($texto === '') {
            throw new RuntimeException('Gemini devolveu resposta vazia.');
        }

        return new SummaryDraft(
            body: $texto,
            model: $modelo,
            tokensUsed: $resposta->json('usageMetadata.totalTokenCount'),
        );
    }

    /** @param array<string, mixed>|null $corpo */
    private function extrairTexto(?array $corpo): string
    {
        $partes = [];

        foreach ($corpo['candidates'] ?? [] as $candidato) {
            foreach ($candidato['content']['parts'] ?? [] as $parte) {
                if (isset($parte['text'])) {
                    $partes[] = $parte['text'];
                }
            }
        }

        return trim(implode('', $partes));
    }
}
