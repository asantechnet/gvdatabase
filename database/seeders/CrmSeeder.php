<?php

namespace Database\Seeders;

use App\Models\CrmInteraction;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\Marketer;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Database\Seeder;

class CrmSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::query()->orderBy('id')->get();
        $owner = User::query()->orderBy('id')->first();

        $marketerNames = ['Sarah Johnson', 'Michael Brown', 'David Wilson'];
        foreach ($marketerNames as $name) {
            Marketer::withTrashed()->updateOrCreate(
                ['name' => $name],
                [
                    'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
                    'phone' => '+93 700 000 '.str_pad((string) (array_search($name, $marketerNames, true) + 1), 3, '0', STR_PAD_LEFT),
                    'status' => 'active',
                    'notes' => 'Demo CRM marketer',
                    'deleted_at' => null,
                ],
            );
        }

        $visitors = Visitor::query()->orderBy('id')->get();
        $leadData = [
            [
                'name' => 'Amina Rahimi',
                'company' => 'Rahimi Family',
                'contact' => '+93 701 111 111',
                'email' => 'amina.rahimi@example.com',
                'service_interest' => 'Student Visa Application Support',
                'stage' => 'qualified',
                'probability' => 65,
                'estimated_value' => 20000,
                'notes' => 'Interested in a master\'s programme in Canada.',
            ],
            [
                'name' => 'Omid Noori',
                'company' => 'Noori Education Group',
                'contact' => '+93 702 222 222',
                'email' => 'omid.noori@example.com',
                'service_interest' => 'University Application Support',
                'stage' => 'contacted',
                'probability' => 35,
                'estimated_value' => 15000,
                'notes' => 'Follow up after the next intake information session.',
            ],
        ];

        foreach ($leadData as $index => $data) {
            $lead = Lead::withTrashed()->updateOrCreate(
                ['email' => $data['email']],
                [...$data, 'owner_id' => $owner?->id, 'marketer_id' => $employees->get($index)?->id, 'deleted_at' => null],
            );

            CrmInteraction::withTrashed()->updateOrCreate(
                ['contactable_type' => $lead->getMorphClass(), 'contactable_id' => $lead->id, 'title' => 'Initial qualification call'],
                [
                    'created_by' => $owner?->id,
                    'channel' => 'call',
                    'notes' => 'Discussed requirements, timeline, and next steps.',
                    'contacted_at' => now()->subDays($index + 1),
                    'follow_up_at' => now()->addDays(3 + $index),
                    'deleted_at' => null,
                ],
            );
        }

        foreach ($visitors->take(2) as $visitor) {
            CrmInteraction::withTrashed()->updateOrCreate(
                ['contactable_type' => $visitor->getMorphClass(), 'contactable_id' => $visitor->id, 'title' => 'Visitor follow-up'],
                [
                    'created_by' => $owner?->id,
                    'channel' => 'message',
                    'notes' => 'Sent an introduction and service information.',
                    'contacted_at' => now()->subDay(),
                    'follow_up_at' => now()->addDays(2),
                    'deleted_at' => null,
                ],
            );
        }
    }
}
