<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

it('remove pdfs temporarios com mais de 24 horas e preserva os recentes', function () {
    Storage::fake('local');

    Storage::disk('local')->put('temp/formularios/velho.pdf', 'x');
    Storage::disk('local')->put('temp/formularios/novo.pdf', 'x');

    touch(Storage::disk('local')->path('temp/formularios/velho.pdf'), now()->subHours(25)->timestamp);
    touch(Storage::disk('local')->path('temp/formularios/novo.pdf'), now()->subHours(1)->timestamp);

    $this->artisan('vbmapp:prune-form-pdfs')->assertSuccessful();

    Storage::disk('local')->assertMissing('temp/formularios/velho.pdf');
    Storage::disk('local')->assertExists('temp/formularios/novo.pdf');
});
