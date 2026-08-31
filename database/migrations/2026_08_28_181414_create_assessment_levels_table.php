<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uma linha por nível INICIADO. Níveis nunca iniciados não têm linha — é assim
 * que a conclusão da avaliação sabe o que precisa estar completo e o que
 * simplesmente não foi avaliado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('status', 16)->default('in_progress');

            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();

            // Desnormalizado: recalculado na MESMA transação da gravação,
            // nunca por job. Evita contar 45 linhas a cada render.
            $table->unsignedSmallInteger('answered_count')->default(0);
            $table->unsignedSmallInteger('total_count'); // 45, 60 ou 65
            $table->decimal('score_total', 4, 1)->default(0);

            $table->timestamps();

            $table->unique(['assessment_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_levels');
    }
};
