<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->default('info');
            $table->string('title');
            $table->text('message');
            $table->timestamp('date_time')->useCurrent();
            // Optional business context. Delivery and recipient state live in notification_user.
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('go_to_url', 2048)->nullable();
            $table->index(['employee_id', 'date_time']);
            $table->timestamps();
        });

        Schema::create('notification_user', function (Blueprint $table) {
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_read')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'user_id']);
            $table->index(['user_id', 'deleted_at', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_user');
        Schema::dropIfExists('notifications');
    }
};
