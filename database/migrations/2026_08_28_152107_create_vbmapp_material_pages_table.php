<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Páginas inteiras do material de aplicação, renderizadas em PNG.
 * Usadas pelos níveis 2 e 3 na v1 e como fallback no nível 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vbmapp_material_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('level');
            $table->foreignId('area_id')->nullable()->constrained('vbmapp_areas')->nullOnDelete();
            $table->unsignedTinyInteger('item_position')->nullable();
            $table->string('image_path');
            $table->unsignedSmallInteger('page_number');
            $table->string('source_file', 160);
            $table->timestamps();

            $table->index(['level', 'area_id', 'item_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vbmapp_material_pages');
    }
};
