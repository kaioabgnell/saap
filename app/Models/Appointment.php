<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Contact\PhoneNumber;
use App\Domain\Schedule\AppointmentStatus;
use App\Domain\Schedule\ReminderMessage;
use App\Domain\Schedule\TimeSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Um horário marcado — e, depois do check-in, o atendimento que aconteceu.
 *
 * São a mesma linha de propósito: ver F11-agenda-e-atendimentos.md.
 */
class Appointment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'learner_id', 'starts_at', 'ends_at', 'status',
        'checked_in_at', 'checked_out_at', 'booking_note', 'notes',
        'notes_locked_at', 'cancel_reason', 'reminder_opened_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'notes_locked_at' => 'datetime',
            'reminder_opened_at' => 'datetime',
            'status' => AppointmentStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function addenda(): HasMany
    {
        return $this->hasMany(AppointmentAddendum::class)->orderBy('created_at');
    }

    /** A faixa de tempo deste agendamento, para a regra de conflito. */
    public function faixa(): TimeSlot
    {
        return new TimeSlot($this->starts_at->toDateTimeImmutable(), $this->ends_at->toDateTimeImmutable());
    }

    /** Duração prevista, em minutos. */
    public function duracaoPrevista(): int
    {
        return $this->faixa()->duration();
    }

    /**
     * Duração real, do check-in ao check-out. Nula enquanto o atendimento não
     * fechou — e é isso que o prontuário mostra, não a prevista.
     */
    public function duracaoReal(): ?int
    {
        if ($this->checked_in_at === null || $this->checked_out_at === null) {
            return null;
        }

        return (int) $this->checked_in_at->diffInMinutes($this->checked_out_at);
    }

    /** O registro escrito está fechado? Depois disso, só aditamento. */
    public function registroFechado(): bool
    {
        return $this->notes_locked_at !== null;
    }

    public function temRegistro(): bool
    {
        return trim((string) $this->notes) !== '';
    }

    /**
     * Apagar é permitido só enquanto o agendamento não virou prontuário.
     * Depois disso o caminho é cancelar, que preserva a linha e o motivo.
     */
    public function podeSerApagado(): bool
    {
        return $this->status === AppointmentStatus::Scheduled && ! $this->temRegistro();
    }

    /** Telefone dos responsáveis normalizado, ou null se não der para normalizar. */
    public function telefoneDoLembrete(): ?PhoneNumber
    {
        return PhoneNumber::doBrasil($this->learner?->contact_phone);
    }

    /**
     * O link do lembrete: wa.me com o número e a mensagem prontos. Null
     * quando não há telefone utilizável — a tela desabilita o botão e diz
     * por quê, em vez de abrir o WhatsApp num número quebrado.
     */
    public function linkDoLembrete(): ?string
    {
        $telefone = $this->telefoneDoLembrete();

        if ($telefone === null) {
            return null;
        }

        return 'https://wa.me/'.$telefone->paraWhatsApp()
            .'?text='.rawurlencode($this->mensagemDoLembrete());
    }

    public function mensagemDoLembrete(): string
    {
        return ReminderMessage::montar(
            $this->learner->name,
            $this->starts_at->toDateTimeImmutable(),
            $this->user->clinic_name,
            $this->user->name,
        );
    }

    /** Agendamentos que ocupam horário — a base da checagem de conflito. */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->whereIn('status', AppointmentStatus::ativos());
    }

    /** Tudo que começa dentro da janela da visão da agenda. */
    public function scopeNaJanela(Builder $query, \DateTimeInterface $de, \DateTimeInterface $ate): Builder
    {
        return $query->where('starts_at', '>=', $de)->where('starts_at', '<=', $ate);
    }
}
