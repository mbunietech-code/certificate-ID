<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->lastName().' Secondary School';

        return [
            'school_code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => $name,
            'short_name' => strtok($name, ' ').' SS',
            'address' => 'P.O. Box '.fake()->numberBetween(100, 9999).', Dar es Salaam',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'phone' => '+255 7'.fake()->numerify('## ### ###'),
            'email' => fake()->unique()->safeEmail(),
            'principal_name' => fake()->name(),
            'primary_color' => '#1e3a8a',
            'secondary_color' => '#b45309',
            'student_id_format' => '{CODE}/{YEAR}/{SEQ:4}',
            'staff_id_format' => '{CODE}/STF/{YEAR}/{SEQ:4}',
            'certificate_number_format' => '{CODE}/CERT/{YEAR}/{SEQ:5}',
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
