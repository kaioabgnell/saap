<?php

namespace Database\Factories;

use App\Models\Learner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Learner>
 */
class LearnerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'birth_date' => fake()->dateTimeBetween('-12 years', '-2 years')->format('Y-m-d'),
            'father_name' => fake()->name('male'),
            'mother_name' => fake()->name('female'),
            'contact_phone' => fake()->phoneNumber(),
        ];
    }
}
