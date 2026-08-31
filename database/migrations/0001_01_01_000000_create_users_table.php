<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Cadastro: nome, telefone, e-mail e senha são os únicos obrigatórios.
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone', 20)->nullable();

            // Perfil, preenchido depois do cadastro.
            $table->string('whatsapp', 20)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('council_id', 40)->nullable();

            // Dados da clínica.
            $table->string('clinic_name', 160)->nullable();
            $table->string('clinic_phone', 20)->nullable();
            $table->string('clinic_email', 160)->nullable();
            $table->string('clinic_address')->nullable();
            $table->string('clinic_city', 120)->nullable();
            $table->char('clinic_state', 2)->nullable();
            $table->string('clinic_zip', 9)->nullable();

            // Marcador de licença do instrumento — ver .claude/specs/00-visao-geral.md
            $table->string('vbmapp_license_ref', 120)->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
