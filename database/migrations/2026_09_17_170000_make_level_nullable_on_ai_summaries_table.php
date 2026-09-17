<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `level` nulo passa a significar "a avaliação inteira" — o resumo do laudo
 * final, que fala dos três níveis juntos.
 *
 * Um resumo por nível e o resumo do laudo são o mesmo tipo de documento (texto
 * de IA, datado, com o modelo registrado) e merecem a mesma tabela. O que muda
 * é o recorte, e é isso que a coluna passa a dizer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_summaries', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_summaries', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->nullable(false)->change();
        });
    }
};
