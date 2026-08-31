<?php

declare(strict_types=1);

namespace App\Domain\Vbmapp\Report;

/**
 * O laudo congelado.
 *
 * Guarda TUDO que o relatório precisa — enunciado, critério, rótulo de área —
 * duplicando o texto do catálogo em vez de referenciá-lo. É proposital: se um
 * limiar ou enunciado for corrigido meses depois (e a revisão clínica da F1
 * ainda vai corrigir vários), o laudo já emitido continua reproduzindo
 * exatamente o que foi avaliado. É o que sustenta a promessa de imutabilidade.
 *
 * Não "otimize" trocando por join.
 */
final class ReportPayload
{
    /** @param array<string, mixed> $data */
    public function __construct(public readonly array $data) {}

    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * SHA-256 do payload canônico. Vai impresso no rodapé do PDF e permite
     * conferir depois que o laudo não foi adulterado.
     */
    public function contentHash(): string
    {
        return hash('sha256', json_encode(
            $this->data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    /** @return list<int> níveis efetivamente avaliados, em ordem */
    public function levels(): array
    {
        return array_map(fn (array $n) => $n['nivel'], $this->data['niveis'] ?? []);
    }

    public function chartFor(int $level): ?MilestoneChart
    {
        foreach ($this->data['niveis'] ?? [] as $nivel) {
            if ($nivel['nivel'] === $level) {
                return MilestoneChart::fromAreas($level, $nivel['areas']);
            }
        }

        return null;
    }
}
