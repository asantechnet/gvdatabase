<?php

namespace Database\Seeders;

use App\Models\WhatsappTemplate;
use App\Support\WhatsappAudience;
use Illuminate\Database\Seeder;

/**
 * Starter WhatsApp templates, one or two per audience.
 *
 * They exist so the quick-contact menus have something to send on a fresh
 * install and so the editor opens with working examples of every supported
 * WhatsApp marker. Every placeholder used here is declared in
 * App\Services\Whatsapp\WhatsappPlaceholderRegistry.
 */
class WhatsappTemplateSeeder extends Seeder
{
    /** @var list<array{audience: string, title: string, labels: list<string>, message: string, sort_order: int}> */
    private const TEMPLATES = [
        [
            'audience' => WhatsappAudience::VISITOR,
            'title' => 'Visitor follow-up',
            'labels' => ['follow-up', 'visitor'],
            'message' => "Hello *{{name}}*, thanks for visiting us about {{service}}.\n\nYour counselor {{counselor}} will follow up with you shortly. Reply here if you have any questions.",
            'sort_order' => 1,
        ],
        [
            'audience' => WhatsappAudience::VISITOR,
            'title' => 'Visit reminder',
            'labels' => ['reminder'],
            'message' => 'Hi {{name}}, this is a reminder about your visit on {{date}}. See you soon!',
            'sort_order' => 2,
        ],
        [
            'audience' => WhatsappAudience::APPLICANT,
            'title' => 'Application status update',
            'labels' => ['status', 'applicant'],
            'message' => "Dear *{{name}}*, here is an update on your {{service}} application.\n\nCurrent stage: _{{stage}}_\nYour advisor: {{responsible_employee}}\n\nWe will contact you as soon as the next step is ready.",
            'sort_order' => 1,
        ],
        [
            'audience' => WhatsappAudience::APPLICANT,
            'title' => 'Document request',
            'labels' => ['documents'],
            'message' => 'Hello {{name}}, to continue your application we still need some documents. Please send them here or bring them to our office.',
            'sort_order' => 2,
        ],
        [
            'audience' => WhatsappAudience::EMPLOYEE,
            'title' => 'Welcome message',
            'labels' => ['onboarding', 'employee'],
            'message' => "Welcome aboard, *{{name}}*!\n\nYou are joining us as {{position}} in {{department}} from {{join_date}}. We are glad to have you on the team.",
            'sort_order' => 1,
        ],
        [
            'audience' => WhatsappAudience::EMPLOYEE,
            'title' => 'Shift reminder',
            'labels' => ['reminder'],
            'message' => 'Hi {{name}}, a quick reminder about your shift at {{office}}. Please let us know if anything comes up.',
            'sort_order' => 2,
        ],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $template) {
            WhatsappTemplate::firstOrCreate(
                ['audience' => $template['audience'], 'title' => $template['title']],
                [
                    'labels' => $template['labels'],
                    'message' => $template['message'],
                    'active' => true,
                    'sort_order' => $template['sort_order'],
                ],
            );
        }
    }
}
