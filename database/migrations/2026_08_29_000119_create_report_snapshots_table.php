<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O laudo congelado, um por avaliação concluída.
 *
 * O payload duplica o texto do catálogo de propósito — ver ReportPayload.
 * MariaDB 10.4 guarda JSON como LONGTEXT: nunca consultar por caminho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->unique()->constrained('assessments')->cascadeOnDelete();

            $table->json('payload');
            $table->string('pdf_path')->nullable();
            $table->char('content_hash', 64);
            $table->dateTime('generated_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
    }
};
