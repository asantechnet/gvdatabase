<?php

namespace Database\Seeders;

use App\Models\CrmInteraction;
use App\Models\Employee;
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

        $counselors = Employee::query()
            ->whereHas('position', fn ($query) => $query->whereIn('system_key', ['counselor', 'senior_counselor']))
            ->pluck('id')
            ->values();
        if ($counselors->isEmpty()) {
            return;
        }

        // Each visitor is enquiring about one of the firm's real services and
        // is handled by a counselor.
        $visitors = [
            ['name' => 'Hamid Noorzai', 'contact' => '+93 700 300 001', 'service' => 'Scholarship', 'days_ago' => 7],
            ['name' => 'Laila Sultani', 'contact' => '+93 700 300 002', 'service' => 'Private', 'days_ago' => 6],
            ['name' => 'Karim Zadran', 'contact' => '+93 700 300 003', 'service' => 'Test Offer', 'days_ago' => 4],
            ['name' => 'Mariam Yousufi', 'contact' => '+93 700 300 004', 'service' => 'Academy', 'days_ago' => 3],
            ['name' => 'Samiullah Baqi', 'contact' => '+93 700 300 005', 'service' => 'Scholarship', 'days_ago' => 2],
            ['name' => 'Rana Alizai', 'contact' => '+93 700 300 006', 'service' => 'Private', 'days_ago' => 1],
        ];

        foreach ($visitors as $index => $data) {
            $at = Carbon::now()->subDays($data['days_ago']);
            Visitor::create([
                'name' => $data['name'],
                'contact' => $data['contact'],
                'service' => $data['service'],
                'counselor_employee_id' => $counselors[$index % $counselors->count()],
                'date' => $at->format('Y-m-d'),
                'notes' => 'Walk-in enquiry about '.$data['service'].'.',
                'converted_at' => $index === 0 ? Carbon::now()->subDays($data['days_ago'] - 1) : null,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }
}
