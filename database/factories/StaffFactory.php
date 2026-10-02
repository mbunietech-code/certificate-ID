<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);

        return [
            'school_id' => School::factory(),
            'employee_number' => 'EMP-'.fake()->unique()->numerify('####'),
            'first_name' => fake()->firstName($gender),
            'last_name' => fake()->lastName(),
            'gender' => $gender,
            'job_title' => fake()->randomElement(['Teacher', 'Senior Teacher', 'Head of Department', 'Accountant', 'Librarian']),
            'department' => fake()->randomElement(['Science', 'Arts', 'Languages', 'Administration']),
            'phone' => '+255 7'.fake()->numerify('## ### ###'),
            'email' => fake()->unique()->safeEmail(),
            'employment_status' => 'active',
        ];
    }
}
