<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\ImageUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('public'));

it('salva dados pessoais e da clínica no mesmo perfil', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch('/perfil', [
        'name' => $user->name,
        'email' => $user->email,
        'phone' => '(11) 90000-0000',
        'whatsapp' => '(11) 90000-0000',
        'council_id' => 'CRP 06/12345',
        'clinic_name' => 'Clínica Exemplo',
        'clinic_phone' => '(11) 3000-0000',
        'clinic_email' => 'contato@clinica.example',
        'clinic_address' => 'Rua Exemplo, 100',
        'clinic_city' => 'São Paulo',
        'clinic_state' => 'SP',
        'clinic_zip' => '01000-000',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect('/perfil');

    $user->refresh();

    expect($user->phone)->toBe('(11) 90000-0000')
        ->and($user->council_id)->toBe('CRP 06/12345')
        ->and($user->clinic_name)->toBe('Clínica Exemplo')
        ->and($user->clinic_city)->toBe('São Paulo')
        ->and($user->clinic_state)->toBe('SP');
});

it('sobe e redimensiona a foto de perfil', function () {
    $user = User::factory()->create();
    $foto = UploadedFile::fake()->image('perfil.jpg', 2000, 2000);

    $response = $this->actingAs($user)->patch('/perfil', [
        'name' => $user->name,
        'email' => $user->email,
        'photo' => $foto,
    ]);

    $response->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->photo_path);
    Storage::disk('public')->assertExists(ImageUploader::thumbnailPathFor($user->photo_path));
});

it('substitui a foto anterior ao subir uma nova', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/perfil', [
        'name' => $user->name,
        'email' => $user->email,
        'photo' => UploadedFile::fake()->image('primeira.jpg'),
    ]);
    $primeiraFoto = $user->refresh()->photo_path;

    $this->actingAs($user)->patch('/perfil', [
        'name' => $user->name,
        'email' => $user->email,
        'photo' => UploadedFile::fake()->image('segunda.jpg'),
    ]);
    $segundaFoto = $user->refresh()->photo_path;

    expect($segundaFoto)->not->toBe($primeiraFoto);
    Storage::disk('public')->assertMissing($primeiraFoto);
    Storage::disk('public')->assertExists($segundaFoto);
});
