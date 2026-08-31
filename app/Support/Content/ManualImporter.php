<?php

declare(strict_types=1);

namespace App\Support\Content;

/**
 * Segmenta o manual VB-MAPP traduzido em blocos, um por marco.
 *
 * O manual usa DOIS formatos de bloco, descobertos na sondagem:
 *
 * Formato A (155 marcos previstos) — campos em maiúscula, em linha própria:
 *     MANDO
 *     3-M
 *     Generaliza 6 mandos entre 2 pessoas...
 *     OBJETIVO
 *     ...
 *     1 PONTO
 *     Dê 1 ponto...
 *     ½ PONTO
 *     Dê 1/2 ponto...
 *
 * Formato B (Ecóico e Vocal, 15 marcos) — código com espaço, campos inline:
 *     ECÓICO
 *     1 M
 *     Pontuação de no mínimo 2 no subteste APCE
 *     Objetivo: ...
 *     1 ponto: ...
 *     ½ ponto: ...
 *
 * O que não for reconhecido entra no relatório de lacunas para transcrição
 * manual — a revisão clínica é parte prevista da fase F1.
 */
final class ManualImporter
{
    /** Rótulo de área no manual => code em vbmapp_areas. */
    private const AREAS = [
        'MANDO' => 'mando',
        'TATO' => 'tato',
        'OUVINTE' => 'ouvinte',
        'VP-MTS' => 'vpmts',
        'PV-MTS' => 'vpmts',   // variante de digitação, ocorre 4 vezes
        'VP/MTS' => 'vpmts',
        'BRINCAR' => 'brincar',
        'SOCIAL' => 'social',
        'IMITAÇÃO' => 'imitacao',
        'IMITACAO' => 'imitacao',
        'ECÓICO' => 'ecoico',
        'ECOICO' => 'ecoico',
        'VOCAL' => 'vocal',
        'LRFFC' => 'lrffc',
        'INTRAVERBAL' => 'intraverbal',
        'GRUPO' => 'grupo',
        'LINGUÍSTICA' => 'linguistica',
        'LINGUISTICA' => 'linguistica',
        'LEITURA' => 'leitura',
        'ESCRITA' => 'escrita',
        'MATEMÁTICA' => 'matematica',
        'MATEMATICA' => 'matematica',
    ];

    /** @var list<string> */
    private array $linhas;

    public function __construct(string $texto)
    {
        $this->linhas = preg_split('/\R/u', $texto) ?: [];
    }

    public static function fromFile(string $caminho): self
    {
        return new self((string) file_get_contents($caminho));
    }

    /**
     * @return array{blocos: array<string, array<string, mixed>>, ignorados: list<array{linha:int, texto:string}>}
     */
    public function parse(): array
    {
        $blocos = [];
        $ignorados = [];
        $total = count($this->linhas);

        for ($i = 0; $i < $total; $i++) {
            $codigo = $this->matchCodigo($this->linhas[$i]);
            if ($codigo === null) {
                continue;
            }

            [$posicao, $formato] = $codigo;
            $area = $this->areaAcimaDe($i);

            if ($area === null) {
                $ignorados[] = ['linha' => $i + 1, 'texto' => trim($this->linhas[$i])];

                continue;
            }

            $fim = $this->fimDoBloco($i + 1, $formato);
            $corpo = array_slice($this->linhas, $i + 1, $fim - $i - 1);

            $campos = $formato === 'A'
                ? $this->camposFormatoA($corpo)
                : $this->camposFormatoB($corpo);

            $chave = "{$area}:{$posicao}";

            // Um marco pode reaparecer (índice, sumário). Fica o bloco mais completo.
            if (isset($blocos[$chave]) && $this->completude($blocos[$chave]) >= $this->completude($campos)) {
                continue;
            }

            $blocos[$chave] = [
                'area' => $area,
                'position' => $posicao,
                'level' => intdiv($posicao - 1, 5) + 1,
                'code' => "{$posicao}-M",
                'source_line' => $i + 1,
                'format' => $formato,
                ...$campos,
            ];
        }

        ksort($blocos);

        return ['blocos' => $blocos, 'ignorados' => $ignorados];
    }

