<?php

namespace Database\Seeders;

use App\Models\AcquisitionRoute;
use App\Models\CrmInteraction;
use App\Models\EducationLevel;
use App\Models\Employee;
use App\Models\EnglishLevel;
use App\Models\Province;
use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VisitorSeeder extends Seeder
{
    public function run(): void
    {
        // Delete dependants first and force-delete visitors so this seeder
        // remains safe and repeatable on its own.
        DB::transaction(function (): void {
            CrmInteraction::query()
                ->where('contactable_type', Visitor::class)
                ->forceDelete();
            Visitor::withTrashed()->forceDelete();
        });

        $consultants = Employee::query()
            ->whereHas('position', fn ($query) => $query->whereIn('system_key', ['counselor', 'senior_counselor']))
            ->pluck('id')
            ->values();
        if ($consultants->isEmpty()) {
            return;
        }

        // Each visitor is enquiring about one of the firm's real services and
        // is handled by a consultant.
        $visitors = [
            ['name' => 'Hamid Noorzai', 'contact' => '+93 700 300 001', 'service' => 'Scholarship', 'days_ago' => 7, 'province' => 'Kabul'],
            ['name' => 'Laila Sultani', 'contact' => '+93 700 300 002', 'service' => 'Private', 'days_ago' => 6, 'province' => 'Herat'],
            ['name' => 'Karim Zadran', 'contact' => '+93 700 300 003', 'service' => 'Test offer', 'days_ago' => 4, 'province' => 'Kandahar'],
            ['name' => 'Mariam Yousufi', 'contact' => '+93 700 300 004', 'service' => 'Academy', 'days_ago' => 3, 'province' => 'Balkh'],
            ['name' => 'Samiullah Baqi', 'contact' => '+93 700 300 005', 'service' => 'Scholarship', 'days_ago' => 2, 'province' => 'Kabul'],
            ['name' => 'Rana Alizai', 'contact' => '+93 700 300 006', 'service' => 'Private', 'days_ago' => 1, 'province' => 'Nangarhar'],
        ];

        $levels = EducationLevel::query()->where('is_active', true)->orderBy('sort_order')->pluck('id')->values();
        $english = EnglishLevel::query()->where('is_active', true)->orderBy('sort_order')->pluck('id')->values();
        $routes = AcquisitionRoute::query()->where('is_active', true)->orderBy('sort_order')->pluck('id')->values();

        foreach ($visitors as $index => $data) {
            $at = Carbon::now()->subDays($data['days_ago']);
            Visitor::create([
                'name' => $data['name'],
                'contact' => $data['contact'],
                'service' => $data['service'],
                'consultant_employee_id' => $consultants[$index % $consultants->count()],
                'education_level_id' => $levels[$index % max($levels->count(), 1)] ?? null,
                'age' => 18 + ($index * 2),
                'grade_score' => 65 + ($index * 5),
                'province_id' => Province::query()->where('name', $data['province'])->value('id'),
                'english_level_id' => $english[$index % max($english->count(), 1)] ?? null,
                'acquisition_route_id' => $routes[$index % max($routes->count(), 1)] ?? null,
                'consultant_review' => $index % 2 === 0 ? 'Initial counseling review completed.' : null,
                'date' => $at->format('Y-m-d'),
                'notes' => 'Walk-in enquiry about '.$data['service'].'.',
                'converted_at' => $index === 0 ? Carbon::now()->subDays($data['days_ago'] - 1) : null,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }
}
