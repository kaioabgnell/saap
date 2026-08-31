<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Scoring;

/**
 * Conta acertos a partir das entradas registradas num marco.
 *
 * Existe separado do ScoreCalculator porque são perguntas diferentes: aqui
 * respondemos "quantos acertos houve", lá "quanto isso vale". O que muda de um
 * tipo de resposta para outro é só a contagem — a régua de pontuação é a mesma
 * para os 170 marcos.
 *
 * Cada entrada é um array com as chaves de response_entries:
 * position, text_value, is_checked, list_key, column_key.
 */
final class EntryTally
{
    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  MatrixStrategy  $matrixStrategy  Só usado quando $type é Matrix —
     *                                          ver a classe MatrixStrategy para por que o critério varia por marco.
     */
    public function count(
        ResponseType $type,
        array $entries,
        MatrixStrategy $matrixStrategy = MatrixStrategy::RowsComplete,
    ): int {
        return match ($type) {
            // O psicólogo escolhe entre dois critérios escritos. A escolha é
            // gravada como uma única entrada cuja posição é o ordinal:
            // 2 = atingiu o critério de 1 ponto, 1 = o de ½, nada = não atingiu.
            ResponseType::BinaryCriteria => $this->ordinalEscolhido($entries),

            // Uma caixa de texto preenchida é um exemplar observado.
            ResponseType::CounterFree => $this->preenchidas($entries),

            // Um check é um acerto; acréscimo fora da lista fixa também conta
            // (vai em text_value, sem list_key — ver ItemCard::entradasDaLista).
            ResponseType::CounterList => $this->marcadas($entries) + $this->preenchidas($entries),

            // Quando o acervo tem menos imagens que o marco exige, o cartão
            // completa com caixas de texto — então as duas formas contam.
            ResponseType::CounterStimuli => $this->marcadas($entries) + $this->preenchidas($entries),

            ResponseType::Matrix => match ($matrixStrategy) {
                MatrixStrategy::RowsComplete => $this->linhasCompletas($entries),
                MatrixStrategy::TotalCells => $this->marcadas($entries),
            },
        };
    }

    /** @param list<array<string, mixed>> $entries */
    private function ordinalEscolhido(array $entries): int
    {
        $ordinais = array_map(
            static fn (array $e) => (int) ($e['position'] ?? 0),
            array_filter($entries, static fn (array $e) => (bool) ($e['is_checked'] ?? false)),
        );

        return $ordinais === [] ? 0 : max($ordinais);
    }

    /** @param list<array<string, mixed>> $entries */
    private function preenchidas(array $entries): int
    {
        return count(array_filter(
            $entries,
            static fn (array $e) => trim((string) ($e['text_value'] ?? '')) !== '',
        ));
    }

    /** @param list<array<string, mixed>> $entries */
    private function marcadas(array $entries): int
    {
        return count(array_filter($entries, static fn (array $e) => (bool) ($e['is_checked'] ?? false)));
    }

    /** @param list<array<string, mixed>> $entries */
    private function linhasCompletas(array $entries): int
    {
        $porLinha = [];

        foreach ($entries as $entrada) {
            $linha = (string) ($entrada['list_key'] ?? '');
            if ($linha === '') {
                continue;
            }
            $porLinha[$linha][] = (bool) ($entrada['is_checked'] ?? false);
        }

        return count(array_filter(
            $porLinha,
            static fn (array $celulas) => $celulas !== [] && ! in_array(false, $celulas, true),
        ));
    }
}
