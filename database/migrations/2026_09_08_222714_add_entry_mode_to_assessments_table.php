<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Como a avaliação foi conduzida: marco a marco na tela ('guided') ou
 * transcrita de um formulário em papel já aplicado ('chart').
 *
 * O modo nasce com a avaliação e nunca muda — ver F10. É ele que impede a
 * tela do nível de abrir uma transcrição e apagá-la no primeiro toque.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('entry_mode', 16)->default('guided')->after('instrument');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('entry_mode');
        });
    }
};
