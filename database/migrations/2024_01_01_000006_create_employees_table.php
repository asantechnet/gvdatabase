<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('father_name');
            $table->string('contact');
            $table->string('education_lvl');
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->decimal('salary', 10, 2);
            $table->boolean('bonus')->default(false);
            $table->text('addr');
            $table->string('ID_file')->nullable();
            $table->string('assure_file')->nullable();
            $table->string('avatar')->nullable();
            $table->string('department')->nullable();
            $table->date('join_date');
            $table->time('work_start_time')->nullable();
            $table->time('work_end_time')->nullable();
            $table->string('contract_duration');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
