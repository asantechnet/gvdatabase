<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Global announcements: immutable messages composed by authorized employees
 * and delivered into the notification inbox of the selected audience. The
 * educational system additionally supports an applicants audience for the
 * applicant portal. See asantech-mini-management/ANNOUNCEMENTS_FEATURE_PLAN.md
 * for the shared design.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->string('title')->nullable();
            $table->text('message');
            $table->json('labels')->nullable();
            $table->json('audience');
            $table->timestamps();

            // Required by the Auditable trait (SoftDeletes) used on the model.
            $table->softDeletes();
        });

        $now = now();

        foreach (['send_announcements', 'announce_to_all_employees', 'announce_to_applicants', 'view_all_announcements'] as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name],
                ['category' => 'operations', 'guard_name' => 'api', 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');

        Permission::query()
            ->whereIn('name', ['send_announcements', 'announce_to_all_employees', 'announce_to_applicants', 'view_all_announcements'])
            ->delete();
    }
};
