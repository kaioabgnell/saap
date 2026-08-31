<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo do instrumento VB-MAPP — imutável em runtime, populado por seeder.
 * O prefixo vbmapp_ isola o conteúdo licenciado. Ver .claude/specs/00-visao-geral.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vbmapp_areas', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->string('short_name', 24); // rótulo do gráfico: "VP/MTS"
            $table->unsignedTinyInteger('position');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vbmapp_areas');
    }
};
