<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Schedule\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Learner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $inicio = now()->addDays(fake()->numberBetween(1, 20))->setTime(fake()->numberBetween(8, 17), 0);

        return [
            'user_id' => User::factory(),
            'learner_id' => Learner::factory(),
            'starts_at' => $inicio,
            'ends_at' => $inicio->copy()->addMinutes(50),
            'status' => AppointmentStatus::Scheduled->value,
        ];
    }

    /** Agendamento em horário fixo — o que os testes de conflito precisam. */
    public function em(string $quando, int $minutos = 60): self
    {
        $inicio = Carbon::parse($quando);

        return $this->state(fn () => [
            'starts_at' => $inicio,
            'ends_at' => $inicio->copy()->addMinutes($minutos),
        ]);
    }

    public function emAtendimento(): self
    {
        return $this->state(fn () => [
            'status' => AppointmentStatus::InProgress->value,
            'checked_in_at' => now(),
        ]);
    }

    public function concluido(?string $registro = 'Sessão tranquila.'): self
    {
        return $this->state(fn () => [
            'status' => AppointmentStatus::Completed->value,
            'checked_in_at' => now()->subMinutes(50),
            'checked_out_at' => now(),
            'notes' => $registro,
            'notes_locked_at' => now(),
        ]);
    }

    public function cancelado(): self
    {
        return $this->state(fn () => [
            'status' => AppointmentStatus::Cancelled->value,
            'cancel_reason' => 'Responsável remarcou.',
        ]);
    }

    public function falta(): self
    {
        return $this->state(fn () => ['status' => AppointmentStatus::NoShow->value]);
    }
}
