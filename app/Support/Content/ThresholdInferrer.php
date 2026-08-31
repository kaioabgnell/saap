<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Domain\Vbmapp\Scoring\ResponseType;
use App\Domain\Vbmapp\Scoring\ScoringMode;

/**
 * Deriva limiares, tipo de resposta e modo de pontuação a partir dos blocos
 * do manual.
 *
 * TUDO AQUI É SUGESTÃO. A saída vai para catalogo.json com um campo
 * `confidence` por marco, e é esse arquivo que a psicóloga revisa — nunca o
 * banco direto. O limiar decide se a criança pontua ½ ou 1: um erro aqui não
 * gera exceção, não quebra teste e não aparece na tela; contamina o laudo.
 */
final class ThresholdInferrer
{
    /**
     * Marcos com imagens no material de aplicação, pelos cabeçalhos de página
     * dos PDFs em docs/material-aplicacao/ ("TATO 1 e 2", "OUVINTE 3", ...).
     * Batem com os itens marcados "Tem no material de aplicação" nos registros.
     *
     * @var array<string, list<int>>
     */
    private const COM_MATERIAL = [
        'tato' => [1, 2, 3, 5, 6, 7, 11, 12, 13, 14],
        'ouvinte' => [3, 5, 7, 9, 11],
        'vpmts' => [5, 6, 7, 8, 9],
        'lrffc' => [6, 7, 8, 9],
    ];

    /**
     * Marcos cuja estrutura no registro é lista fixa ou grade. Identificados na
     * leitura dos PDFs de registro; ver .claude/specs/04-catalogo-vbmapp.md
     *
     * @var array<string, array<int, string>>
     */
    private const ESTRUTURA = [
        'ecoico' => [1 => 'counter_list', 2 => 'counter_list', 3 => 'counter_list',
            4 => 'counter_list', 5 => 'counter_list', 6 => 'counter_list',
            7 => 'counter_list', 8 => 'counter_list', 9 => 'counter_list', 10 => 'counter_list'],
        'tato' => [7 => 'matrix', 8 => 'counter_list', 9 => 'counter_list', 11 => 'matrix'],
    ];

    /** Expressões de tempo e percentual, removidas antes de buscar o limiar. */
    private const RUIDO_NUMERICO = [
        '/\b\d+\s*%/u',
        '/\b\d+\s*(min|minutos?|segundos?|seg|horas?|hora)\b/ui',
        '/\bpor\s+hora\b/ui',
        '/\(\s*OC\s*:[^)]*\)/ui',
        '/\bp\.?\s*\d+\b/ui',        // referência de página
        '/\bnível\s+\d+\b/ui',
        '/\bgrupo\s+\d+\b/ui',
    ];

