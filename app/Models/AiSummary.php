<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um resumo de nível redigido por IA — ver a migration para o porquê do nome
 * e do somente-acréscimo.
 */
class AiSummary extends Model
{
    use HasFactory;

    protected $fillable = ['assessment_id', 'level', 'body', 'model', 'tokens_used', 'generated_at'];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'tokens_used' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * O resumo mais recente de um recorte, ou null se nunca foi gerado.
     *
     * `$level` nulo é o resumo do LAUDO, da avaliação inteira — e precisa de
     * `whereNull`: `where('level', null)` vira `level = NULL` em SQL, que não
     * casa com nada, nem com as próprias linhas nulas.
     */
    public static function maisRecente(int $assessmentId, ?int $level): ?self
    {
        return self::where('assessment_id', $assessmentId)
            ->when($level === null, fn ($q) => $q->whereNull('level'), fn ($q) => $q->where('level', $level))
            ->latest('generated_at')
            ->latest('id')
            ->first();
    }

    /** Os títulos que o SummaryBriefing pede ao modelo. */
    private const SECOES = [
        'dados da avaliação',
        'desempenho por área',
        'áreas de maior pontuação',
        'áreas de menor pontuação',
    ];

    /**
     * O corpo quebrado para a view, separando título de parágrafo.
     *
     * O modelo é instruído a não usar markdown, mas instrução não é garantia
     * — e `**Dados da avaliação**` impresso com os asteriscos num documento
     * que vai para a família é o tipo de detalhe que denuncia automação
     * malfeita. Aqui a marcação é removida e o título reconhecido pelo texto,
     * não pela formatação.
     *
     * @return list<array{titulo: bool, texto: string}>
     */
    public function paragrafos(): array
    {
        $linhas = preg_split('/\R+/u', trim((string) $this->body)) ?: [];
        $saida = [];

        foreach ($linhas as $linha) {
            // Tira **negrito**, ##títulos e marcadores de lista.
            $limpa = trim(preg_replace('/^\s*[#>*\-]+\s*|\*+/u', '', $linha) ?? '');

            if ($limpa === '') {
                continue;
            }

            $semPontuacao = rtrim(mb_strtolower($limpa), " :.\u{00A0}");

            $saida[] = [
                'titulo' => in_array($semPontuacao, self::SECOES, true),
                'texto' => $limpa,
            ];
        }

        return $saida;
    }
}
