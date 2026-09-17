<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logo da clínica, para o cabeçalho do relatório em PDF. Segue o mesmo
 * regime de `photo_path`: disco público — não é dado sensível de criança,
 * é material de marca escolhido pelo próprio psicólogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('clinic_logo_path')->nullable()->after('clinic_zip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('clinic_logo_path');
        });
    }
};
