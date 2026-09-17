<?php

declare(strict_types=1);

namespace App\Livewire\Agenda;

use App\Application\Learner\CreateLearner;
use App\Application\Schedule\RescheduleAppointment;
use App\Application\Schedule\ScheduleAppointment;
use App\Application\Schedule\ScheduleAppointmentCommand;
use App\Application\Schedule\ScheduleConflict;
use App\Models\Appointment;
use App\Models\Learner;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

/**
 * O modal de agendar e remarcar — com o alerta de conflito e o cadastro
 * rápido do aprendiz dentro.
 *
 * Os três moram no mesmo componente porque são o mesmo gesto interrompido:
 * a psicóloga está marcando um horário e descobre, no meio, que falta o
 * cadastro ou que o horário está ocupado. Mandá-la para outra tela em
 * qualquer um dos dois casos custaria o agendamento.
 */
class AppointmentForm extends Component
{
    public bool $aberto = false;

    /** Preenchido só na remarcação — muda o caso de uso e o título. */
    public ?int $appointmentId = null;

    public ?int $learnerId = null;

    public string $busca = '';

    public string $data = '';

    public string $hora = '';

    public int $duracao = ScheduleAppointment::DURACAO_PADRAO;

    public int $repetir = 1;

    public string $recado = '';

    /**
     * O alerta, já em texto. Guardar os models aqui obrigaria a serializá-los
     * entre requisições — e o que a tela precisa é da frase, não da linha.
     *
     * @var list<array{quando: string, nomes: string}>
     */
    public array $conflitos = [];

    // ------------------------------------------------- cadastro rápido
    public bool $cadastroRapido = false;

    public string $novoNome = '';

    public string $novoPai = '';

    public string $novaMae = '';

    public string $novoTelefone = '';

    public string $novoNascimento = '';

    public ?string $erro = null;

    #[On('abrir-agendamento')]
    public function abrir(?string $data = null, ?string $hora = null): void
    {
        $this->reset();
        $this->aberto = true;
        $this->data = $data ?? now()->toDateString();
        $this->hora = $hora ?? '09:00';
    }

    #[On('abrir-remarcacao')]
    public function abrirRemarcacao(int $appointmentId): void
    {
        $agendamento = Appointment::where('user_id', auth()->id())->findOrFail($appointmentId);
        $this->authorize('update', $agendamento);

        $this->reset();
        $this->aberto = true;
        $this->appointmentId = $agendamento->id;
        $this->learnerId = $agendamento->learner_id;
        $this->data = $agendamento->starts_at->toDateString();
        $this->hora = $agendamento->starts_at->format('H:i');
        $this->duracao = $agendamento->duracaoPrevista();
    }

    public function fechar(): void
    {
        $this->reset();
    }

    public function remarcando(): bool
    {
        return $this->appointmentId !== null;
    }

