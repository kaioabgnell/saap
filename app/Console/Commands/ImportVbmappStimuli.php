<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Models\Vbmapp\Area;
use App\Models\Vbmapp\Item;
use App\Models\Vbmapp\Stimulus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * Importa acervo de figuras CURADO À MÃO, de uma pasta por marco.
 *
 * Não substitui o `vbmapp:import-material`: aquele recorta o PDF do material
 * e depende da curadoria em /admin/estimulos para descobrir os rótulos. Aqui
 * as figuras já vêm escolhidas e nomeadas pela psicóloga, num arquivo
 * versionado (database/data/vbmapp-estimulos-nivel-N.json) que é a fonte da
 * verdade — rodar de novo reconstrói o mesmo acervo, sem duplicar.
 *
 * Redimensiona no caminho: as capturas de tela chegam com até 2500px e 4 MB,
 * para um espaço de ~150px na grade. Ver o que isso custou no laudo em
 * BuildReportPayload::logoEmBase64().
 */
class ImportVbmappStimuli extends Command
{
    protected $signature = 'vbmapp:import-stimuli {--level=3 : Nível do acervo} {--dry-run : Só confere, não grava}';

    protected $description = 'Importa figuras curadas à mão como estímulos dos marcos';

    /** Lado máximo em pixels. Folga para a tela de apresentação, que exibe grande. */
    private const LADO_MAXIMO = 900;

