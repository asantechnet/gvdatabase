<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The CRM visitor surface is split into two lists on one page: the Visitors list
 * (records that are not marked worthy of follow-up) and the Follow-up list (the
 * marked ones). Both are driven by the same endpoint and the same flag.
 */
class VisitorFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);
    }

    private function visitor(string $name, string $contact, bool $followUp = false, ?string $convertedAt = null): Visitor
    {
        return Visitor::create([
            'name' => $name,
            'contact' => $contact,
            'service' => 'Scholarship',
            'date' => now()->toDateString(),
            'follow_up' => $followUp,
            'converted_at' => $convertedAt,
        ]);
    }

    public function test_the_visitor_and_follow_up_lists_are_split_by_the_flag(): void
    {
        $this->actingAsSuperAdmin();
        $this->visitor('Plain one', '0700000001');
        $this->visitor('Plain two', '0700000002');
        $this->visitor('Marked one', '0700000003', true);
        // A converted visitor belongs to neither list.
        $this->visitor('Marked and converted', '0700000004', true, now()->toDateTimeString());

        $plain = $this->getJson('/api/visitors?follow_up=0')->assertOk()->json('data');
        $this->assertSame(['Plain one', 'Plain two'], array_column($plain, 'name'));
        $this->assertSame([false, false], array_column($plain, 'follow_up'));

        $marked = $this->getJson('/api/visitors?follow_up=1')->assertOk()->json('data');
        $this->assertSame(['Marked one'], array_column($marked, 'name'));
        $this->assertSame([true], array_column($marked, 'follow_up'));

        // Without the filter the endpoint keeps its historical shape: every
        // visitor that has not converted, regardless of the follow-up flag.
        $this->getJson('/api/visitors')->assertOk()->assertJsonCount(3, 'data');

        $this->getJson('/api/visitors?follow_up=maybe')->assertUnprocessable()->assertJsonValidationErrors('follow_up');
    }

    public function test_a_visitor_can_be_moved_into_and_back_out_of_the_follow_up_list(): void
    {
        $this->actingAsSuperAdmin();
        $visitor = $this->visitor('Candidate', '0700000005');

        $this->putJson("/api/visitors/{$visitor->id}/follow-up", ['follow_up' => true])
            ->assertOk()->assertJsonPath('data.follow_up', true);
        $this->assertDatabaseHas('visitors', ['id' => $visitor->id, 'follow_up' => true]);
        $this->getJson('/api/visitors?follow_up=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/visitors?follow_up=0')->assertOk()->assertJsonCount(0, 'data');

        $this->putJson("/api/visitors/{$visitor->id}/follow-up", ['follow_up' => false])
            ->assertOk()->assertJsonPath('data.follow_up', false);
        $this->assertDatabaseHas('visitors', ['id' => $visitor->id, 'follow_up' => false]);
        $this->getJson('/api/visitors?follow_up=0')->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/visitors/{$visitor->id}/follow-up", ['follow_up' => 'maybe'])
            ->assertUnprocessable()->assertJsonValidationErrors('follow_up');
        $this->putJson("/api/visitors/{$visitor->id}/follow-up", [])
            ->assertUnprocessable()->assertJsonValidationErrors('follow_up');
    }

    public function test_marking_a_visitor_for_follow_up_requires_the_edit_permission(): void
    {
        $role = Role::firstOrCreate(['name' => 'visitor_reader']);
        $role->permissions()->sync(Permission::query()->where('name', 'view_visitors')->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        Sanctum::actingAs($user, ['access']);

        $visitor = $this->visitor('Read only', '0700000006');

        $this->getJson('/api/visitors?follow_up=0')->assertOk();
        $this->putJson("/api/visitors/{$visitor->id}/follow-up", ['follow_up' => true])->assertForbidden();
        $this->assertDatabaseHas('visitors', ['id' => $visitor->id, 'follow_up' => false]);
    }
}
