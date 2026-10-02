<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);

        return [
            'school_id' => School::factory(),
            'admission_number' => 'ADM/'.fake()->unique()->numerify('#####'),
            'first_name' => fake()->firstName($gender),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => $gender,
            'date_of_birth' => fake()->dateTimeBetween('-19 years', '-13 years')->format('Y-m-d'),
            'nationality' => 'Tanzanian',
            'level' => 'O-Level',
            'class_name' => fake()->randomElement(['Form I', 'Form II', 'Form III', 'Form IV']),
            'stream' => fake()->randomElement(['A', 'B', 'C']),
            'parent_name' => fake()->name(),
            'parent_phone' => '+255 7'.fake()->numerify('## ### ###'),
            'status' => 'active',
        ];
    }
}
