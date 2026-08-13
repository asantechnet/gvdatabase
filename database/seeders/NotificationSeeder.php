<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $userIds = User::query()->where('acc_status', 'active')->pluck('id');

        Notification::factory()->count(3)->create()->each(fn (Notification $notification) => $notification->deliverTo($userIds));

        User::query()->where('acc_status', 'active')->take(3)->get()->each(function (User $user) {
            $notification = Notification::factory()->create();
            $notification->deliverTo([$user->id]);
        });
    }
}
