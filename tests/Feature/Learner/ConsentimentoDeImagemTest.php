<?php

declare(strict_types=1);

use App\Models\Learner;
use App\Models\User;
use App\Support\ImageUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Foto de criança só existe no sistema com termo assinado pelos responsáveis.
 *
 * O termo é assinado fora da plataforma; a caixa é a atestação do psicólogo de
 * que ele existe, e a data é o que responde "desde quando havia autorização".
 */
beforeEach(function () {
    Storage::fake('local');
    $this->psicologo = User::factory()->create();
    $this->actingAs($this->psicologo);
});

it('recusa foto sem a autorização dos responsáveis', function () {
    $this->from(route('aprendizes.create'))->post(route('aprendizes.store'), [
        'name' => 'Kaleo',
        'birth_date' => '2020-03-15',
        'photo' => UploadedFile::fake()->image('foto.jpg'),
    ])->assertSessionHasErrors('image_consent');

    expect(Learner::count())->toBe(0);
});

it('aceita foto quando a autorização está marcada, e registra a data', function () {
    $this->post(route('aprendizes.store'), [
        'name' => 'Kaleo',
        'birth_date' => '2020-03-15',
        'photo' => UploadedFile::fake()->image('foto.jpg'),
        'image_consent' => '1',
    ])->assertRedirect();

    $learner = Learner::sole();

    expect($learner->photo_path)->not->toBeNull()
        ->and($learner->temConsentimentoDeImagem())->toBeTrue()
        ->and($learner->image_consent_at->isToday())->toBeTrue();
});

it('permite cadastrar sem foto e sem autorização', function () {
    // A autorização é sobre a imagem. Um aprendiz sem foto não precisa dela, e
    // exigir a caixa aí seria pedir consentimento para nada.
    $this->post(route('aprendizes.store'), [
        'name' => 'Kaleo',
        'birth_date' => '2020-03-15',
    ])->assertRedirect();

    expect(Learner::sole()->temConsentimentoDeImagem())->toBeFalse();
});

it('revogar a autorização apaga a foto', function () {
    $this->post(route('aprendizes.store'), [
        'name' => 'Kaleo',
        'birth_date' => '2020-03-15',
        'photo' => UploadedFile::fake()->image('foto.jpg'),
        'image_consent' => '1',
    ]);

    $learner = Learner::sole();
    $caminho = $learner->photo_path;
    $miniatura = ImageUploader::thumbnailPathFor($caminho);

    Storage::disk('local')->assertExists($caminho);

    // Desmarcar a caixa é revogar: guardar a imagem depois disso seria
    // retenção sem base legal.
    $this->put(route('aprendizes.update', $learner), [
        'name' => 'Kaleo',
        'birth_date' => '2020-03-15',
    ])->assertRedirect();

    $learner->refresh();

    expect($learner->photo_path)->toBeNull()
        ->and($learner->temConsentimentoDeImagem())->toBeFalse();

    Storage::disk('local')->assertMissing($caminho);
    Storage::disk('local')->assertMissing($miniatura);
});

it('preserva a data original quando a autorização é reconfirmada', function () {
    $learner = Learner::factory()->for($this->psicologo)
        ->create(['image_consent_at' => now()->subMonths(6)]);

    $original = $learner->image_consent_at;

    $this->put(route('aprendizes.update', $learner), [
        'name' => $learner->name,
        'birth_date' => $learner->birth_date->toDateString(),
        'image_consent' => '1',
    ])->assertRedirect();

    // A pergunta da LGPD é "desde quando havia autorização" — carimbar hoje a
    // cada edição apagaria justamente a resposta.
    expect($learner->fresh()->image_consent_at->toDateTimeString())
        ->toBe($original->toDateTimeString());
});
