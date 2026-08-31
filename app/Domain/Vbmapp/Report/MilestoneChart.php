<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Report;

/**
 * O gráfico de marcos do VB-MAPP, no padrão de refs/Grafico VB-MAPP.xlsx:
 * uma coluna por área do nível, cinco células empilhadas, o marco de menor
 * número embaixo.
 *
 * Cada célula vira DUAS meias-células na renderização — é o único jeito de
 * pintar meio ponto em HTML e em dompdf com a mesma marcação, sem gradiente
 * nem pseudo-elemento. Ver 03-design-system.md.
 */
final class MilestoneChart
{
    public const ESTADO_CHEIO = 'full';

    public const ESTADO_MEIO = 'half';

    public const ESTADO_ZERO = 'zero';

    public const ESTADO_PENDENTE = 'pending';

    /**
     * @param  list<array{code: string, short_name: string, name: string, cells: list<array{position: int, state: string, label: string, code: string, statement: string, score: string, answer: string}>}>  $columns
     */
    public function __construct(
        public readonly int $level,
        public readonly array $columns,
    ) {}

    /**
     * Monta o gráfico a partir das áreas do snapshot.
     *
     * @param  list<array<string, mixed>>  $areas  Estrutura de ReportPayload
     */
    public static function fromAreas(int $level, array $areas): self
    {
        $columns = [];

        foreach ($areas as $area) {
            $cells = [];

            foreach ($area['marcos'] as $marco) {
                $estado = self::estadoDe($marco);

                $cells[] = [
                    'position' => $marco['posicao'],
                    'state' => $estado,
                    // Cor nunca é o único portador de informação — cada célula
                    // carrega o próprio rótulo para leitor de tela.
                    'label' => sprintf(
                        '%s, marco %d: %s',
                        $area['short_name'],
                        $marco['posicao'],
                        self::rotuloDoEstado($estado),
                    ),
                    // O que a célula precisa dizer quando alguém para o mouse
                    // (ou o foco) em cima dela. Sai tudo do snapshot, nunca do
                    // catálogo atual: o gráfico de um laudo emitido continua
                    // mostrando o enunciado que valia no dia da conclusão.
                    'area' => $area['name'],
                    'code' => $marco['codigo'],
                    'statement' => $marco['enunciado'],
                    'score' => self::rotuloDoEstado($estado),
                    'answer' => self::respostaDe($marco),
                ];
            }

            // O marco de menor número fica embaixo, como na planilha original.
            usort($cells, fn (array $a, array $b) => $b['position'] <=> $a['position']);

            $columns[] = [
                'code' => $area['code'],
                'short_name' => $area['short_name'],
                'name' => $area['name'],
                'cells' => $cells,
            ];
        }

        return new self($level, $columns);
    }

    /** @param array<string, mixed> $marco */
    private static function estadoDe(array $marco): string
    {
        if (! ($marco['respondido'] ?? false)) {
            return self::ESTADO_PENDENTE;
        }

        $score = (float) ($marco['score'] ?? 0);

        return match (true) {
            $score >= 1.0 => self::ESTADO_CHEIO,
            $score >= 0.5 => self::ESTADO_MEIO,
            default => self::ESTADO_ZERO,
        };
    }

    /**
     * O que foi registrado no marco, em uma linha.
     *
     * Marco não respondido não tem resposta — e dizer "0 ponto" ali seria
     * mentira: zero é uma pontuação deliberada, ausência de resposta não é.
     *
     * @param  array<string, mixed>  $marco
     */
    private static function respostaDe(array $marco): string
    {
        if (! ($marco['respondido'] ?? false)) {
            return '';
        }

        $partes = [];

        if (($marco['exemplares'] ?? []) !== []) {
            $partes[] = implode(', ', $marco['exemplares']);
        }

        if (trim((string) ($marco['observacoes'] ?? '')) !== '') {
            $partes[] = 'Obs.: '.trim((string) $marco['observacoes']);
        }

        return implode(' · ', $partes);
    }

    private static function rotuloDoEstado(string $estado): string
    {
        return match ($estado) {
            self::ESTADO_CHEIO => '1 ponto',
            self::ESTADO_MEIO => 'meio ponto',
            self::ESTADO_ZERO => '0 ponto',
            default => 'não respondido',
        };
    }

    /** As posições de marco deste nível, de cima para baixo (15..11, 10..6, 5..1). */
    public function positionsTopDown(): array
    {
        $fim = $this->level * 5;

        return range($fim, $fim - 4);
    }
}