    /** @return array{int, string}|null [posição, formato] */
    private function matchCodigo(string $linha): ?array
    {
        $nu = trim($linha);

        // O separador varia no PDF: "7-M", "7- M" e "9 –M" (travessão) são
        // o mesmo código. Presença de traço indica formato A.
        if (preg_match('/^([0-9]{1,2})\s*[-–—]\s*M$/u', $nu, $m)) {
            return [(int) $m[1], 'A'];
        }
        // Formato B não tem traço: "1 M" ou "5M". Usado por Ecóico e Vocal.
        if (preg_match('/^([0-9]{1,2})\s*M$/u', $nu, $m)) {
            return [(int) $m[1], 'B'];
        }

        return null;
    }

    /**
     * Procura o rótulo de área nas linhas acima do código, pulando linhas em
     * branco e marcadores de página — que interrompem o par área/código em
     * dois pontos do manual.
     */
    private function areaAcimaDe(int $indice): ?string
    {
        for ($j = $indice - 1, $saltos = 0; $j >= 0 && $saltos < 4; $j--) {
            $nu = trim($this->linhas[$j]);

            if ($nu === '' || str_starts_with($nu, '===== PAGE')) {
                continue;
            }

            $saltos++;

            $rotulo = $this->rotuloDeArea($nu);

            if ($rotulo !== null) {
                return $rotulo;
            }
        }

        return null;
    }

    /**
     * Reconhece o rótulo de área numa linha.
     *
     * A linha inteira é testada primeiro: "VP-MTS" contém hífen e seria
     * mutilada por um split ingênuo. Só depois tentamos o sufixo, para casos
     * como "ECÓICO (SUBTESTE APCE) – NÍVEL 1".
     */
    private function rotuloDeArea(string $linha): ?string
    {
        $nu = trim($linha);

        if (isset(self::AREAS[$nu])) {
            return self::AREAS[$nu];
        }

        // separadores de sufixo: parêntese, travessão e meia-risca — nunca o hífen
        $base = trim(preg_split('/[(–—]/u', $nu)[0] ?? '');

        return self::AREAS[$base] ?? null;
    }

    /** O bloco termina onde começa o próximo código de marco. */
    private function fimDoBloco(int $inicio, string $formato): int
    {
        $total = count($this->linhas);

        for ($i = $inicio; $i < $total; $i++) {
            // Cabeçalho de seção encerra o bloco: "RESPONDER DE OUVINTE – NÍVEL 1"
            if (preg_match('/[–—-]\s*N[ÍI]VEL\s+\d/ui', trim($this->linhas[$i]))) {
                return $i;
            }

            if ($this->matchCodigo($this->linhas[$i]) !== null) {
                // recua até antes do rótulo de área que precede o próximo código
                $recuo = $i;
                for ($j = $i - 1; $j >= $inicio && $j >= $i - 3; $j--) {
                    $nu = trim($this->linhas[$j]);
                    if ($nu === '' || str_starts_with($nu, '===== PAGE')) {
                        continue;
                    }
                    if ($this->rotuloDeArea($nu) !== null) {
                        $recuo = $j;
                    }
                    break;
                }

                return $recuo;
            }
        }

        return min($inicio + 60, $total);
    }

    /** @param list<string> $corpo */
    private function camposFormatoA(array $corpo): array
    {
        $marcadores = [
            'objective' => 'OBJETIVO',
            'materials' => 'MATERIAIS',
            'examples' => 'EXEMPLOS',
            'criteria_full' => '1 PONTO',
            'criteria_half' => '½ PONTO',
        ];

        // O marcador costuma ocupar a linha inteira, mas às vezes o conteúdo vem
        // colado: "MATERIAIS Materiais básicos de sala de aula." Guardamos o
        // resto da linha para não perdê-lo.
        $posicoes = [];
        $resto = [];
        foreach ($corpo as $n => $linha) {
            $nu = trim($linha);
            foreach ($marcadores as $campo => $marcador) {
                if (isset($posicoes[$campo])) {
                    continue;
                }
                // O marcador pode trazer dois-pontos ("1 PONTO:") e conteúdo colado.
                if (! preg_match('/^'.preg_quote($marcador, '/').'\s*:?\s*(.*)$/u', $nu, $m)) {
                    continue;
                }
                $posicoes[$campo] = $n;
                $resto[$campo] = trim($m[1]);
            }
        }

        $primeiro = $posicoes === [] ? count($corpo) : min($posicoes);
        $campos = ['statement' => $this->juntar(array_slice($corpo, 0, $primeiro))];

        $ordenadas = $posicoes;
        asort($ordenadas);
        $chaves = array_keys($ordenadas);

        foreach ($chaves as $k => $campo) {
            $de = $ordenadas[$campo] + 1;
            $ate = isset($chaves[$k + 1]) ? $ordenadas[$chaves[$k + 1]] : count($corpo);
            $seguintes = $this->juntar(array_slice($corpo, $de, $ate - $de));
            $campos[$campo] = trim($resto[$campo].' '.$seguintes);
        }

        return $this->normalizar($campos);
    }

