<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('vbmapp_items')->cascadeOnDelete();

            $table->decimal('score', 2, 1)->default(0);          // o valor que vale
            $table->decimal('computed_score', 2, 1)->default(0); // o que o sistema calculou
            $table->boolean('is_overridden')->default(false);
            $table->string('override_reason')->nullable();

            $table->text('notes')->nullable();

            // Nulo = registrado mas ainda NÃO respondido.
            //
            // Serve aos marcos de scoring_mode 'assisted', em que os dois
            // critérios pedem a mesma contagem e só a qualidade os separa
            // (Mando 4-M: 5 mandos diferentes contra 5 sempre iguais). O
            // psicólogo digita os exemplares — que ficam salvos na hora, sem
            // risco de perda — mas o marco só conta como respondido depois da
            // confirmação explícita de qual critério foi atingido.
            //
            // Para todo o resto, é preenchido na própria gravação.
            $table->dateTime('answered_at')->nullable();

            $table->timestamps();

            // Torna a gravação idempotente: reenviar após queda de rede
            // converge no mesmo estado em vez de duplicar. Base do PUT da API.
            $table->unique(['assessment_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responses');
    }
};
