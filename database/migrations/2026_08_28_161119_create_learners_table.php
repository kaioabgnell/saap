<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('name', 160);
            $table->date('birth_date'); // base do cálculo de idade — ver App\Domain\Learner\Age
            $table->string('father_name', 160)->nullable();
            $table->string('mother_name', 160)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learners');
    }
};
