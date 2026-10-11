<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_browser_days', function (Blueprint $table) {
            $table->id();
            $table->string('browser_hash', 64);
            $table->date('date');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->unique(['browser_hash', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_browser_days');
    }
};
