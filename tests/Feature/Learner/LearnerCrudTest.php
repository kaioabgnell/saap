<?php

declare(strict_types=1);

use App\Models\Assessment;
use App\Models\Learner;
use App\Models\User;
use App\Support\ImageUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Sem isso, o upload de foto grava de verdade em storage/app/private —
// o teste vaza arquivo real a cada execução.
beforeEach(fn () => Storage::fake('local'));

it('cria aprendiz com todos os campos', function () {
    $psicologo = User::factory()->create();

    $response = $this->actingAs($psicologo)->post(route('aprendizes.store'), [
        'name' => 'Kaleo',
        'birth_date' => '2020-03-15',
        'father_name' => 'João',
        'mother_name' => 'Maria',
        'contact_phone' => '(11) 98888-7777',
        'notes' => 'Observação de teste.',
    ]);

    $learner = Learner::sole();

    $response->assertRedirect(route('aprendizes.show', $learner));
    expect($learner->user_id)->toBe($psicologo->id)
        ->and($learner->name)->toBe('Kaleo')
        ->and($learner->father_name)->toBe('João')
        ->and($learner->mother_name)->toBe('Maria');
});

it('recusa data de nascimento futura', function () {
    $psicologo = User::factory()->create();

    $response = $this->actingAs($psicologo)->post(route('aprendizes.store'), [
        'name' => 'Kaleo',
        'birth_date' => now()->addDay()->toDateString(),
    ]);

    $response->assertSessionHasErrors('birth_date');
    expect(Learner::count())->toBe(0);
});

it('recusa data de nascimento anterior a 30 anos', function () {
    $psicologo = User::factory()->create();

    $response = $this->actingAs($psicologo)->post(route('aprendizes.store'), [
        'name' => 'Kaleo',
        'birth_date' => now()->subYears(31)->toDateString(),
    ]);

    $response->assertSessionHasErrors('birth_date');
});

it('sobe e redimensiona a foto do aprendiz', function () {
    $psicologo = User::factory()->create();
    $foto = UploadedFile::fake()->image('aprendiz.jpg', 2000, 1500);

    $this->actingAs($psicologo)->post(route('aprendizes.store'), [
        'name' => 'Kaleo',
        'birth_date' => '2020-03-15',
        'photo' => $foto,
        'image_consent' => '1',
    ]);

    $learner = Learner::sole();

    expect($learner->photo_path)->not->toBeNull();
    Storage::disk('local')->assertExists($learner->photo_path);
    Storage::disk('local')->assertExists(ImageUploader::thumbnailPathFor($learner->photo_path));
});

it('atualiza aprendiz existente', function () {
    $psicologo = User::factory()->create();
    $learner = Learner::factory()->for($psicologo)->create(['name' => 'Nome antigo']);

    $this->actingAs($psicologo)->put(route('aprendizes.update', $learner), [
        'name' => 'Nome novo',
        'birth_date' => $learner->birth_date->toDateString(),
    ]);

    expect($learner->fresh()->name)->toBe('Nome novo');
});

it('bloqueia exclusão de aprendiz com avaliação concluída', function () {
    $psicologo = User::factory()->create();
    $learner = Learner::factory()->for($psicologo)->create();
    Assessment::factory()->completed()->for($learner)->for($psicologo)->create();

    $response = $this->actingAs($psicologo)->delete(route('aprendizes.destroy', $learner));

    $response->assertRedirect(route('aprendizes.show', $learner));
    expect(Learner::find($learner->id))->not->toBeNull();
});

it('permite excluir aprendiz sem avaliação concluída', function () {
    $psicologo = User::factory()->create();
    $learner = Learner::factory()->for($psicologo)->create();

    $response = $this->actingAs($psicologo)->delete(route('aprendizes.destroy', $learner));

    $response->assertRedirect(route('aprendizes.index'));
    expect(Learner::find($learner->id))->toBeNull();
});
