<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Uma leitura ou download de laudo concluído.
 *
 * Só se escreve por `registrar()`, e nunca se atualiza: o registro de acesso
 * que pode ser alterado não serve para nada.
 */
class ReportAccessLog extends Model
{
    public const NA_TELA = 'view';

    public const DOWNLOAD = 'download';

    public const PELA_API = 'api';

    public $timestamps = false;

    protected $fillable = ['report_snapshot_id', 'user_id', 'action', 'ip_address', 'user_agent', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public static function registrar(ReportSnapshot $snapshot, User $user, string $acao, ?Request $request = null): self
    {
        return self::create([
            'report_snapshot_id' => $snapshot->id,
            'user_id' => $user->id,
            'action' => $acao,
            'ip_address' => $request?->ip(),
            // O cabeçalho vem do cliente: truncar evita que um agente
            // absurdamente longo derrube a gravação do registro.
            'user_agent' => $request === null ? null : mb_substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ReportSnapshot::class, 'report_snapshot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
