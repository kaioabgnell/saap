<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `computed_score` é "o que o SISTEMA calculou". Numa transcrição de papel o
 * sistema não calculou nada: a pontuação veio pronta do formulário.
 *
 * Guardar 0 ali seria pior que guardar nada — com `score = 1` o
 * `is_overridden` viraria verdadeiro e o laudo imprimiria "pontuação ajustada
 * pelo aplicador" em quase todos os marcos, afirmando uma conduta clínica que
 * nunca houve. Nulo diz a verdade: não houve cálculo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->decimal('computed_score', 2, 1)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->decimal('computed_score', 2, 1)->default(0)->change();
        });
    }
};
