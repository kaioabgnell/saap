<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Chart;

use InvalidArgumentException;

/**
 * A grade editável do lançamento retroativo: o mesmo desenho do gráfico de
 * marcos do laudo, só que clicável e alimentada pelo catálogo atual.
 *
 * Não confundir com `Report\MilestoneChart`. Aquele monta o gráfico a partir
 * do SNAPSHOT de um laudo já emitido, e por isso não pode consultar o
 * catálogo — se um enunciado mudar, o laudo antigo continua mostrando o que
 * valia no dia. Esta monta a partir do catálogo VIGENTE, porque é onde a
 * transcrição está sendo digitada agora. Fontes diferentes de propósito; o
 * que as duas compartilham é o CSS.
 *
 * PHP puro: recebe arrays, devolve arrays. Nada de Eloquent — quem lê o banco
 * é `Application\Assessment\BuildChartGrid`.
 */
final class ChartGrid
{
    public const ESTADO_CHEIO = 'full';

    public const ESTADO_MEIO = 'half';

    public const ESTADO_ZERO = 'zero';

    public const ESTADO_PENDENTE = 'pending';

    public const METADE_CIMA = 'top';

    public const METADE_BAIXO = 'bottom';

    /**
     * @param  list<array{code: string, name: string, short_name: string, cells: list<array<string, mixed>>}>  $columns
     */
    public function __construct(
        public readonly int $level,
        public readonly array $columns,
    ) {}

    /**
     * @param  list<array{code: string, name: string, short_name: string, items: list<array{id: int, position: int, code: string, statement: string, has_half_point: bool}>}>  $areas
     * @param  array<int, float|null>  $scores  pontuação por id de marco; ausente = pendente
     */
    public static function build(int $level, array $areas, array $scores): self
    {
        $columns = [];

        foreach ($areas as $area) {
            $cells = [];

            foreach ($area['items'] as $item) {
                $pontos = array_key_exists($item['id'], $scores) ? $scores[$item['id']] : null;
                $estado = self::estadoDe($pontos);

                $cells[] = [
                    'item_id' => $item['id'],
                    'position' => $item['position'],
                    'code' => $item['code'],
                    'statement' => $item['statement'],
                    'area' => $area['name'],
                    'has_half_point' => $item['has_half_point'],
                    'score' => $pontos,
                    'state' => $estado,
                    // Cor nunca é o único portador de informação, nem numa
                    // tela de digitação: é por este rótulo que o leitor de
                    // tela sabe em que célula o foco está.
                    'label' => sprintf(
                        '%s, marco %d: %s',
                        $area['short_name'],
                        $item['position'],
                        self::rotuloDoEstado($estado),
                    ),
                ];
            }

            // O marco de menor número fica embaixo, como na planilha original.
            usort($cells, fn (array $a, array $b) => $b['position'] <=> $a['position']);

            $columns[] = [
                'code' => $area['code'],
                'name' => $area['name'],
                'short_name' => $area['short_name'],
                'cells' => $cells,
            ];
        }

        return new self($level, $columns);
    }

    /**
     * A regra do clique.
     *
     * Cada metade da célula é um interruptor de meio ponto: a de baixo vale o
     * primeiro, a de cima o segundo. Clicar em cima liga as duas, porque um
     * ponto inteiro com a metade de baixo apagada não é um estado que exista
     * no gráfico de papel.
     *
     * Voltar a PENDENTE não sai daqui — é o `null` do teclado ou do
     * "limpar nível". Pendente é o estado de quem nunca foi tocado, e um
     * clique é toque.
     */
    public static function nextScore(?float $atual, string $metade, bool $temMeioPonto): float
    {
        if (! in_array($metade, [self::METADE_CIMA, self::METADE_BAIXO], true)) {
            throw new InvalidArgumentException("Metade inválida: {$metade}.");
        }

        // Ouvinte 2 e afins: o manual não prevê meio ponto, então as duas
        // metades viram um interruptor só. Ver `threshold_half` anulável.
        if (! $temMeioPonto) {
            return $atual === 1.0 ? 0.0 : 1.0;
        }

        if ($metade === self::METADE_CIMA) {
            return $atual === 1.0 ? 0.5 : 1.0;
        }

        return match ($atual) {
            0.5, 1.0 => 0.0,
            default => 0.5,
        };
    }

    public static function estadoDe(?float $score): string
    {
        return match (true) {
            $score === null => self::ESTADO_PENDENTE,
            $score >= 1.0 => self::ESTADO_CHEIO,
            $score >= 0.5 => self::ESTADO_MEIO,
            default => self::ESTADO_ZERO,
        };
    }

    public static function rotuloDoEstado(string $estado): string
    {
        return match ($estado) {
            self::ESTADO_CHEIO => '1 ponto',
            self::ESTADO_MEIO => 'meio ponto',
            self::ESTADO_ZERO => '0 ponto',
            default => 'não marcado',
        };
    }

    /** As posições de marco deste nível, de cima para baixo (15..11, 10..6, 5..1). */
    public function positionsTopDown(): array
    {
        $fim = $this->level * 5;

        return range($fim, $fim - 4);
    }

    /** @return list<array<string, mixed>> todas as células, sem agrupamento */
    public function cells(): array
    {
        return array_merge(...array_column($this->columns, 'cells'));
    }

    public function total(): int
    {
        return count($this->cells());
    }

    /** Marcados — inclusive os marcados como zero, que são pontuação. */
    public function markedCount(): int
    {
        return count(array_filter($this->cells(), fn (array $c) => $c['score'] !== null));
    }

    public function pendingCount(): int
    {
        return $this->total() - $this->markedCount();
    }

    public function scoreTotal(): float
    {
        return round(array_sum(array_map(
            fn (array $c) => $c['score'] ?? 0.0,
            $this->cells(),
        )), 1);
    }
}
