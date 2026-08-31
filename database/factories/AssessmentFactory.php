<?php

namespace Database\Factories;

use App\Domain\Assessment\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Learner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'learner_id' => Learner::factory(),
            'user_id' => User::factory(),
            'instrument' => 'vbmapp',
            'status' => AssessmentStatus::NotStarted->value,
            'applied_on' => now()->toDateString(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(['status' => AssessmentStatus::InProgress->value, 'started_at' => now()]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => AssessmentStatus::Completed->value,
            'started_at' => now()->subDays(10),
            'completed_at' => now(),
            'locked_at' => now(),
        ]);
    }
}
