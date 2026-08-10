<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Visitor;
use Carbon\Carbon;

class VisitorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        Visitor::truncate();

        // Sample visitor data
        $visitors = [
            [
                'name' => 'John Smith',
                'company' => 'Tech Solutions Inc.',
                'contact' => '+1 (555) 123-4567',
                'service' => 'Web Development',
                'marketer' => 'Sarah Johnson',
                'date' => Carbon::now()->subDays(5)->format('Y-m-d'),
                'created_at' => Carbon::now()->subDays(5),
                'updated_at' => Carbon::now()->subDays(5),
            ],
            [
                'name' => 'Emily Davis',
                'company' => 'Global Marketing Ltd.',
                'contact' => '+1 (555) 234-5678',
                'service' => 'Digital Marketing',
                'marketer' => 'Michael Brown',
                'date' => Carbon::now()->subDays(3)->format('Y-m-d'),
                'created_at' => Carbon::now()->subDays(3),
                'updated_at' => Carbon::now()->subDays(3),
            ],
            [
                'name' => 'Robert Wilson',
                'company' => 'Data Analytics Co.',
                'contact' => '+1 (555) 345-6789',
                'service' => 'Data Analysis',
                'marketer' => 'Sarah Johnson',
                'date' => Carbon::now()->subDays(1)->format('Y-m-d'),
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1),
            ],
            [
                'name' => 'Lisa Taylor',
                'company' => 'Creative Designs LLC',
                'contact' => '+1 (555) 456-7890',
                'service' => 'Graphic Design',
                'marketer' => 'David Wilson',
                'date' => Carbon::now()->format('Y-m-d'),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'James Anderson',
                'company' => 'Software Innovations',
                'contact' => '+1 (555) 567-8901',
                'service' => 'Mobile App Development',
                'marketer' => 'Michael Brown',
                'date' => Carbon::now()->subDays(7)->format('Y-m-d'),
                'created_at' => Carbon::now()->subDays(7),
                'updated_at' => Carbon::now()->subDays(7),
            ],
            [
                'name' => 'Patricia Martinez',
                'company' => 'E-commerce Solutions',
                'contact' => '+1 (555) 678-9012',
                'service' => 'E-commerce Platform',
                'marketer' => 'Sarah Johnson',
                'date' => Carbon::now()->subDays(2)->format('Y-m-d'),
                'created_at' => Carbon::now()->subDays(2),
                'updated_at' => Carbon::now()->subDays(2),
            ],
            [
                'name' => 'Thomas Robinson',
                'company' => 'Cloud Services Ltd.',
                'contact' => '+1 (555) 789-0123',
                'service' => 'Cloud Hosting',
                'marketer' => 'David Wilson',
                'date' => Carbon::now()->subDays(4)->format('Y-m-d'),
                'created_at' => Carbon::now()->subDays(4),
                'updated_at' => Carbon::now()->subDays(4),
            ],
            [
                'name' => 'Jennifer Clark',
                'company' => 'Social Media Experts',
                'contact' => '+1 (555) 890-1234',
                'service' => 'Social Media Management',
                'marketer' => 'Michael Brown',
                'date' => Carbon::now()->subDays(6)->format('Y-m-d'),
                'created_at' => Carbon::now()->subDays(6),
                'updated_at' => Carbon::now()->subDays(6),
            ],
        ];

        // Insert the data
        Visitor::insert($visitors);
    }
}
