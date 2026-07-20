<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'     => fake()->company(),
            'phone'    => fake()->numerify('9#########'),
            'email'    => fake()->safeEmail(),
            'type'     => 'dealer',
            'is_active' => true,
        ];
    }
}
