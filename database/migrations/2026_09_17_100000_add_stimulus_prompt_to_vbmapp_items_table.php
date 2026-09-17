<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A instrução que o psicólogo lê ao usar o acervo de um marco.
 *
 * Não é o enunciado (`statement`), que é o texto do instrumento, nem
 * `materials`, que descreve o que é preciso ter em mãos. É a frase curta que
 * diz o que FAZER com as figuras que estão na tela — em Ouvinte 11 do nível 3,
 * "Solicitar algum desses falando cor ou forma".
 *
 * Mora no catálogo, e não na Blade, porque é conteúdo: cada marco com acervo
 * curado pode precisar da sua, e uma cadeia de `match` na view seria conteúdo
 * escondido em lugar nenhum. Nulo é o normal — só marco com acervo próprio e
 * instrução específica preenche.
 *
 * Quem grava é `vbmapp:import-stimuli`, a partir do JSON versionado do acervo.
 * O seeder do catálogo não lista esta coluna no `updateOrCreate`, então
 * re-semear o catálogo não apaga o que o import escreveu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vbmapp_items', function (Blueprint $table) {
            $table->string('stimulus_prompt', 160)->nullable()->after('examples');
        });
    }

    public function down(): void
    {
        Schema::table('vbmapp_items', function (Blueprint $table) {
            $table->dropColumn('stimulus_prompt');
        });
    }
};
