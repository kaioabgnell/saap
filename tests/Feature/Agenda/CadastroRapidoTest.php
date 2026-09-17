<?php

declare(strict_types=1);

use App\Livewire\Agenda\AppointmentForm;
use App\Models\Appointment;
use App\Models\Learner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-09-10 10:00');
    $this->psicologo = User::factory()->create();
    $this->actingAs($this->psicologo);
});

afterEach(fn () => Carbon::setTestNow());

function cadastro(array $dados = []): Testable
{
    return Livewire::test(AppointmentForm::class)
        ->call('abrir', '2026-09-17', '15:00')
        ->call('abrirCadastroRapido')
        ->set('novoNome', $dados['nome'] ?? 'Kaleo Souza')
        ->set('novoPai', $dados['pai'] ?? '')
        ->set('novaMae', $dados['mae'] ?? 'Ana Souza')
        ->set('novoTelefone', $dados['telefone'] ?? '(11) 90000-0000')
        ->set('novoNascimento', $dados['nascimento'] ?? '2020-03-15');
}

it('cadastra o aprendiz sem sair da agenda e já o seleciona', function () {
    $componente = cadastro()->call('cadastrarRapido')->assertHasNoErrors();

    $learner = Learner::sole();

    expect($learner->name)->toBe('Kaleo Souza')
        ->and($learner->mother_name)->toBe('Ana Souza')
        ->and($learner->user_id)->toBe($this->psicologo->id);

    $componente
        ->assertSet('cadastroRapido', false)
        ->assertSet('learnerId', $learner->id)
        // Segue no modal do agendamento, com a data que já estava preenchida.
        ->assertSet('aberto', true)
        ->assertSet('data', '2026-09-17');
});

it('leva o que já foi digitado na busca para o nome', function () {
    Livewire::test(AppointmentForm::class)
        ->call('abrir')
        ->set('busca', 'Kaleo')
        ->call('abrirCadastroRapido')
        ->assertSet('novoNome', 'Kaleo');
});

it('agenda direto para o aprendiz recém-criado', function () {
    cadastro()->call('cadastrarRapido')->call('salvar')->assertHasNoErrors();

    $agendamento = Appointment::sole();

    expect($agendamento->learner_id)->toBe(Learner::sole()->id)
        ->and($agendamento->starts_at->format('Y-m-d H:i'))->toBe('2026-09-17 15:00');
});

it('exige nome, telefone e data de nascimento', function (string $campo, string $erro) {
    cadastro([$campo => ''])->call('cadastrarRapido')->assertHasErrors($erro);

    expect(Learner::count())->toBe(0);
})->with([
    ['nome', 'novoNome'],
    ['telefone', 'novoTelefone'],
    ['nascimento', 'novoNascimento'],
]);

/**
 * A data de nascimento é obrigatória também aqui (Decisão 3): sem ela o
 * aprendiz não pode ser avaliado, porque o laudo imprime a idade na data da
 * aplicação. É o que mantém `learners.birth_date` NOT NULL.
 */
it('recusa data de nascimento no futuro', function () {
    cadastro(['nascimento' => '2027-01-01'])->call('cadastrarRapido')
        ->assertHasErrors('novoNascimento');
});

it('exige o nome do pai ou o da mãe', function () {
    cadastro(['pai' => '', 'mae' => ''])->call('cadastrarRapido')
        ->assertHasErrors(['novoPai', 'novaMae']);

    expect(Learner::count())->toBe(0);
});

it('aceita só o pai', function () {
    cadastro(['pai' => 'João Souza', 'mae' => ''])->call('cadastrarRapido')->assertHasNoErrors();

    expect(Learner::sole()->father_name)->toBe('João Souza');
});

/**
 * Nenhuma regra de aprendiz nasce no cadastro rápido — se nascesse, o sistema
 * teria duas verdades sobre o que é um cadastro válido.
 *
 * O teste compara o resultado das duas portas em vez de espiar a chamada:
 * CreateLearner é `final` e não se deixa dublar, e o que importa mesmo é que
 * o aprendiz saia idêntico pelos dois caminhos.
 */
it('produz o mesmo aprendiz que a tela de cadastro', function () {
    $dados = [
        'name' => 'Kaleo Souza',
        'birth_date' => '2020-03-15',
        'mother_name' => 'Ana Souza',
        'contact_phone' => '(11) 90000-0000',
    ];

    $this->post(route('aprendizes.store'), $dados)->assertRedirect();
    $pelaTela = Learner::sole();

    $pelaTela->forceDelete();

    cadastro([
        'nome' => $dados['name'],
        'mae' => $dados['mother_name'],
        'telefone' => $dados['contact_phone'],
        'nascimento' => $dados['birth_date'],
    ])->call('cadastrarRapido')->assertHasNoErrors();

    $pelaAgenda = Learner::sole();

    $comparaveis = ['user_id', 'name', 'birth_date', 'father_name', 'mother_name',
        'contact_phone', 'photo_path', 'image_consent_at', 'notes'];

    expect($pelaAgenda->only($comparaveis))->toEqual($pelaTela->only($comparaveis));
});

it('não cria aprendiz sem consentimento de imagem, porque não há foto', function () {
    cadastro()->call('cadastrarRapido');

    expect(Learner::sole()->temConsentimentoDeImagem())->toBeFalse();
});
