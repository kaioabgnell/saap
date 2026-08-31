<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estímulos individuais recortados do material de aplicação.
 * Na v1 apenas o nível 1 é curado (~96 estímulos) — ver fase F4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vbmapp_stimuli', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('vbmapp_items')->cascadeOnDelete();
            $table->string('label', 80);
            $table->string('image_path');
            $table->unsignedSmallInteger('source_page'); // auditoria da origem
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['item_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vbmapp_stimuli');
    }
};
