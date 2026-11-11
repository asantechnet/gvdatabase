<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('academy_teacher_timesheets');

        Schema::table('academy_attendances', function (Blueprint $table): void {
            if (! Schema::hasColumn('academy_attendances', 'check_in_time')) {
                $table->time('check_in_time')->nullable()->after('status');
            }
            if (! Schema::hasColumn('academy_attendances', 'check_out_time')) {
                $table->time('check_out_time')->nullable()->after('check_in_time');
            }
        });

        DB::table('academy_attendances')->where('status', 'late')->update(['status' => 'present']);
        DB::table('academy_attendances')->where('status', 'excused')->update(['status' => 'leave']);

        Schema::table('academy_attendances', function (Blueprint $table): void {
            $drop = array_values(array_filter(['minutes', 'note'], fn (string $column) => Schema::hasColumn('academy_attendances', $column)));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('academy_attendances', function (Blueprint $table): void {
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->text('note')->nullable();
            $table->dropColumn(['check_in_time', 'check_out_time']);
        });

        Schema::create('academy_teacher_timesheets', function (Blueprint $table): void {
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
        });
    }
};
