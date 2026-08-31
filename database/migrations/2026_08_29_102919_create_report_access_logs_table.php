<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de acesso a laudo concluído.
 *
 * Exigência da F9 e da LGPD: laudo de criança é dado sensível, e quem o leu
 * ou baixou precisa ficar registrado. A tabela guarda **quem, quando, como e
 * de onde** — nunca o conteúdo do laudo, que já vive em report_snapshots.
 *
 * Não é imutável por constraint (MariaDB 10.4 não tem tabela append-only),
 * mas nada na aplicação a atualiza ou apaga: só há INSERT e SELECT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_access_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('report_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 'view' = leu na tela, 'download' = baixou o PDF, 'api' = leu pela API.
            $table->string('action', 16);

            // Suporta IPv6 (45 caracteres no pior caso).
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // A consulta natural é "quem acessou o laudo deste aprendiz".
            $table->index(['report_snapshot_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_access_logs');
    }
};
