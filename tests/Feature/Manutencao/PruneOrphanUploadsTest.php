<?php

declare(strict_types=1);

use App\Models\Learner;
use App\Models\User;
use App\Support\ImageUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

function fotoEm(string $disco, string $caminho, int $horasAtras = 48): void
{
    Storage::disk($disco)->put($caminho, 'jpeg');
    Storage::disk($disco)->put(ImageUploader::thumbnailPathFor($caminho), 'jpeg');

    foreach ([$caminho, ImageUploader::thumbnailPathFor($caminho)] as $arquivo) {
        touch(Storage::disk($disco)->path($arquivo), now()->subHours($horasAtras)->timestamp);
    }
}

it('remove a foto de aprendiz que já não tem dono', function () {
    fotoEm('local', 'aprendizes/99/orfa.jpg');

    $this->artisan('saap:prune-orphan-uploads')->assertSuccessful();

    Storage::disk('local')->assertMissing('aprendizes/99/orfa.jpg');
    Storage::disk('local')->assertMissing('aprendizes/99/orfa-thumb.jpg');
});

it('preserva a foto referenciada por um aprendiz', function () {
    $learner = Learner::factory()->for(User::factory())->create(['photo_path' => 'aprendizes/1/atual.jpg']);
    fotoEm('local', $learner->photo_path);

    $this->artisan('saap:prune-orphan-uploads')->assertSuccessful();

    Storage::disk('local')->assertExists('aprendizes/1/atual.jpg');
    Storage::disk('local')->assertExists('aprendizes/1/atual-thumb.jpg');
});

it('preserva a foto de aprendiz removido em soft delete', function () {
    // O aprendiz saiu da lista mas não do banco: a foto ainda tem dono, e
    // apagá-la aqui destruiria dado que o psicólogo pode restaurar.
    $learner = Learner::factory()->for(User::factory())->create(['photo_path' => 'aprendizes/2/arquivada.jpg']);
    fotoEm('local', $learner->photo_path);
    $learner->delete();

    $this->artisan('saap:prune-orphan-uploads')->assertSuccessful();

    Storage::disk('local')->assertExists('aprendizes/2/arquivada.jpg');
});

it('não toca em upload recente', function () {
    // Janela de segurança: o arquivo pode estar no meio de uma requisição,
    // já em disco e ainda sem photo_path no banco.
    fotoEm('local', 'aprendizes/3/recem-enviada.jpg', horasAtras: 1);

    $this->artisan('saap:prune-orphan-uploads')->assertSuccessful();

    Storage::disk('local')->assertExists('aprendizes/3/recem-enviada.jpg');
});

it('varre também as fotos de perfil, no disco público', function () {
    $user = User::factory()->create(['photo_path' => 'perfis/1/eu.jpg']);
    fotoEm('public', $user->photo_path);
    fotoEm('public', 'perfis/1/antiga.jpg');

    $this->artisan('saap:prune-orphan-uploads')->assertSuccessful();

    Storage::disk('public')->assertExists('perfis/1/eu.jpg');
    Storage::disk('public')->assertMissing('perfis/1/antiga.jpg');
});

it('não apaga nada em dry-run', function () {
    fotoEm('local', 'aprendizes/99/orfa.jpg');

    $this->artisan('saap:prune-orphan-uploads', ['--dry-run' => true])->assertSuccessful();

    Storage::disk('local')->assertExists('aprendizes/99/orfa.jpg');
});
