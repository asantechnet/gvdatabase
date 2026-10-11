<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core section: settings, RBAC catalog tables, API tokens and the (singleton)
 * office. Column order, types and defaults mirror the historical end state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('navigation_clr')->nullable();
            $table->string('cards_clr')->nullable();
            $table->string('footer_clr')->nullable();
            $table->unsignedSmallInteger('paid_leave_days_per_year')->default(0);
            $table->string('website_title');
            $table->string('website_logo')->nullable();
            $table->string('company_signature')->nullable();
            $table->string('contract_letterhead')->nullable();
            $table->string('contract_cover_design')->nullable();
            $table->string('company_email')->nullable();
            $table->string('mail_host')->nullable();
            $table->unsignedSmallInteger('mail_port')->nullable();
            $table->string('mail_encryption', 10)->nullable();
            $table->string('mail_username')->nullable();
            $table->text('mail_password')->nullable();
            $table->string('mail_from_name')->nullable();
            $table->string('mail_reply_to')->nullable();
            $table->string('timezone', 64)->default('Asia/Kabul');
            $table->string('website_address')->nullable();
            $table->string('phone', 50);
            $table->text('physical_address');
            $table->string('short_company_name');
            $table->string('original_company_name');
            $table->string('business_licence')->nullable();
            $table->time('office_open_time')->nullable();
            $table->time('office_close_time')->nullable();
            $table->unsignedSmallInteger('default_break_minutes')->default(0);
            $table->boolean('automatic_attendance_enabled')->default(false);
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('success_color')->default('#15803D');
            $table->string('info_color')->default('#0284C7');
            $table->string('warning_color')->default('#D97706');
            $table->string('error_color')->default('#DC2626');
            $table->string('light_background_color')->default('#F5F7F4');
            $table->string('light_surface_color')->default('#FFFEFA');
            $table->string('dark_background_color')->default('#102321');
            $table->string('dark_surface_color')->default('#173330');
            $table->timestamps();
            $table->softDeletes();
            // Column order matches history: the safe area was appended after
            // the timestamp columns by a later migration.
            $table->decimal('contract_letterhead_safe_x', 5, 2)->default(8.50);
            $table->decimal('contract_letterhead_safe_y', 5, 2)->default(15.00);
            $table->decimal('contract_letterhead_safe_width', 5, 2)->default(83.00);
            $table->decimal('contract_letterhead_safe_height', 5, 2)->default(70.00);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category')->default('general');
            $table->string('guard_name')->default('web');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('level')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
            $table->index('user_id');
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
            $table->index('role_id');
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            // Singleton office: at most one row may exist (enforced by the
            // unique index, mirroring the final historical state).
            $table->boolean('is_singleton')->default(true)->unique();
            $table->string('name');
            $table->string('code', 30);
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('geofence_radius_meters')->default(100);
            $table->boolean('geofence_enabled')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('settings');
    }
};
