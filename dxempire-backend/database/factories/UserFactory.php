<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        return [
            'name'      => fake()->name(),
            'phone'     => '9' . str_pad($seq, 9, (string) fake()->randomDigitNotNull(), STR_PAD_LEFT),
            'email'     => fake()->unique()->safeEmail(),
            'is_active' => true,
        ];
    }
}
