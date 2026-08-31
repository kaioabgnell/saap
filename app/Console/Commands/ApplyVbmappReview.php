<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Vbmapp\Scoring\ResponseType;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;

/**
 * Aplica ao catálogo as correções da revisão clínica.
 *
 * Fecha o ciclo que `vbmapp:review-sheet` abre: a planilha sai, o psicólogo
 * confere marco a marco, e este comando traz as correções de volta. Sem ele a
 * revisão não teria onde aterrissar — alguém teria de editar 170 entradas de
 * JSON à mão, que é exatamente o tipo de trabalho onde o erro entra sem ser
 * visto.
 *
 * Regra de ouro: **nada aqui adivinha**. Célula vazia significa "não mexer".
 * Só a coluna preenchida altera o catálogo, e toda alteração é listada na
 * saída antes de ser gravada.
 */
class ApplyVbmappReview extends Command
{
    protected $signature = 'vbmapp:apply-review
                            {--planilha=storage/app/vbmapp/revisao-clinica.csv : CSV preenchido}
                            {--dry-run : Mostra o que mudaria, sem gravar}';

    protected $description = 'Aplica ao catálogo as correções da planilha de revisão clínica';

    public function handle(): int
    {
        // Caminho absoluto passa direto; relativo é a partir da raiz do projeto.
        // Sem isto, apontar para /tmp viraria <projeto>/tmp e o erro seria
        // "não encontrado" num caminho que o usuário nunca digitou.
        $informado = (string) $this->option('planilha');
        $planilha = str_starts_with($informado, '/') ? $informado : base_path($informado);
        $origem = storage_path('app/vbmapp/catalogo.json');

        foreach ([$planilha => 'Planilha', $origem => 'Catálogo'] as $arquivo => $rotulo) {
            if (! is_file($arquivo)) {
                $this->error("{$rotulo} não encontrado: {$arquivo}");

                return self::FAILURE;
            }
        }

        try {
            $dados = json_decode((string) file_get_contents($origem), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('Catálogo ilegível: '.$e->getMessage());

            return self::FAILURE;
        }

        $linhas = $this->lerPlanilha($planilha);

        if ($linhas === []) {
            $this->error('Planilha vazia ou sem cabeçalho reconhecível.');

            return self::FAILURE;
        }

        $mudancas = [];
        $conferidos = 0;
        $erros = [];

        foreach ($linhas as $n => $linha) {
            $chave = trim((string) ($linha['area_code'] ?? '')).':'.trim((string) ($linha['marco'] ?? ''));

            if (! isset($dados['marcos'][$chave])) {
                $erros[] = "linha {$n}: marco desconhecido '{$chave}'";

                continue;
            }

            if ($this->sim($linha['CONFERIDO_SN'] ?? '')) {
                $conferidos++;
            }

            try {
                $mudancas = [...$mudancas, ...$this->correcoesDaLinha($chave, $linha, $dados['marcos'][$chave])];
            } catch (RuntimeException $e) {
                $erros[] = "linha {$n} ({$chave}): ".$e->getMessage();
            }
        }

        if ($erros !== []) {
            $this->error('A planilha tem problemas — nada foi gravado:');
            foreach ($erros as $erro) {
                $this->line("  {$erro}");
            }

            return self::FAILURE;
        }

        $this->line("Marcos marcados como conferidos: {$conferidos} de ".count($dados['marcos']));

        if ($mudancas === []) {
            $this->info('Nenhuma correção a aplicar — a planilha não pede mudança de tipo nem de limiar.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line(count($mudancas).' correção(ões):');

        foreach ($mudancas as $m) {
            $this->line(sprintf('  %-14s %-16s %s → %s',
                $m['marco'], $m['campo'],
                var_export($m['de'], true), var_export($m['para'], true)));

            $dados['marcos'][$m['marco']][$m['campo']] = $m['para'];
            $dados['marcos'][$m['marco']]['revisado_clinicamente'] = true;
        }

        // O limiar revisado por humano não pode ser sobrescrito por uma nova
        // rodada do parser. É o mesmo mecanismo do _pos_inferencia da F5.
        foreach ($mudancas as $m) {
            $dados['marcos'][$m['marco']]['_pos_inferencia'] = true;
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('dry-run: nada foi gravado.');

            return self::SUCCESS;
        }

        file_put_contents($origem, json_encode(
            $dados,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        $this->newLine();
        $this->info('Catálogo atualizado em storage/app/vbmapp/catalogo.json');
        $this->line('Próximo passo: php artisan vbmapp:freeze --revisor="Nome de quem revisou"');
        $this->line('               php artisan migrate:fresh --seed');

        return self::SUCCESS;
    }

    /**
     * Correções de UMA linha. Célula vazia é "não mexer" — nunca "apagar".
     *
     * @return list<array{marco: string, campo: string, de: mixed, para: mixed}>
     */
    private function correcoesDaLinha(string $chave, array $linha, array $marco): array
    {
        $mudancas = [];

        $tipo = trim((string) ($linha['TIPO_CORRIGIDO'] ?? ''));
        if ($tipo !== '') {
            if (ResponseType::tryFrom($tipo) === null) {
                throw new RuntimeException("tipo '{$tipo}' não existe");
            }
            if ($tipo !== $marco['response_type']) {
                $mudancas[] = ['marco' => $chave, 'campo' => 'response_type', 'de' => $marco['response_type'], 'para' => $tipo];
            }
        }

        $modo = trim((string) ($linha['MODO_CORRIGIDO'] ?? ''));
        if ($modo !== '') {
            if (! in_array($modo, ['auto', 'assisted'], true)) {
                throw new RuntimeException("modo '{$modo}' não existe (use auto ou assisted)");
            }
            if ($modo !== $marco['scoring_mode']) {
                $mudancas[] = ['marco' => $chave, 'campo' => 'scoring_mode', 'de' => $marco['scoring_mode'], 'para' => $modo];
            }
        }

        foreach ([['MEIO_CORRIGIDO', 'threshold_half'], ['CHEIO_CORRIGIDO', 'threshold_full']] as [$coluna, $campo]) {
            $bruto = trim((string) ($linha[$coluna] ?? ''));

            if ($bruto === '') {
                continue;
            }

            // "nulo" é a forma de dizer "este marco não tem meio ponto" — o
            // caso do Ouvinte 2. Sem uma palavra para isso, seria indistinguível
            // de célula não preenchida.
            $valor = $this->ehNulo($bruto) ? null : $this->inteiro($bruto, $coluna);

            if ($valor === null && $campo === 'threshold_full') {
                throw new RuntimeException('threshold_full não pode ser nulo — todo marco tem critério de 1 ponto');
            }

            if ($valor !== $marco[$campo]) {
                $mudancas[] = ['marco' => $chave, 'campo' => $campo, 'de' => $marco[$campo], 'para' => $valor];
            }
        }

        $this->validarPar($chave, $marco, $mudancas);

        return [...$mudancas, ...$this->imporModoAssistido($chave, $marco, $mudancas)];
    }

    /**
     * Limiares iguais significam critério qualitativo: a contagem não separa ½
     * de 1 ponto, só a qualidade separa — e aí o sistema tem de pedir
     * confirmação ao psicólogo em vez de decidir sozinho. É invariante do
     * domínio, não preferência, então o comando a impõe em vez de deixar o
     * revisor criar um estado que o seeder recusaria.
     *
     * @return list<array{marco: string, campo: string, de: mixed, para: mixed}>
     */
    private function imporModoAssistido(string $chave, array $marco, array $mudancas): array
    {
        $meio = $marco['threshold_half'];
        $cheio = $marco['threshold_full'];
        $modo = $marco['scoring_mode'];

        foreach ($mudancas as $m) {
            $meio = $m['campo'] === 'threshold_half' ? $m['para'] : $meio;
            $cheio = $m['campo'] === 'threshold_full' ? $m['para'] : $cheio;
            $modo = $m['campo'] === 'scoring_mode' ? $m['para'] : $modo;
        }

        if ($meio !== null && $meio === $cheio && $modo !== 'assisted') {
            return [['marco' => $chave, 'campo' => 'scoring_mode', 'de' => $modo, 'para' => 'assisted']];
        }

        return [];
    }

    /** Meio maior que cheio nunca é legítimo — a mesma guarda do seeder. */
    private function validarPar(string $chave, array $marco, array $mudancas): void
    {
        $meio = $marco['threshold_half'];
        $cheio = $marco['threshold_full'];

        foreach ($mudancas as $m) {
            if ($m['campo'] === 'threshold_half') {
                $meio = $m['para'];
            }
            if ($m['campo'] === 'threshold_full') {
                $cheio = $m['para'];
            }
        }

        if ($meio !== null && $cheio !== null && $meio > $cheio) {
            throw new RuntimeException("meio ({$meio}) maior que cheio ({$cheio})");
        }
    }

    private function inteiro(string $bruto, string $coluna): int
    {
        if (! preg_match('/^\d+$/', $bruto)) {
            throw new RuntimeException("{$coluna}: '{$bruto}' não é um número inteiro nem 'nulo'");
        }

        return (int) $bruto;
    }

    private function ehNulo(string $v): bool
    {
        return in_array(mb_strtolower($v), ['nulo', 'null', 'nenhum', '-', '—'], true);
    }

    private function sim(string $v): bool
    {
        return in_array(mb_strtoupper(trim($v)), ['S', 'SIM', 'X', 'Y'], true);
    }

    /** @return array<int, array<string, string>> */
    private function lerPlanilha(string $caminho): array
    {
        $handle = fopen($caminho, 'r');
        $cabecalho = fgetcsv($handle);

        if ($cabecalho === false) {
            return [];
        }

        // O BOM que o review-sheet escreve para o Excel gruda na primeira coluna.
        $cabecalho[0] = ltrim((string) $cabecalho[0], "\u{FEFF}");

        $linhas = [];
        $n = 1;

        while (($valores = fgetcsv($handle)) !== false) {
            $n++;

            if ($valores === [null] || $valores === []) {
                continue;
            }

            $linhas[$n] = array_combine(
                $cabecalho,
                array_pad(array_slice($valores, 0, count($cabecalho)), count($cabecalho), ''),
            );
        }

        fclose($handle);

        return $linhas;
    }
}
