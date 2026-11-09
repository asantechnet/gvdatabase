<?php

namespace Database\Factories;

use App\Models\AcademyClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademyEnrollmentFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'student_id' => Student::factory(),
            'applicant_id' => null,
            'contract_id' => null,
            'applicant_contract_id' => null,
            'class_id' => AcademyClass::factory(),
            'program_id' => null,
            'started_on' => $start->format('Y-m-d'),
            'ends_on' => (clone $start)->modify('+6 months')->format('Y-m-d'),
            'status' => 'active',
            'completed_on' => null,
            'notes' => null,
        ];
    }
}
