<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uma linha por exemplar registrado. É o detalhe que alimenta a contagem e o
 * que o relatório detalha marco a marco.
 *
 * Tabela em vez de JSON porque o MariaDB 10.4 não consulta JSON por caminho —
 * ver 02-modelo-de-dados.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('response_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->constrained('responses')->cascadeOnDelete();

            $table->unsignedSmallInteger('position');
            $table->foreignId('stimulus_id')->nullable()->constrained('vbmapp_stimuli')->nullOnDelete();
            $table->string('list_key', 80)->nullable();   // counter_list e matrix
            $table->string('column_key', 80)->nullable(); // matrix
            $table->string('text_value')->nullable();     // counter_free
            $table->boolean('is_checked')->default(false);

            $table->timestamps();

            $table->unique(['response_id', 'position', 'column_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('response_entries');
    }
};
