<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $recipients = User::query()
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'applicant'))
            ->where('acc_status', 'active')
            ->orderBy('id')
            ->get();
        if ($recipients->isEmpty()) {
            return;
        }

        $templates = [
            [
                'type' => 'finance', 'notification_key' => 'finance.receipt',
                'title' => 'New receipt recorded',
                'message' => 'A scholarship service receipt was recorded and matched to its contract line.',
                'go_to_url' => '/income',
            ],
            [
                'type' => 'task', 'notification_key' => 'task.assigned',
                'title' => 'New task assigned',
                'message' => 'A contract delivery task has been assigned to you.',
                'go_to_url' => '/tasks',
            ],
            [
                'type' => 'applicant', 'notification_key' => 'applicant.assigned',
                'title' => 'New applicant case assigned',
                'message' => 'An applicant case was assigned to you for counseling.',
                'go_to_url' => '/applicants',
            ],
        ];

        foreach ($templates as $index => $template) {
            $notification = Notification::updateOrCreate(
                ['notification_key' => $template['notification_key'], 'title' => $template['title']],
                [
                    'type' => $template['type'],
                    'message' => $template['message'],
                    'go_to_url' => $template['go_to_url'],
                    'date_time' => now()->subDays($index + 1),
                    'data' => ['seeded' => true],
                ],
            );

            $notification->deliverTo([
                $recipients[$index % $recipients->count()]->id,
                $recipients[($index + 1) % $recipients->count()]->id,
            ]);
        }
    }
}
