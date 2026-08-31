<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // aplicador

            $table->string('instrument', 24)->default('vbmapp');
            $table->string('status', 16)->default('not_started');

            $table->date('applied_on');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('locked_at')->nullable(); // preenchido = imutável
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->text('observations')->nullable();

            $table->timestamps();

            $table->index(['learner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
