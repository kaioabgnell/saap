<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resumos de nível redigidos por IA.
 *
 * A tabela se chama `ai_summaries` e não `level_summaries` porque a
 * procedência é o fato mais importante sobre esta linha: o texto vai para os
 * responsáveis e para o prontuário, e quem o escreveu não foi a psicóloga.
 * Nome da tabela, coluna `model` e o aviso impresso no PDF dizem a mesma
 * coisa em três lugares — de propósito.
 *
 * SOMENTE-ACRÉSCIMO, como `appointment_addenda`: cada geração é uma linha
 * nova. Um resumo que já foi enviado à família não pode ser silenciosamente
 * substituído por outro — se a psicóloga gerar de novo e o texto sair
 * diferente, os dois precisam existir para responder "o que eu mandei".
 * A tela e o PDF usam o mais recente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');

            $table->text('body');

            // Qual modelo redigiu. Sem isso, um resumo de 2026 e outro de 2029
            // seriam indistinguíveis depois que o padrão do modelo mudar.
            $table->string('model', 60);
            $table->unsignedInteger('tokens_used')->nullable();

            $table->dateTime('generated_at');
            $table->timestamps();

            $table->index(['assessment_id', 'level', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_summaries');
    }
};
