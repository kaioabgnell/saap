<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Scoring;

/**
 * Como contar acertos numa grade item × exemplares.
 *
 * O critério de pontuação da matrix varia por marco — não é uma regra única.
 *
 * Tato 7-M (nível 2): "1 ponto se 3 exemplares de cada um de 50 itens" — o
 * ITEM (a linha) só conta quando TODOS os exemplares dele estão marcados.
 * RowsComplete.
 *
 * Tato 11-M (nível 3): "1 ponto se nomear cor, forma e função de 5 objetos
 * (15 testagens); ½ ponto para 10 testagens" — aqui o critério é sobre o
 * TOTAL de células certas, não sobre linhas completas: 10 de 15 pode vir de
 * qualquer combinação de objetos e características. TotalCells.
 */
enum MatrixStrategy: string
{
    case RowsComplete = 'rows_complete';
    case TotalCells = 'total_cells';

    public static function fromColumns(?array $matrixColumns): self
    {
        $valor = $matrixColumns['strategy'] ?? null;

        return self::tryFrom((string) $valor) ?? self::RowsComplete;
    }
}
