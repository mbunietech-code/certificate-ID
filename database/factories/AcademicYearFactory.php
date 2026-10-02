<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (string) now()->year;

        return [
            'school_id' => School::factory(),
            'name' => $year,
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'is_current' => true,
            'status' => 'active',
        ];
    }
}
