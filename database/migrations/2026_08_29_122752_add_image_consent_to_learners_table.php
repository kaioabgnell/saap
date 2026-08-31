<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consentimento de uso de imagem do aprendiz.
 *
 * O termo é assinado pelos responsáveis num formulário externo à plataforma;
 * o que fica aqui é a **atestação** do psicólogo de que aquele termo existe,
 * com data. Guardar a data, e não um booleano, é o que permite responder
 * "desde quando havia autorização" — que é a pergunta que a LGPD faz.
 *
 * Nulo significa "sem autorização registrada", e nesse estado o sistema não
 * aceita foto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->timestamp('image_consent_at')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn('image_consent_at');
        });
    }
};
