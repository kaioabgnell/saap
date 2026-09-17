<?php

declare(strict_types=1);

use App\Application\Assessment\CompleteAssessment;
use App\Application\Assessment\CompleteLevel;
use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Application\Assessment\StartLevel;
use App\Application\Report\BuildReportPayload;
use App\Models\Assessment;
use App\Models\Learner;
use App\Models\User;
use App\Models\Vbmapp\Item;
use App\Support\ClinicLogoUploader;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\VbmappAreaSeeder;
use Database\Seeders\VbmappItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VbmappAreaSeeder::class, VbmappItemSeeder::class]);
    Storage::fake('local');
    Storage::fake('public');

    $this->psicologo = User::factory()->create(['name' => 'Dra. Ana']);
    $this->learner = Learner::factory()->for($this->psicologo)->create(['name' => 'Kaleo']);
    $this->assessment = Assessment::factory()->for($this->learner)->for($this->psicologo)->create();

    $this->actingAs($this->psicologo);
});

/** Responde e conclui um nível inteiro, só para ter um marco a montar. */
function completarNivelParaLogo(Assessment $assessment, int $level = 1): void
{
    $nivel = app(StartLevel::class)->handle($assessment, $level);
    $save = app(SaveResponse::class);

    foreach (Item::where('level', $level)->get() as $item) {
        $save->handle(new SaveResponseCommand(assessmentId: $assessment->id, itemId: $item->id, explicitScore: 1.0));
    }

    app(CompleteLevel::class)->handle($nivel->refresh());
}

it('usa a logo do sistema quando a clínica não tem logo própria', function () {
    completarNivelParaLogo($this->assessment);

    $payload = app(BuildReportPayload::class)->handle($this->assessment->refresh());
    $logo = $payload->toArray()['aplicador']['clinica']['logo'];

    $esperado = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/logo-saap-relatorio.png')));

    expect($logo)->toBe($esperado);
});

it('incorpora a logo própria da clínica quando existe', function () {
    $arquivo = UploadedFile::fake()->image('logo.png', 300, 80);
    $this->psicologo->clinic_logo_path = app(ClinicLogoUploader::class)->store($arquivo);
    $this->psicologo->save();

    completarNivelParaLogo($this->assessment);

    $payload = app(BuildReportPayload::class)->handle($this->assessment->refresh());
    $logo = $payload->toArray()['aplicador']['clinica']['logo'];

    $esperado = 'data:image/png;base64,'.base64_encode(
        Storage::disk('public')->get($this->psicologo->clinic_logo_path)
    );

    expect($logo)->toBe($esperado)
        // E não é, por coincidência, a do sistema.
        ->and($logo)->not->toBe('data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/logo-saap-relatorio.png'))));
});

it('mantém a logo antiga no laudo já emitido, mesmo depois de a clínica trocar', function () {
    $primeira = UploadedFile::fake()->image('primeira.png', 300, 80);
    $this->psicologo->clinic_logo_path = app(ClinicLogoUploader::class)->store($primeira);
    $this->psicologo->save();

    completarNivelParaLogo($this->assessment);

    $laudo = app(CompleteAssessment::class)->handle($this->assessment->refresh());
    $logoCongelada = $laudo->payload['aplicador']['clinica']['logo'];

    // A clínica troca a logo DEPOIS do laudo emitido.
    $segunda = UploadedFile::fake()->image('segunda.png', 300, 80);
    $this->psicologo->clinic_logo_path = app(ClinicLogoUploader::class)->store($segunda, $this->psicologo->clinic_logo_path);
    $this->psicologo->save();

    expect($laudo->refresh()->payload['aplicador']['clinica']['logo'])->toBe($logoCongelada);
});

it('mostra a logo como imagem de fato no PDF gerado', function () {
    $arquivo = UploadedFile::fake()->image('logo.png', 300, 80);
    $this->psicologo->clinic_logo_path = app(ClinicLogoUploader::class)->store($arquivo);
    $this->psicologo->save();

    completarNivelParaLogo($this->assessment);

    $snapshot = app(CompleteAssessment::class)->handle($this->assessment->refresh());

    $pdf = Storage::disk('local')->get($snapshot->pdf_path);

    expect(contarImagensDoPdf($pdf))->toBeGreaterThan(0);
});

it('não quebra o laudo em tela nem em PDF de payload emitido antes desta funcionalidade', function () {
    completarNivelParaLogo($this->assessment);

    $snapshot = app(CompleteAssessment::class)->handle($this->assessment->refresh());

    // Simula um laudo emitido antes de a chave 'logo' existir: o payload
    // congelado de verdade nunca tem essa chave criada depois do fato — é
    // exatamente o que um laudo já emitido continua sendo para sempre.
    $payloadAntigo = $snapshot->payload;
    unset($payloadAntigo['aplicador']['clinica']['logo']);
    $snapshot->forceFill(['payload' => $payloadAntigo])->save();

    $this->get(route('avaliacoes.relatorio', $this->assessment))->assertOk();

    $pdf = Pdf::loadView('pdf.relatorio.documento', [
        'payload' => $snapshot->refresh()->reportPayload(),
        'hash' => $snapshot->content_hash,
        'geradoEm' => now()->format('d/m/Y H:i'),
    ]);

    expect(fn () => $pdf->render())->not->toThrow(Throwable::class);
});