    public function handle(ImageManager $imagens): int
    {
        $level = (int) $this->option('level');
        $simulacao = (bool) $this->option('dry-run');

        try {
            $dados = $this->lerArquivo($level);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $totalFiguras = 0;

        foreach ($dados['marcos'] ?? [] as $marco) {
            $item = $this->acharMarco($marco, $level);

            if ($item === null) {
                $this->error("Marco {$marco['area']}:{$marco['position']} não existe no nível {$level}.");

                return self::FAILURE;
            }

            if (($erro = $this->conferirMatriz($item, $marco)) !== null) {
                $this->error($erro);

                return self::FAILURE;
            }

            $gravadas = $this->importarMarco($item, $marco, $imagens, $simulacao);
            $totalFiguras += $gravadas;

            $this->line(sprintf(
                '  %s %s:%-2d  %2d figuras  (marco pede %d)',
                $gravadas >= $item->threshold_full || $item->response_type->value === 'matrix' ? 'ok  ' : 'FALTA',
                $marco['area'], $marco['position'], $gravadas, $item->threshold_full,
            ));
        }

        if ($simulacao) {
            $this->newLine();
            $this->warn("Simulação: {$totalFiguras} figuras seriam importadas. Nada foi gravado.");

            return self::SUCCESS;
        }

        CatalogCache::flush();

        $this->newLine();
        $this->info("{$totalFiguras} figuras importadas no nível {$level}.");

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function lerArquivo(int $level): array
    {
        $caminho = base_path("database/data/vbmapp-estimulos-nivel-{$level}.json");

        if (! is_file($caminho)) {
            throw new RuntimeException("Não há acervo curado para o nível {$level}: {$caminho} não existe.");
        }

        $dados = json_decode((string) file_get_contents($caminho), true);

        if (! is_array($dados)) {
            throw new RuntimeException("Arquivo inválido: {$caminho}.");
        }

        return $dados;
    }

    /** @param array<string, mixed> $marco */
    private function acharMarco(array $marco, int $level): ?Item
    {
        $area = Area::where('code', $marco['area'])->first();

        return $area === null ? null : Item::where('area_id', $area->id)
            ->where('level', $level)
            ->where('position', $marco['position'])
            ->first();
    }

    /**
     * Numa matriz a figura não é um check: é a identidade de uma LINHA. Se o
     * rótulo não bate com `fixed_list`, a figura fica órfã e a linha aparece
     * sem imagem — falha silenciosa que só apareceria na frente da criança.
     *
     * @param  array<string, mixed>  $marco
     */
    private function conferirMatriz(Item $item, array $marco): ?string
    {
        if ($item->response_type->value !== 'matrix') {
            return null;
        }

        $linhas = $item->fixed_list ?? [];
        $rotulos = array_column($marco['figuras'] ?? [], 'rotulo');
        $orfaos = array_diff($rotulos, $linhas);

        if ($orfaos !== []) {
            return sprintf(
                'Em %s:%d os rótulos [%s] não são linhas da matriz. Linhas do catálogo: [%s].',
                $marco['area'], $marco['position'], implode(', ', $orfaos), implode(', ', $linhas),
            );
        }

        return null;
    }

    /** @param array<string, mixed> $marco */
    private function importarMarco(Item $item, array $marco, ImageManager $imagens, bool $simulacao): int
    {
        $pasta = base_path($marco['pasta']);
        $destino = "vbmapp/estimulos/nivel-{$item->level}";
        $posicao = 0;
        $gravadas = 0;

        // A instrução acompanha o acervo, não o instrumento: é o que fazer com
        // ESTAS figuras. Acompanha também a ausência — tirar "instrucao" do
        // JSON tem de limpar a coluna, senão o texto fica órfão no banco.
        if (! $simulacao) {
            $item->update(['stimulus_prompt' => $marco['instrucao'] ?? null]);
        }

        foreach ($marco['figuras'] ?? [] as $figura) {
            $origem = $pasta.'/'.$figura['arquivo'];
            $posicao++;

            if (! is_file($origem)) {
                $this->warn("  faltando: {$origem}");

                continue;
            }

            if ($simulacao) {
                $gravadas++;

                continue;
            }

            $imagem = $imagens->decodePath($origem);
            $imagem->scaleDown(width: self::LADO_MAXIMO, height: self::LADO_MAXIMO);

            // Fotografia em PNG é desperdício puro: as capturas deste acervo
            // chegam com até 800 KB cada, e a mesma figura em JPEG fica em
            // 90 KB sem diferença visível a 150px. Só o que tem transparência
            // de verdade continua PNG — achatar um recorte contra branco
            // estragaria a figura em qualquer fundo que não fosse branco.
            $transparente = $this->temTransparencia($origem);

            [$extensao, $conteudo] = $transparente
                ? ['png', (string) $imagem->encode(new PngEncoder)]
                : ['jpg', (string) $imagem->encode(new JpegEncoder(quality: 82))];

            // Nome estável: reimportar sobrescreve o mesmo arquivo em vez de
            // deixar órfão no disco a cada rodada.
            $nome = sprintf('n%d-%s-%02d-%s.%s', $item->level, $marco['area'], $marco['position'], Str::slug($figura['rotulo']), $extensao);
            $caminho = "{$destino}/{$nome}";

            Storage::disk('public')->put($caminho, $conteudo);

            $anterior = Stimulus::where('item_id', $item->id)->where('position', $posicao)->first();

            Stimulus::updateOrCreate(
                ['item_id' => $item->id, 'position' => $posicao],
                [
                    'label' => $figura['rotulo'],
                    'image_path' => $caminho,
                    // 0 = não veio de página do PDF. A procedência destas
                    // figuras é a pasta declarada no JSON versionado.
                    'source_page' => 0,
                ],
            );

            // Rótulo renomeado ou formato trocado muda o caminho: o arquivo
            // velho não pode ficar para trás ocupando o disco.
            if ($anterior !== null && $anterior->image_path !== $caminho) {
                Storage::disk('public')->delete($anterior->image_path);
            }

            $gravadas++;
        }

        // Reimportação com menos figuras não pode deixar sobra da rodada anterior.
        if (! $simulacao) {
            foreach (Stimulus::where('item_id', $item->id)->where('position', '>', $posicao)->get() as $sobra) {
                Storage::disk('public')->delete($sobra->image_path);
                $sobra->delete();
            }
        }

        return $gravadas;
    }

    /**
     * A figura tem transparência de verdade?
     *
     * Não basta olhar o canal alfa: estas capturas de tela chegam em RGBA com
     * o canal inteiro opaco — teriam o canal, mas nada transparente nele. Por
     * isso a checagem amostra os pixels em vez de ler o cabeçalho.
     */
    private function temTransparencia(string $arquivo): bool
    {
        $gd = @imagecreatefromstring((string) file_get_contents($arquivo));

        if ($gd === false) {
            return true; // não deu para saber: PNG é o lado seguro
        }

        if (! imageistruecolor($gd)) {
            imagepalettetotruecolor($gd);
        }

        $largura = imagesx($gd);
        $altura = imagesy($gd);
        $passo = max(1, (int) floor(min($largura, $altura) / 60));

        for ($x = 0; $x < $largura; $x += $passo) {
            for ($y = 0; $y < $altura; $y += $passo) {
                // No GD o alfa tem 7 bits: 0 é opaco, 127 é invisível.
                if (((imagecolorat($gd, $x, $y) >> 24) & 0x7F) > 8) {
                    imagedestroy($gd);

                    return true;
                }
            }
        }

        imagedestroy($gd);

        return false;
    }
}
