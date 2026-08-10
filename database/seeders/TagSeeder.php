<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tag;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['name' => 'Bug', 'color' => 'error'],
            ['name' => 'Feature', 'color' => 'primary'],
            ['name' => 'Enhancement', 'color' => 'info'],
            ['name' => 'Documentation', 'color' => 'warning'],
            ['name' => 'Research', 'color' => 'secondary'],
            ['name' => 'Testing', 'color' => 'success'],
            ['name' => 'UI/UX', 'color' => 'primary'],
            ['name' => 'Backend', 'color' => 'error'],
        ];

        foreach ($tags as $tag) {
            Tag::firstOrCreate($tag);
        }
    }
}
