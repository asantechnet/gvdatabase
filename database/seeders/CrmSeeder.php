<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\CrmInteraction;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Database\Seeder;

class CrmSeeder extends Seeder
{
    public function run(): void
    {
        $fallbackOwner = User::query()->orderBy('id')->value('id');

        foreach (Visitor::query()->with('consultant')->orderBy('id')->get() as $visitor) {
            CrmInteraction::withTrashed()->updateOrCreate(
                ['contactable_type' => $visitor->getMorphClass(), 'contactable_id' => $visitor->id, 'title' => 'Visitor intake follow-up'],
                [
                    'created_by' => $visitor->counselor?->user_id ?? $fallbackOwner,
                    'channel' => 'message',
                    'notes' => 'Sent service information for '.$visitor->service.' and booked a consultation.',
                    'contacted_at' => now()->subDay(),
                    'follow_up_at' => now()->addDays(2),
                    'deleted_at' => null,
                ],
            );
        }

        foreach (Applicant::query()->with('responsibleEmployee')->orderBy('id')->get() as $applicant) {
            CrmInteraction::withTrashed()->updateOrCreate(
                ['contactable_type' => $applicant->getMorphClass(), 'contactable_id' => $applicant->id, 'title' => 'Case progress check-in'],
                [
                    'created_by' => $applicant->responsibleEmployee?->user_id ?? $fallbackOwner,
                    'channel' => 'call',
                    'notes' => 'Confirmed the current stage and outstanding documents.',
                    'contacted_at' => now()->subDays(2),
                    'follow_up_at' => now()->addDays(5),
                    'deleted_at' => null,
                ],
            );
        }
    }
}