    /** @param list<string> $corpo */
    private function camposFormatoB(array $corpo): array
    {
        $marcadores = [
            'objective' => '/^Objetivo\s*:/ui',
            'materials' => '/^Materiais\s*:/ui',
            'examples' => '/^Exemplos\s*:/ui',
            'criteria_full' => '/^1\s*ponto\s*:/ui',
            'criteria_half' => '/^½\s*ponto\s*:/ui',
        ];

        $posicoes = [];
        foreach ($corpo as $n => $linha) {
            foreach ($marcadores as $campo => $padrao) {
                if (! isset($posicoes[$campo]) && preg_match($padrao, trim($linha))) {
                    $posicoes[$campo] = $n;
                }
            }
        }

        $primeiro = $posicoes === [] ? count($corpo) : min($posicoes);
        $campos = ['statement' => $this->juntar(array_slice($corpo, 0, $primeiro))];

        $ordenadas = $posicoes;
        asort($ordenadas);
        $chaves = array_keys($ordenadas);

        foreach ($chaves as $k => $campo) {
            $de = $ordenadas[$campo];
            $ate = isset($chaves[$k + 1]) ? $ordenadas[$chaves[$k + 1]] : count($corpo);
            $trecho = $this->juntar(array_slice($corpo, $de, $ate - $de));
            // remove o rótulo inline ("Objetivo:", "1 ponto:")
            $campos[$campo] = trim(preg_replace($marcadores[$campo], '', $trecho, 1) ?? $trecho);
        }

        return $this->normalizar($campos);
    }

    /** @param list<string> $linhas */
    private function juntar(array $linhas): string
    {
        $texto = trim(implode(' ', array_map('trim', $linhas)));

        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    /**
     * Corta o que veio depois do fim real do campo. O último campo do bloco
     * costuma arrastar marcador de página e cabeçalho da seção seguinte —
     * "... 8 mandos diferentes. TATO NÍVEL 1".
     *
     * Os padrões são explícitos de propósito: um regex genérico de maiúsculas
     * comeria conteúdo legítimo, como "APCE" e "LRFFC" no meio da frase.
     */
    private function podarCauda(string $texto): string
    {
        $cortes = [
            '/=====\s*PAGE\b.*$/us',
            '/\bCAPÍTULO\s+\d.*$/us',
            '/\bAVALIAÇÃO\s+[A-ZÀ-Ú]{2,}.*$/us',
            '/\b(RESPONDER DE OUVINTE|COMPORTAMENTO VOCAL|ESTRUTURA LINGUÍSTICA|IMITAÇÃO MOTORA|BRINCAR INDEPENDENTE|COMPORTAMENTO SOCIAL|COMPORTAMENTO EM GRUPO|PERCEPÇÃO VISUAL)\b.*$/us',
            // rótulo de área em maiúsculas seguido de NÍVEL: "TATO NÍVEL 2"
            '/\b[A-ZÀ-Ú][A-ZÀ-Ú\/\- ]{2,}\s+N[ÍI]VEL\s+\d.*$/us',
        ];

        return trim((string) preg_replace($cortes, '', $texto));
    }

    private function normalizar(array $campos): array
    {
        foreach (['statement', 'objective', 'materials', 'examples', 'criteria_full', 'criteria_half'] as $campo) {
            if (($campos[$campo] ?? null) !== null) {
                $campos[$campo] = $this->podarCauda($campos[$campo]);
            }
            $campos[$campo] = ($campos[$campo] ?? '') === '' ? null : $campos[$campo];
        }

        // "(OC: 60 min)" no enunciado indica tempo de observação exigido.
        $campos['observation_minutes'] = null;
        if ($campos['statement'] !== null
            && preg_match('/\(OC:\s*([0-9]+)\s*min\)/ui', $campos['statement'], $m)) {
            $campos['observation_minutes'] = (int) $m[1];
        }

        return $campos;
    }

    private function completude(array $campos): int
    {
        $peso = 0;
        foreach (['statement' => 3, 'criteria_full' => 3, 'criteria_half' => 2, 'objective' => 1, 'materials' => 1, 'examples' => 1] as $campo => $p) {
            if (($campos[$campo] ?? null) !== null) {
                $peso += $p;
            }
        }

        return $peso;
    }
}
