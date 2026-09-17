<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aditamento: a única forma de corrigir um registro já fechado.
 *
 * A tabela é somente-acréscimo — por isso não tem `updated_at`. Um aditamento
 * que pudesse ser editado seria a mesma porta que o fechamento do check-out
 * acabou de trancar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_addenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->text('body');
            $table->dateTime('created_at');

            $table->index(['appointment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_addenda');
    }
};