    /**
     * @param  array<string, mixed>  $bloco
     * @return array<string, mixed>
     */
    public function infer(array $bloco): array
    {
        $area = (string) $bloco['area'];
        $posicao = (int) $bloco['position'];

        $tipo = $this->inferirTipo($bloco, $area, $posicao);
        $qualitativo = false;

        if ($tipo === ResponseType::BinaryCriteria) {
            // Aqui o limiar não é contagem de exemplares: o psicólogo escolhe
            // entre dois critérios escritos. Modelamos a escolha como ordinal —
            // 2 = atingiu o critério de 1 ponto, 1 = atingiu o de ½.
            // Assim o ScoreCalculator continua uniforme para todos os tipos.
            $cheio = 2;
            $meio = $this->temMeioPonto($bloco['criteria_half'] ?? null) ? 1 : null;
        } else {
            $meio = $this->extrairMeioPonto($bloco['criteria_half'] ?? null);
            $cheio = $this->extrairLimiar($bloco['criteria_full'] ?? null, $bloco['statement'] ?? null, $meio);

            // Marcos qualitativos — "Lê seu próprio nome", "Usa o vaso" — não
            // têm o que contar. Viram julgamento binário e vão para a revisão.
            // Meio ponto existe mas não tem número: critério qualitativo
            // ("copiar as letras de forma aproximada"). Não há o que contar.
            $meioQualitativo = $meio === null && $this->temMeioPonto($bloco['criteria_half'] ?? null);

            // meio > cheio denuncia critério de sentido invertido, em que o
            // número conta ajudas e não acertos: "1 dica verbal" vale 1 ponto,
            // "2 ou mais dicas" vale ½. Contar não serve; o psicólogo escolhe.
            // Limiar cheio igual a 1 não é contagem: uma única caixa é sim ou não.
            // Costuma ser resíduo de critério temporal — "explora objetos por 1
            // minuto" — em que o número sobrevivente é a duração, não a meta.
            $semContagem = $cheio === 1;

            if ($cheio === null || $meioQualitativo || $semContagem || ($meio !== null && $meio > $cheio)) {
                $tipo = ResponseType::BinaryCriteria;
                $cheio = 2;
                $meio = $this->temMeioPonto($bloco['criteria_half'] ?? null) ? 1 : null;
                $qualitativo = true;
            }
        }

        $confianca = $qualitativo ? 'low' : $this->avaliarConfianca($bloco, $tipo, $cheio, $meio);

        // Limiares iguais são a assinatura do critério qualitativo: mesma
        // contagem nos dois níveis, o que muda é a qualidade. Ver Mando 4-M.
        $modo = ($tipo !== ResponseType::BinaryCriteria
            && $cheio !== null && $meio !== null && $cheio === $meio)
            ? ScoringMode::Assisted
            : ScoringMode::Auto;

        return [
            'response_type' => $tipo->value,
            'threshold_full' => $cheio,
            'threshold_half' => $meio,
            'scoring_mode' => $modo->value,
            'confidence' => $confianca,
            // Quando o manual declara que não há meio ponto, o texto da
            // declaração não é critério — é ausência de critério. Some junto
            // com o limiar, para que os dois nunca divirjam.
            'criteria_half' => $this->temMeioPonto($bloco['criteria_half'] ?? null)
                ? $bloco['criteria_half']
                : null,
        ];
    }

    private function inferirTipo(array $bloco, string $area, int $posicao): ResponseType
    {
        if (isset(self::ESTRUTURA[$area][$posicao])) {
            return ResponseType::from(self::ESTRUTURA[$area][$posicao]);
        }

        if (in_array($posicao, self::COM_MATERIAL[$area] ?? [], true)) {
            return ResponseType::CounterStimuli;
        }

        // Sem estrutura conhecida, a decisão fica com a evidência: se há o que
        // contar, é contagem. Marcos cujo limiar é puramente temporal — "por 1
        // minuto", "30 segundos" — não sobrevivem à remoção do ruído numérico e
        // caem no fallback binário em infer(). Mencionar minutos não basta para
        // deixar de ser contagem: "imita 8 movimentos em 30 min" conta 8.
        return ResponseType::CounterFree;
    }

    /**
     * O limiar de 1 ponto é o número que aparece no enunciado E no critério —
     * validado em 157 dos 170 marcos. Sem coincidência, cai no primeiro número
     * do critério depois de removido o ruído.
     */
    private function extrairLimiar(?string $criterio, ?string $enunciado, ?int $meio): ?int
    {
        if ($criterio === null) {
            return null;
        }

        $doCriterio = $this->numeros($this->semRuido($this->semPrefixo($criterio)));

        if ($doCriterio === []) {
            return null;
        }

        // O limiar cheio nunca é menor que o de meio ponto. Usar isso como
        // restrição descarta números acessórios — "arranjo de 3 itens" —
        // que a coincidência com o enunciado sozinha não filtra. Caso real:
        // imitacao:6, cujo enunciado traz "Imita10" sem espaço no PDF, o que
        // esconde o 10 do casamento por palavra.
        $viaveis = $meio === null
            ? $doCriterio
            : array_values(array_filter($doCriterio, fn (int $n) => $n >= $meio));

        if ($viaveis === []) {
            $viaveis = $doCriterio;
        }

        if ($enunciado !== null) {
            $comuns = array_values(array_intersect($viaveis, $this->numeros($this->semRuido($enunciado))));
            if ($comuns !== []) {
                return max($comuns);
            }
        }

        return $viaveis[0];
    }

