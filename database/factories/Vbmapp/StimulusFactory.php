<?php

namespace Database\Factories\Vbmapp;

use App\Models\Vbmapp\Stimulus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stimulus>
 */
class StimulusFactory extends Factory
{
    protected $model = Stimulus::class;

    public function definition(): array
    {
        return [
            // O marco vem do catálogo semeado, não de factory: sempre use
            // ->for($item, 'item') ao criar um estímulo.
            'item_id' => null,
            'label' => fake()->word(),
            'image_path' => 'vbmapp/estimulos/nivel-1/'.fake()->uuid().'.png',
            'source_page' => fake()->numberBetween(3, 28),
            'position' => 1,
        ];
    }
}
