<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['name' => 'Counseling', 'color' => 'primary'],
            ['name' => 'Admission', 'color' => 'info'],
            ['name' => 'Documents', 'color' => 'warning'],
            ['name' => 'Finance', 'color' => 'success'],
            ['name' => 'Visa', 'color' => 'secondary'],
            ['name' => 'Academy', 'color' => 'primary'],
            ['name' => 'Follow-up', 'color' => 'secondary'],
            ['name' => 'Operations', 'color' => 'info'],
            ['name' => 'Urgent', 'color' => 'error'],
        ];

        foreach ($tags as $tag) {
            Tag::firstOrCreate(['name' => $tag['name']], ['color' => $tag['color']]);
        }
    }
}
