<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Position;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $positions = [
            [
                'title' => 'Junior Engineer',
                'salary' => 50000,
                'status' => 'active',
                'description' => 'Entry-level engineering position'
            ],
            [
                'title' => 'Senior Engineer',
                'salary' => 80000,
                'status' => 'active',
                'description' => 'Experienced engineering position'
            ],
            [
                'title' => 'Lead Consultant',
                'salary' => 100000,
                'status' => 'active',
                'description' => 'Technical leadership position'
            ],
            [
                'title' => 'Manager',
                'salary' => 120000,
                'status' => 'active',
                'description' => 'Management position'
            ],
            [
                'title' => 'Director',
                'salary' => 150000,
                'status' => 'active',
                'description' => 'Executive leadership position'
            ],
        ];

        foreach ($positions as $position) {
            Position::firstOrCreate([
                'title' => $position['title']
            ], $position);
        }
    }
}
