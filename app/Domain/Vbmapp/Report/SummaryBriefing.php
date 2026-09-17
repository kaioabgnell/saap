<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Report;

/**
 * O que sai do sistema quando se pede um resumo por IA — e o que NÃO sai.
 *
 * Esta classe existe para que essa decisão tenha um lugar só, legível e com
 * teste, em vez de ficar diluída num monte de concatenação dentro do cliente
 * HTTP. Tudo que for enviado a terceiro passa por aqui.
 *
 * **O nome do aprendiz não é enviado.** Vai o marcador `{{APRENDIZ}}`, e a
 * substituição pelo nome real acontece depois que o texto volta, já dentro do
 * SAAP. O modelo não precisa do nome para redigir — precisa das pontuações —,
 * então mandá-lo seria entregar a identificação de uma criança a um terceiro
 * em troca de nada. O resultado que a psicóloga lê é idêntico.
 *
 * O que sai, então, é: nível, pontuação total e pontuação por área. Dado
 * clínico de uma criança não identificada. Continua sendo dado sensível e
 * está declarado na tabela da LGPD em docs/operacao.md.
 *
 * Dois recortes, a mesma classe: um nível avulso (`deNivel`) e a avaliação
 * fechada do laudo (`deAvaliacao`). O que muda é o enquadramento do texto —
 * o que sai daqui é do mesmo tipo nos dois casos.
 */
final class SummaryBriefing
{
    /** O modelo escreve isto; o SAAP troca pelo nome real na volta. */
    public const MARCADOR_APRENDIZ = '{{APRENDIZ}}';

    /** @param  list<array{nivel: int, pontuacao: float, total: int, areas: list<array{nome: string, pontuacao: float, total: int}>}>  $niveis */
    private function __construct(
        private readonly array $niveis,
        private readonly bool $laudo,
    ) {}

    /** @param  list<array{nome: string, pontuacao: float, total: int}>  $areas */
    public static function deNivel(int $level, float $pontuacao, int $totalDeMarcos, array $areas): self
    {
        return new self(
            [['nivel' => $level, 'pontuacao' => $pontuacao, 'total' => $totalDeMarcos, 'areas' => $areas]],
            laudo: false,
        );
    }

    /** @param  list<array{nivel: int, pontuacao: float, total: int, areas: list<array{nome: string, pontuacao: float, total: int}>}>  $niveis */
    public static function deAvaliacao(array $niveis): self
    {
        return new self($niveis, laudo: true);
    }

    /**
     * O papel e as travas. Cada restrição aqui responde a um risco concreto
     * de um texto que vai para a família e para o prontuário.
     */
    public function instrucaoDoSistema(): string
    {
        return implode(' ', array_filter([
            'Você é psicólogo especialista no VB-MAPP (Verbal Behavior Milestones Assessment and Placement Program).',
            $this->laudo
                ? 'Redija o resumo descritivo de uma avaliação concluída, para compor um laudo, em português do Brasil,'
                : 'Redija o resumo de uma avaliação em português do Brasil,',
            'no padrão de registro de prontuário: impessoal, descritivo, em terceira pessoa, sem juízo de valor.',
            // As três proibições abaixo são o núcleo. Um resumo automático que
            // sugere diagnóstico, prognóstico ou conduta seria ato privativo
            // do psicólogo saindo assinado por uma máquina.
            'NÃO faça diagnóstico, NÃO faça prognóstico e NÃO recomende tratamento, conduta ou intervenção.',
            'Use exclusivamente os dados fornecidos: não invente marcos, números, comportamentos nem observações de sessão.',
            'Não cite idade cronológica nem faixa etária esperada.',
            'Estruture em seções curtas, nesta ordem, com estes títulos exatos:',
            $this->laudo
                ? '"Dados da avaliação", "Desempenho por nível", "Áreas de maior pontuação", "Áreas de menor pontuação".'
                : '"Dados da avaliação", "Desempenho por área", "Áreas de maior pontuação", "Áreas de menor pontuação".',
            $this->laudo ? 'Máximo de 400 palavras.' : 'Máximo de 300 palavras.',
            'Refira-se ao avaliado sempre como '.self::MARCADOR_APRENDIZ.', escrito exatamente assim, sem aspas.',
            'Não use markdown, asteriscos nem listas com marcador: apenas parágrafos e os títulos das seções.',
        ]));
    }

    /** Os dados. Curtos de propósito — é o que o modelo cobra por token. */
    public function dadosDaAvaliacao(): string
    {
        $linhas = [];

        if ($this->laudo) {
            $linhas[] = sprintf(
                'Avaliação VB-MAPP concluída, com %d nível(is) aplicado(s). Pontuação total: %s de %d marcos.',
                count($this->niveis),
                $this->numero($this->pontuacaoTotal()),
                $this->totalDeMarcos(),
            );
        }

        foreach ($this->niveis as $nivel) {
            $linhas[] = sprintf(
                'Nível %d: %s de %d marcos.',
                $nivel['nivel'],
                $this->numero($nivel['pontuacao']),
                $nivel['total'],
            );
            $linhas[] = 'Pontuação por área (máximo 5 em cada):';

            foreach ($nivel['areas'] as $area) {
                $linhas[] = sprintf('- %s: %s de %d', $area['nome'], $this->numero($area['pontuacao']), $area['total']);
            }
        }

        return implode("\n", $linhas);
    }

    private function pontuacaoTotal(): float
    {
        return round(array_sum(array_column($this->niveis, 'pontuacao')), 1);
    }

    private function totalDeMarcos(): int
    {
        return (int) array_sum(array_column($this->niveis, 'total'));
    }

    /** "4,5" e não "4.5": o texto é lido por quem escreve em português. */
    private function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 1, ',', ''), '0'), ',');
    }
}
