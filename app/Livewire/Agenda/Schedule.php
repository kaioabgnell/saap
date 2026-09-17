<?php

declare(strict_types=1);

namespace App\Livewire\Agenda;

use App\Application\Schedule\CancelAppointment;
use App\Application\Schedule\CheckInAppointment;
use App\Application\Schedule\MarkNoShow;
use App\Application\Schedule\MarkReminderOpened;
use App\Domain\Schedule\AppointmentStatus;
use App\Domain\Schedule\CalendarLabels;
use App\Domain\Schedule\CalendarView;
use App\Models\Appointment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Component;
use RuntimeException;

/**
 * A agenda — a tela mais mexida do sistema.
 *
 * Abre no mês, que é a visão de chegada. Dia e semana ficam a um clique, e a
 * escolha persiste na sessão: quem trabalha no dia não quer reencontrar o mês
 * a cada volta.
 *
 * As três visões consultam a MESMA janela de agendamentos, só mudando o
 * recorte. É por isso que trocar de visão não vai ao banco de novo com uma
 * consulta diferente — e é o que mantém a tela leve no iPad.
 */
class Schedule extends Component
{
    /** Persistida: a visão sobrevive à navegação e à volta no dia seguinte. */
    #[Session(key: 'saap.agenda-visao')]
    public string $visao = CalendarView::Mes->value;

    /** O dia em que a visão está ancorada, em Y-m-d. */
    public string $ancora = '';

    /** Ficha aberta no painel de detalhe, se houver. */
    public ?int $detalheId = null;

    public bool $cancelando = false;

    public string $motivoDoCancelamento = '';

    public ?string $erro = null;

    /**
     * Quantas fichas cabem numa célula do mês antes do "+N mais".
     *
     * Duas, e não três, porque cada ficha tem os 44px de alvo de toque que
     * todo controle do sistema tem. Uma grade de seis linhas com três fichas
     * de 44px passaria de mil pixels de altura — o mês deixaria de caber na
     * tela justamente na visão que existe para dar a visão geral.
     */
    public const FICHAS_POR_DIA = 2;

    public function mount(): void
    {
        $this->authorize('viewAny', Appointment::class);

        $this->ancora = now()->toDateString();
    }

    // ---------------------------------------------------------------- visão

    #[Computed]
    public function visaoAtual(): CalendarView
    {
        return CalendarView::tryFrom($this->visao) ?? CalendarView::Mes;
    }

    #[Computed]
    public function dia(): Carbon
    {
        return Carbon::parse($this->ancora)->startOfDay();
    }

