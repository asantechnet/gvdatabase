<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * WhatsApp message templates are grouped by the record type they are written
 * for, and access follows that record type: reading an audience's templates
 * needs its view permission, changing them needs its edit permission. The
 * placeholder catalogue is what stops a template shipping a token its audience
 * can never fill.
 */
class WhatsappTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);
    }

    /** A user whose single role carries exactly the given permissions. */
    private function actingWithPermissions(string ...$permissions): User
    {
        $role = Role::firstOrCreate(['name' => 'whatsapp_tester_'.implode('_', $permissions) ?: 'none']);
        $role->permissions()->sync(Permission::query()->whereIn('name', $permissions)->pluck('id'));

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    private function template(string $audience, string $title, array $overrides = []): WhatsappTemplate
    {
        return WhatsappTemplate::create([
            'audience' => $audience,
            'title' => $title,
            'labels' => ['test'],
            'message' => 'Hello {{name}}, this is a test.',
            'active' => true,
            'sort_order' => 0,
            ...$overrides,
        ]);
    }

    public function test_the_index_returns_only_the_audiences_the_user_may_view(): void
    {
        $this->template('visitor', 'Visitor one');
        $this->template('applicant', 'Applicant one');
        $this->template('employee', 'Employee one');

        $this->actingWithPermissions('view_applicants');

        $this->getJson('/api/whatsapp-templates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.audience', 'applicant');
    }

    public function test_a_super_admin_sees_every_audience(): void
    {
        $this->template('visitor', 'Visitor one');
        $this->template('applicant', 'Applicant one');
        $this->template('employee', 'Employee one');

        $this->actingAsSuperAdmin();

        $this->getJson('/api/whatsapp-templates')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_templates_can_be_filtered_by_audience_and_active_state(): void
    {
        $this->actingAsSuperAdmin();
        $this->template('visitor', 'Visitor active');
        $this->template('visitor', 'Visitor inactive', ['active' => false]);
        $this->template('employee', 'Employee active');

        $this->getJson('/api/whatsapp-templates?audience=visitor')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.audience', 'visitor');

        $this->getJson('/api/whatsapp-templates?audience=visitor&active=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Visitor active');

        $this->getJson('/api/whatsapp-templates?audience=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audience');
    }

    public function test_creating_a_template_requires_the_record_types_edit_permission(): void
    {
        $payload = [
            'audience' => 'visitor',
            'title' => 'Visitor greeting',
            'labels' => ['greeting', 'greeting', '  '],
            'message' => 'Hello *{{name}}*!',
        ];

        // Reading visitors does not allow writing their templates.
        $this->actingWithPermissions('view_visitors');
        $this->postJson('/api/whatsapp-templates', $payload)
            ->assertForbidden();
        $this->assertDatabaseCount('whatsapp_templates', 0);

        $this->actingWithPermissions('edit_visitors');
        $this->postJson('/api/whatsapp-templates', $payload)
            ->assertCreated()
            ->assertJsonPath('data.audience', 'visitor')
            ->assertJsonPath('data.title', 'Visitor greeting')
            // Duplicates collapse and blanks are dropped.
            ->assertJsonPath('data.labels', ['greeting'])
            ->assertJsonPath('data.active', true);
    }

    public function test_a_template_cannot_use_placeholders_from_another_audience(): void
    {
        $this->actingAsSuperAdmin();

        // `father_phone` belongs to the applicant audience, not to visitors.
        $this->postJson('/api/whatsapp-templates', [
            'audience' => 'visitor',
            'title' => 'Wrong token',
            'message' => 'Hello {{name}}, call {{father_phone}}.',
        ])->assertUnprocessable()->assertJsonValidationErrors('message');

        $this->assertDatabaseCount('whatsapp_templates', 0);

        $this->postJson('/api/whatsapp-templates', [
            'audience' => 'applicant',
            'title' => 'Right token',
            'message' => 'Hello {{name}}, call {{father_phone}}.',
        ])->assertCreated();
    }

    public function test_a_template_requires_a_known_audience_and_a_non_empty_body(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/whatsapp-templates', ['audience' => 'supplier', 'title' => 'Nope', 'message' => 'Hi'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audience');

        $this->postJson('/api/whatsapp-templates', ['audience' => 'visitor', 'title' => 'Nope', 'message' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        $this->postJson('/api/whatsapp-templates', ['audience' => 'visitor', 'message' => 'Hi'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_a_template_can_be_updated_and_deleted(): void
    {
        $this->actingAsSuperAdmin();
        $template = $this->template('visitor', 'Old title');

        $this->putJson("/api/whatsapp-templates/{$template->id}", ['title' => 'New title', 'active' => false])
            ->assertOk()
            ->assertJsonPath('data.title', 'New title')
            ->assertJsonPath('data.active', false);

        $this->deleteJson("/api/whatsapp-templates/{$template->id}")->assertOk();
        $this->assertSoftDeleted('whatsapp_templates', ['id' => $template->id]);
    }

    public function test_moving_a_template_to_another_audience_needs_that_audiences_permission(): void
    {
        $this->actingWithPermissions('edit_visitors');
        $template = $this->template('visitor', 'Visitor template');

        $this->putJson("/api/whatsapp-templates/{$template->id}", ['audience' => 'employee'])
            ->assertForbidden();

        $this->putJson("/api/whatsapp-templates/{$template->id}", ['title' => 'Still a visitor'])
            ->assertOk()
            ->assertJsonPath('data.audience', 'visitor');
    }

    public function test_the_placeholder_catalogue_matches_the_viewable_audiences(): void
    {
        $this->actingWithPermissions('view_visitors');

        $response = $this->getJson('/api/whatsapp-template-placeholders')->assertOk();
        $groups = $response->json('data');

        $this->assertSame(['visitor'], array_column($groups, 'audience'));
        $this->assertSame('pages.whatsapp.placeholders.visitor.name', $groups[0]['fields'][0]['label_key']);
        $this->assertNotEmpty(array_filter($groups[0]['fields'], fn (array $field) => $field['token'] === 'counselor'));

        // A user without any of the three view permissions sees no catalogue.
        $this->actingWithPermissions();
        $this->getJson('/api/whatsapp-template-placeholders')->assertForbidden();
    }
}
