<?php

declare(strict_types=1);

namespace App\Livewire\Atendimento;

use App\Application\Schedule\AddSessionAddendum;
use App\Application\Schedule\CheckOutAppointment;
use App\Application\Schedule\SaveSessionNotes;
use App\Domain\Schedule\AppointmentStatus;
use App\Models\Appointment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * A tela do atendimento: o registro escrito, o check-out e os aditamentos.
 *
 * O registro salva sozinho, como o ItemCard, e pelo mesmo motivo: a psicóloga
 * está com a criança à frente e não vai redigitar o que se perdeu.
 *
 * Depois do check-out a caixa some e dá lugar ao aditamento. Não é uma trava
 * de interface: o caso de uso recusa a escrita de qualquer jeito.
 */
class SessionBoard extends Component
{
    #[Locked]
    public int $appointmentId;

    public string $registro = '';

    public string $aditamento = '';

    public ?string $salvoEm = null;

    public bool $confirmandoCheckout = false;

    public ?string $erro = null;

    public function mount(Appointment $appointment): void
    {
        $this->authorize('view', $appointment);

        $this->appointmentId = $appointment->id;
        $this->registro = (string) $appointment->notes;
    }

    #[Computed]
    public function atendimento(): Appointment
    {
        return Appointment::with('learner', 'addenda')->findOrFail($this->appointmentId);
    }

    /** Salvamento contínuo — a cada pausa na digitação. */
    public function salvar(): void
    {
        $atendimento = $this->atendimento;

        if (! auth()->user()->can('update', $atendimento)) {
            $this->erro = 'Este atendimento está fechado.';

            return;
        }

        try {
            $this->salvoEm = app(SaveSessionNotes::class)->handle($atendimento, $this->registro);
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        $this->erro = null;
        unset($this->atendimento);
    }

    /**
     * Concluir com registro vazio é legítimo — a criança não colaborou, a
     * sessão durou cinco minutos —, mas tem de ser ato consciente.
     */
    public function pedirConfirmacao(): void
    {
        $this->erro = null;

        if (trim($this->registro) === '') {
            $this->confirmandoCheckout = true;

            return;
        }

        $this->checkOut();
    }

    public function checkOut(): void
    {
        $atendimento = $this->atendimento;
        $this->authorize('update', $atendimento);

        try {
            // O texto vai junto: o que foi digitado nos segundos antes do
            // clique tem de entrar no documento que está sendo lacrado.
            app(CheckOutAppointment::class)->handle($atendimento, $this->registro);
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();
            $this->confirmandoCheckout = false;

            return;
        }

        $this->confirmandoCheckout = false;
        unset($this->atendimento);

        $this->redirectRoute('aprendizes.prontuario', $atendimento->learner_id, navigate: true);
    }

    public function acrescentarAditamento(): void
    {
        $atendimento = $this->atendimento;
        $this->authorize('addendum', $atendimento);

        try {
            app(AddSessionAddendum::class)->handle($atendimento, $this->aditamento);
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        $this->erro = null;
        $this->aditamento = '';
        unset($this->atendimento);
    }

    public function render()
    {
        return view('livewire.atendimento.session-board', [
            'emAndamento' => $this->atendimento->status === AppointmentStatus::InProgress,
        ]);
    }
}