    /**
     * A janela de dias que a visão cobre.
     *
     * No mês são sempre 42 dias — 6 linhas de 7 —, e não os 30 do calendário:
     * a grade precisa começar no domingo anterior e terminar no sábado
     * seguinte, e é por isso que os dias dos meses vizinhos aparecem.
     */
    #[Computed]
    public function janela(): array
    {
        $dia = $this->dia;

        return match ($this->visaoAtual) {
            CalendarView::Mes => [
                $de = $dia->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY),
                $de->copy()->addDays(41)->endOfDay(),
            ],
            CalendarView::Semana => [
                $de = $dia->copy()->startOfWeek(Carbon::SUNDAY),
                $de->copy()->addDays(6)->endOfDay(),
            ],
            CalendarView::Dia => [$dia->copy(), $dia->copy()->endOfDay()],
        };
    }

    #[Computed]
    public function titulo(): string
    {
        [$de, $ate] = $this->janela;

        return match ($this->visaoAtual) {
            CalendarView::Mes => CalendarLabels::mesEAno($this->dia),
            CalendarView::Semana => CalendarLabels::intervalo($de, $ate),
            CalendarView::Dia => CalendarLabels::porExtenso($this->dia),
        };
    }

    // ------------------------------------------------------------- consulta

    /**
     * Os agendamentos da janela, agrupados por dia.
     *
     * Cancelado some do calendário — ele liberou o horário, e mantê-lo na
     * grade deixaria a agenda parecendo mais ocupada do que está. A falta
     * continua aparecendo: o horário foi ocupado de fato, a ausência é o
     * próprio fato do dia. Os dois seguem visíveis na linha do tempo do
     * prontuário (`ProntuarioController`), que não filtra por status —
     * cancelar não apaga histórico, só desocupa a agenda.
     */
    #[Computed]
    public function porDia(): Collection
    {
        [$de, $ate] = $this->janela;

        return Appointment::query()
            ->with('learner:id,name,contact_phone')
            ->where('user_id', auth()->id())
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->naJanela($de, $ate)
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (Appointment $a) => $a->starts_at->toDateString());
    }

    /** As 6 linhas de 7 dias da grade do mês. */
    #[Computed]
    public function semanas(): array
    {
        [$de] = $this->janela;

        $semanas = [];

        for ($linha = 0; $linha < 6; $linha++) {
            $dias = [];

            for ($coluna = 0; $coluna < 7; $coluna++) {
                $dias[] = $de->copy()->addDays($linha * 7 + $coluna);
            }

            $semanas[] = $dias;
        }

        return $semanas;
    }

    /** Os sete dias da visão de semana. */
    #[Computed]
    public function diasDaSemana(): array
    {
        [$de] = $this->janela;

        return array_map(fn (int $i) => $de->copy()->addDays($i), range(0, 6));
    }

    /**
     * A trilha de horas das visões de dia e semana.
     *
     * Vai das 7h às 20h por padrão, mas se abre para caber um atendimento
     * fora desse intervalo — a agenda tem de mostrar o que existe, não o que
     * caberia num expediente idealizado.
     */
    #[Computed]
    public function horas(): array
    {
        $inicio = 7;
        $fim = 20;

        foreach ($this->porDia->flatten() as $a) {
            $inicio = min($inicio, (int) $a->starts_at->format('G'));
            $fim = max($fim, (int) $a->ends_at->copy()->subMinute()->format('G'));
        }

        return range($inicio, $fim);
    }

    /** @return Collection<int, Appointment> */
    public function agendamentosDe(Carbon $dia): Collection
    {
        return $this->porDia->get($dia->toDateString(), collect());
    }

    #[Computed]
    public function detalhe(): ?Appointment
    {
        if ($this->detalheId === null) {
            return null;
        }

        return Appointment::with('learner', 'user')
            ->where('user_id', auth()->id())
            ->find($this->detalheId);
    }

    // ----------------------------------------------------------- navegação

    public function mudarVisao(string $visao): void
    {
        $this->visao = (CalendarView::tryFrom($visao) ?? CalendarView::Mes)->value;
        $this->limparDerivados();
    }

    public function anterior(): void
    {
        $this->andar(-1);
    }

    public function proximo(): void
    {
        $this->andar(1);
    }

    public function hoje(): void
    {
        $this->ancora = now()->toDateString();
        $this->limparDerivados();
    }

    /** Anda na unidade da visão atual: mês a mês, semana a semana, dia a dia. */
    private function andar(int $passo): void
    {
        $unidade = $this->visaoAtual->unidade();

        // addMonthsNoOverflow: 31/03 recuando um mês precisa dar 28/02, e não
        // 03/03. O título do mês seguiria certo, mas a grade pularia fevereiro.
        $this->ancora = match ($unidade) {
            'month' => $this->dia->copy()->addMonthsNoOverflow($passo)->toDateString(),
            'week' => $this->dia->copy()->addWeeks($passo)->toDateString(),
            default => $this->dia->copy()->addDays($passo)->toDateString(),
        };

        $this->limparDerivados();
    }

    /** Clicar no número do dia, no mês ou na semana, abre aquele dia. */
    public function abrirDia(string $data): void
    {
        $this->ancora = Carbon::parse($data)->toDateString();
        $this->visao = CalendarView::Dia->value;
        $this->limparDerivados();
    }

    // -------------------------------------------------------------- fichas

    public function abrirDetalhe(int $id): void
    {
        $this->detalheId = $id;
        $this->cancelando = false;
        $this->motivoDoCancelamento = '';
        $this->erro = null;
        unset($this->detalhe);
    }

    public function fecharDetalhe(): void
    {
        $this->detalheId = null;
        $this->cancelando = false;
        $this->erro = null;
        unset($this->detalhe);
    }

    /**
     * Novo agendamento a partir de um dia (mês) ou de um horário (dia/semana).
     *
     * Sem `$data` — o botão do cabeçalho —, o padrão é HOJE, não a âncora da
     * navegação: depois de criar um agendamento a âncora segue a data
     * recém-criada (ver `aoMudarAgenda`), e o botão genérico não pode herdar
     * a data do último agendamento como se fosse o dia que se está vendo.
     */
    public function novo(?string $data = null, ?int $hora = null): void
    {
        $this->dispatch('abrir-agendamento',
            data: $data ?? now()->toDateString(),
            hora: $hora === null ? null : sprintf('%02d:00', $hora),
        );
    }

    public function remarcar(int $id): void
    {
        $this->dispatch('abrir-remarcacao', appointmentId: $id);
        $this->fecharDetalhe();
    }

    public function checkIn(int $id): void
    {
        $agendamento = $this->doUsuario($id);
        $this->authorize('update', $agendamento);

        try {
            app(CheckInAppointment::class)->handle($agendamento);
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        $this->redirectRoute('atendimentos.show', $agendamento, navigate: true);
    }

    public function marcarFalta(int $id): void
    {
        $agendamento = $this->doUsuario($id);
        $this->authorize('update', $agendamento);

        try {
            app(MarkNoShow::class)->handle($agendamento);
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        $this->fecharDetalhe();
        $this->limparDerivados();
    }

    public function pedirMotivo(): void
    {
        $this->cancelando = true;
        $this->erro = null;
    }

    public function cancelar(int $id): void
    {
        $agendamento = $this->doUsuario($id);
        $this->authorize('update', $agendamento);

        try {
            app(CancelAppointment::class)->handle($agendamento, $this->motivoDoCancelamento);
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        $this->motivoDoCancelamento = '';
        $this->fecharDetalhe();
        $this->limparDerivados();
    }

    /**
     * O lembrete: carimba que a conversa foi aberta e devolve o link.
     *
     * Quem abre a aba é o navegador, não o servidor — por isso o componente
     * emite o link em vez de "enviar" coisa alguma.
     */
    public function abrirLembrete(int $id): void
    {
        $agendamento = $this->doUsuario($id);
        $this->authorize('view', $agendamento);

        $link = $agendamento->linkDoLembrete();

        if ($link === null) {
            $this->erro = 'Sem telefone utilizável no cadastro do aprendiz.';

            return;
        }

        app(MarkReminderOpened::class)->handle($agendamento);

        $this->dispatch('abrir-lembrete', url: $link);
        $this->limparDerivados();
        unset($this->detalhe);
    }

    #[On('agenda-mudou')]
    public function aoMudarAgenda(?string $data = null): void
    {
        if ($data !== null) {
            $this->ancora = Carbon::parse($data)->toDateString();
        }

        $this->fecharDetalhe();
        $this->limparDerivados();
    }

    private function doUsuario(int $id): Appointment
    {
        return Appointment::where('user_id', auth()->id())->findOrFail($id);
    }

    private function limparDerivados(): void
    {
        unset($this->janela, $this->porDia, $this->semanas, $this->diasDaSemana,
            $this->horas, $this->titulo, $this->dia, $this->visaoAtual, $this->detalhe);
    }

    public function render()
    {
        return view('livewire.agenda.schedule', [
            'estados' => AppointmentStatus::class,
        ]);
    }
}
