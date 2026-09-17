<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agendamento e atendimento são a MESMA linha — ver F11-agenda-e-atendimentos.md.
 *
 * O atendimento é o agendamento depois que alguém apareceu. Duas tabelas 1:1
 * só criariam a chance de discordarem sobre o mesmo fato.
 *
 * Não há `series_id`: a repetição por N semanas cria linhas independentes por
 * decisão. Guardar um identificador de série que nada consome seria prometer
 * um agrupamento que a interface não entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // restrictOnDelete: um atendimento é prontuário, e prontuário não
            // desaparece porque alguém apagou o cadastro. O aprendiz tem soft
            // delete justamente para que esta restrição nunca precise barrar.
            $table->foreignId('learner_id')->constrained('learners')->restrictOnDelete();

            $table->dateTime('starts_at');
            // Guardado, não calculado a partir de uma duração padrão: mudar o
            // padrão da clínica no ano que vem não pode reescrever o passado.
            $table->dateTime('ends_at');

            $table->string('status', 16)->default('scheduled'); // App\Domain\Schedule\AppointmentStatus

            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('checked_out_at')->nullable();

            // Recado da MARCAÇÃO — "vem com a avó", "trazer o relatório da
            // escola". Coluna à parte de `notes` de propósito: é anotação de
            // logística, não prontuário. Se dividisse o campo com o registro
            // clínico, o check-out a trancaria junto e ela viraria documento
            // de 20 anos por acidente.
            $table->string('booking_note', 255)->nullable();

            $table->text('notes')->nullable();
            // Carimbado no check-out. Daí em diante o registro só muda por
            // aditamento — é o mesmo congelamento do laudo.
            $table->dateTime('notes_locked_at')->nullable();

            $table->string('cancel_reason', 255)->nullable();

            // 'opened', não 'sent': o sistema abre o WhatsApp, quem envia é a
            // pessoa. Afirmar "enviado" seria inventar um fato.
            $table->dateTime('reminder_opened_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'starts_at']); // a consulta da agenda
            $table->index(['learner_id', 'starts_at']); // a consulta do prontuário
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
