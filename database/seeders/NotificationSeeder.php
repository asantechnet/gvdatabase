<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Notification;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        Notification::factory()->count(3)->create();
        Employee::query()->take(3)->get()->each(fn (Employee $employee) => Notification::factory()->forEmployee($employee)->create());
    }
}
