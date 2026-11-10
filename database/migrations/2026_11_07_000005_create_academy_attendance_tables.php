<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academy_enrollment_id')->constrained('academy_enrollments')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('academy_classes')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20);
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['academy_enrollment_id', 'date']);
            $table->index(['class_id', 'date']);
        });

        Schema::create('academy_teacher_timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('academy_classes')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20);
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['teacher_id', 'class_id', 'date']);
            $table->index(['teacher_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_teacher_timesheets');
        Schema::dropIfExists('academy_attendances');
    }
};
