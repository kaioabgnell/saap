<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Os 170 marcos: 34 pares área/nível × 5.
 * Nível 1 = posições 1-5, nível 2 = 6-10, nível 3 = 11-15.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vbmapp_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('vbmapp_areas')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->unsignedTinyInteger('position'); // 1..15, contínuo entre níveis
            $table->string('code', 16);              // código do manual: "3-M"

            $table->text('statement');
            $table->text('objective')->nullable();
            $table->text('materials')->nullable();
            $table->text('examples')->nullable();

            $table->text('criteria_full');
            // Nulo quando o marco não tem meio ponto — caso real: Ouvinte 2-M,
            // "Não há ½ ponto para esta habilidade" (manual, p. 80).
            $table->text('criteria_half')->nullable();

            $table->string('response_type', 24);
            $table->unsignedSmallInteger('threshold_full');
            $table->unsignedSmallInteger('threshold_half')->nullable();
            $table->string('scoring_mode', 12)->default('auto'); // auto | assisted

            $table->unsignedSmallInteger('observation_minutes')->nullable();

            // Listas pré-definidas dos tipos counter_list e matrix.
            // MariaDB 10.4 guarda JSON como LONGTEXT: nunca consultar por caminho.
            $table->json('fixed_list')->nullable();
            $table->json('matrix_columns')->nullable();

            $table->timestamps();

            $table->unique(['level', 'area_id', 'position']);
            $table->index(['level', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vbmapp_items');
    }
};