    /** Os aprendizes da conta, filtrados pela busca do seletor. */
    #[Computed]
    public function aprendizes(): Collection
    {
        return Learner::query()
            ->where('user_id', auth()->id())
            ->when(trim($this->busca) !== '', fn ($q) => $q->where('name', 'like', '%'.trim($this->busca).'%'))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);
    }

    #[Computed]
    public function selecionado(): ?Learner
    {
        return $this->learnerId === null
            ? null
            : Learner::where('user_id', auth()->id())->find($this->learnerId);
    }

    public function selecionar(int $learnerId): void
    {
        $this->learnerId = $learnerId;
        $this->busca = '';
        $this->conflitos = [];
        $this->erro = null;
        unset($this->selecionado);
    }

    /** Trocar de aprendiz, data ou hora invalida o alerta já mostrado. */
    public function updated(string $campo): void
    {
        if (in_array($campo, ['data', 'hora', 'duracao', 'repetir', 'learnerId'], true)) {
            $this->conflitos = [];
        }
    }

    // ---------------------------------------------------------- gravação

    public function salvar(bool $permitirConflito = false): void
    {
        $this->erro = null;

        $dados = $this->validate($this->regras(), attributes: [
            'learnerId' => 'aprendiz',
            'data' => 'data',
            'hora' => 'hora de início',
            'duracao' => 'duração',
            'repetir' => 'repetição',
        ]);

        $inicio = $dados['data'].' '.$dados['hora'];

        try {
            $conflitos = $this->remarcando()
                ? $this->mover($inicio, $permitirConflito)
                : $this->criar($inicio, $permitirConflito);
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        if ($conflitos !== []) {
            $this->conflitos = array_map(fn (ScheduleConflict $c) => [
                'quando' => $c->quando(),
                'nomes' => $c->nomes(),
            ], $conflitos);

            return;
        }

        $data = $this->data;
        $this->reset();
        $this->dispatch('agenda-mudou', data: $data);
    }

    /** @return list<ScheduleConflict> */
    private function criar(string $inicio, bool $permitirConflito): array
    {
        $resultado = app(ScheduleAppointment::class)->handle(new ScheduleAppointmentCommand(
            userId: (int) auth()->id(),
            learnerId: (int) $this->learnerId,
            startsAt: $inicio,
            durationMinutes: $this->duracao,
            repeatWeeks: $this->repetir,
            bookingNote: $this->recado,
            permitirConflito: $permitirConflito,
        ));

        return $resultado->precisaConfirmacao() ? $resultado->conflitos : [];
    }

    /** @return list<ScheduleConflict> */
    private function mover(string $inicio, bool $permitirConflito): array
    {
        $agendamento = Appointment::where('user_id', auth()->id())->findOrFail($this->appointmentId);
        $this->authorize('update', $agendamento);

        return app(RescheduleAppointment::class)
            ->handle($agendamento, $inicio, $this->duracao, $permitirConflito);
    }

    /** @return array<string, mixed> */
    private function regras(): array
    {
        return [
            'learnerId' => ['required', 'integer', 'exists:learners,id'],
            'data' => ['required', 'date'],
            'hora' => ['required', 'date_format:H:i'],
            'duracao' => ['required', 'integer', 'min:5', 'max:480'],
            'repetir' => [
                'required', 'integer', 'min:1',
                'max:'.ScheduleAppointment::MAXIMO_DE_SEMANAS,
                // Remarcar move UM agendamento: não existe série para repetir.
                $this->remarcando() ? 'in:1' : 'nullable',
            ],
            'recado' => ['nullable', 'string', 'max:255'],
        ];
    }

    // ---------------------------------------------------- cadastro rápido

    public function abrirCadastroRapido(): void
    {
        $this->cadastroRapido = true;
        $this->novoNome = trim($this->busca);
        $this->erro = null;
    }

    public function fecharCadastroRapido(): void
    {
        $this->cadastroRapido = false;
        $this->resetValidation();
    }

    /**
     * Cria o aprendiz sem sair da agenda — chamando o MESMO CreateLearner da
     * tela de cadastro. Nenhuma regra de aprendiz nasce aqui: se nascesse,
     * o sistema teria duas verdades sobre o que é um cadastro válido.
     */
    public function cadastrarRapido(): void
    {
        $dados = $this->validate([
            'novoNome' => ['required', 'string', 'max:160'],
            // Ao menos um dos dois responsáveis — a agenda liga para alguém.
            'novoPai' => ['nullable', 'required_without:novaMae', 'string', 'max:160'],
            'novaMae' => ['nullable', 'required_without:novoPai', 'string', 'max:160'],
            'novoTelefone' => ['required', 'string', 'max:20'],
            // Obrigatória: sem nascimento o aprendiz não pode ser avaliado,
            // porque o laudo imprime a idade na data da aplicação.
            'novoNascimento' => [
                'required', 'date', 'before_or_equal:today',
                'after_or_equal:'.now()->subYears(30)->toDateString(),
            ],
        ], attributes: [
            'novoNome' => 'nome do aprendiz',
            'novoPai' => 'nome do pai',
            'novaMae' => 'nome da mãe',
            'novoTelefone' => 'telefone de contato',
            'novoNascimento' => 'data de nascimento',
        ]);

        $learner = app(CreateLearner::class)->handle(auth()->user(), [
            'name' => $dados['novoNome'],
            'birth_date' => $dados['novoNascimento'],
            'father_name' => $dados['novoPai'] ?: null,
            'mother_name' => $dados['novaMae'] ?: null,
            'contact_phone' => $dados['novoTelefone'],
        ]);

        $this->cadastroRapido = false;
        $this->novoNome = $this->novoPai = $this->novaMae = '';
        $this->novoTelefone = $this->novoNascimento = '';

        unset($this->aprendizes);
        $this->selecionar($learner->id);
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'novoPai.required_without' => 'Informe o nome do pai ou o da mãe.',
            'novaMae.required_without' => 'Informe o nome do pai ou o da mãe.',
            'repetir.in' => 'Remarcar move só este atendimento.',
        ];
    }

    public function render()
    {
        return view('livewire.agenda.appointment-form');
    }
}
