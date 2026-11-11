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
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['academy_enrollment_id', 'date']);
            $table->index(['class_id', 'date']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('academy_attendances');
    }
};
