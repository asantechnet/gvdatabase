<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_admin_seeds_without_environment_configuration(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $user = User::where('email', SuperAdminSeeder::DEMO_EMAIL)->sole();

        $this->assertSame('Demo Admin', $user->name);
        $this->assertSame('active', $user->acc_status);
        $this->assertTrue(Hash::check(SuperAdminSeeder::DEMO_PASSWORD, $user->password));
        $this->assertTrue($user->roles->contains('name', 'super_admin'));
        $this->assertNotNull($user->employee);
        $this->assertTrue($user->positions->contains('title', 'Director'));

        // The seeded credential signs in through the real login endpoint.
        $this->postJson('/api/auth/login', [
            'email' => SuperAdminSeeder::DEMO_EMAIL,
            'password' => SuperAdminSeeder::DEMO_PASSWORD,
        ])->assertOk();
    }

    public function test_reseeding_keeps_one_demo_admin_and_restores_a_soft_deleted_one(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $user = User::where('email', SuperAdminSeeder::DEMO_EMAIL)->sole();
        $user->delete();

        $this->seed(SuperAdminSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        $restored = User::where('email', SuperAdminSeeder::DEMO_EMAIL)->sole();
        $this->assertSame($user->id, $restored->id);
        $this->assertSame(1, User::withTrashed()->where('email', SuperAdminSeeder::DEMO_EMAIL)->count());
        $this->assertSame(1, $restored->employee()->count());
    }
}
