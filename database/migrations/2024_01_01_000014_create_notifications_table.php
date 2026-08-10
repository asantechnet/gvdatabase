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
            $table->boolean('is_read')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('go_to_url', 2048)->nullable();
            $table->index(['employee_id', 'is_read', 'date_time']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
