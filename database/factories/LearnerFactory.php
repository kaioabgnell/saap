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
            // Celular brasileiro, não o phoneNumber() do faker en_US: o
            // lembrete do WhatsApp exige um número que normalize para E.164,
            // e "1-555-555-5555" não normaliza. Ver App\Domain\Contact\PhoneNumber.
            'contact_phone' => '(11) 9'.fake()->numerify('####-####'),
        ];
    }
}