    /**
     * O marco admite meio ponto? Nem todos admitem, e o manual diz isso de
     * formas diferentes: "Não há ½ ponto para esta habilidade" (Ouvinte 2-M)
     * e um lacônico "Não tem" (Imitação 10-M).
     */
    private function temMeioPonto(?string $criterio): bool
    {
        if ($criterio === null) {
            return false;
        }

        $nu = trim($criterio);

        return ! preg_match('/^n[ãa]o\s+(h[áa]|tem|se\s+aplica)/ui', $nu)
            && ! preg_match('/n[ãa]o\s+h[áa]\s+(½|1\/2|meio)\s*ponto/ui', $nu);
    }

    private function extrairMeioPonto(?string $criterio): ?int
    {
        if (! $this->temMeioPonto($criterio)) {
            return null;
        }

        $numeros = $this->numeros($this->semRuido($this->semPrefixo($criterio)));

        return $numeros[0] ?? null;
    }

    /**
     * Remove as menções ao próprio valor do ponto.
     *
     * Um regex de prefixo é frágil — a redação varia ("Dê 1 ponto à criança
     * se", "Dê à criança ½ ponto se", "Dê 1/2 ponto se criança que"). Apagar
     * toda ocorrência de "<valor> ponto" é mais robusto e não pode comer
     * limiar, porque limiar nunca vem seguido da palavra "ponto".
     */
    private function semPrefixo(string $texto): string
    {
        $limpo = (string) preg_replace(
            '/\b(1\/2|½|1|um|uma|meio)\s*pontos?\b/ui',
            ' ',
            trim($texto),
        );

        return (string) preg_replace('/^\s*D[êe]\s*(à\s+criança)?\s*(se\s+(ela|a\s+criança|o\s+estudante)?)?/ui', '', $limpo, 1);
    }

    private function semRuido(string $texto): string
    {
        return (string) preg_replace(self::RUIDO_NUMERICO, ' ', $texto);
    }

    /** O manual alterna dígito e extenso: "duas atividades", "2 itens". */
    private const POR_EXTENSO = [
        'uma' => 1, 'um' => 1, 'duas' => 2, 'dois' => 2, 'três' => 3, 'tres' => 3,
        'quatro' => 4, 'cinco' => 5, 'seis' => 6, 'sete' => 7, 'oito' => 8,
        'nove' => 9, 'dez' => 10, 'quinze' => 15, 'vinte' => 20, 'vinte e cinco' => 25,
    ];

    /** @return list<int> */
    private function numeros(string $texto): array
    {
        preg_match_all('/\b(\d{1,4})\b/u', $texto, $m);
        $numeros = array_map('intval', $m[1]);

        $minusculo = mb_strtolower($texto);
        foreach (self::POR_EXTENSO as $palavra => $valor) {
            if (preg_match('/\b'.preg_quote($palavra, '/').'\b/u', $minusculo)) {
                $numeros[] = $valor;
            }
        }

        return array_values($numeros);
    }

    /**
     * Confiança honesta: `high` só quando enunciado e critério concordam no
     * número e o tipo veio de fonte conhecida. O resto pede olho humano.
     */
    private function avaliarConfianca(array $bloco, ResponseType $tipo, ?int $cheio, ?int $meio): string
    {
        if ($cheio === null) {
            return 'missing';
        }

        // Em binary_criteria o limiar é ordinal e não aparece no enunciado.
        // O que importa é ter os dois critérios escritos, que é o que a tela
        // apresenta ao psicólogo.
        if ($tipo === ResponseType::BinaryCriteria) {
            return ($bloco['criteria_full'] ?? null) !== null ? 'medium' : 'low';
        }

        $area = (string) $bloco['area'];
        $posicao = (int) $bloco['position'];
        // Só fonte documental conta como "conhecido". A heurística de tempo é
        // palpite e não deve inflar a confiança.
        $tipoConhecido = isset(self::ESTRUTURA[$area][$posicao])
            || in_array($posicao, self::COM_MATERIAL[$area] ?? [], true);

        $numerosBatem = $bloco['statement'] !== null
            && in_array($cheio, $this->numeros($this->semRuido($bloco['statement'])), true);

        if ($numerosBatem && $tipoConhecido) {
            return 'high';
        }
        if ($numerosBatem || $tipoConhecido) {
            return 'medium';
        }

        return 'low';
    }
}
