<?php

namespace App\Services;

use App\Models\ContractTemplateVersion;
use App\Models\Notification;
use App\Models\User;

class ContractTemplateVersionNotificationService
{
    public function send(string $event, ContractTemplateVersion $version): void
    {
        $version->loadMissing(['template', 'country', 'language']);
        $recipients = User::query()->where('acc_status', 'active')->get()
            ->filter(fn (User $user) => $user->hasPermission('view_contract_templates'))
            ->pluck('id');
        if ($recipients->isEmpty()) return;

        $key = "contract_template_version.{$event}.{$version->id}";
        $notification = Notification::firstOrCreate([
            'notification_key' => $key,
        ], [
            'type' => 'contract_template_version',
            'data' => [
                'translation_key' => "contract_template_version.{$event}",
                'template' => $version->template?->name,
                'version' => $version->name,
                'version_number' => $version->version_number,
                'country' => $version->country?->name,
                'language' => $version->language?->name,
            ],
            'date_time' => now(),
            'go_to_url' => "/contract-templates/{$version->contract_template_id}",
        ]);
        $notification->deliverTo($recipients);
    }
}
