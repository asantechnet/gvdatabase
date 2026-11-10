<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['educational_scholarships', 'academy_classes'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                if ($name === 'educational_scholarships') {
                    $table->unsignedInteger('sort_order')->default(0);
                }
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }
        Schema::create('private_applicant_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('passport_id', 64)->nullable();
            $table->decimal('grade_score', 5, 2)->nullable();
            foreach (['country_id' => 'countries', 'education_level_id' => 'education_levels', 'semester_id' => 'semesters', 'university_id' => 'universities', 'contract_type_id' => 'contract_types'] as $field => $target) {
                $table->foreignId($field)->nullable()->constrained($target)->restrictOnDelete();
            }
            $table->decimal('university_fee', 20, 6)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('scholarship_applicant_details', fn (Blueprint $table) => $table->foreignId('scholarship_id')->nullable()->constrained('educational_scholarships')->restrictOnDelete());
        Schema::table('academy_applicant_details', fn (Blueprint $table) => $table->foreignId('class_id')->nullable()->constrained('academy_classes')->restrictOnDelete());
        Schema::table('applicants', fn (Blueprint $table) => $table->string('backup_phone', 50)->nullable());
        Schema::table('applicant_contract', function (Blueprint $table) {
            // Itemised charges belong to the agreed line, never the applicant.
            foreach (['application_fee', 'visa_fee', 'booking_fee', 'service_fee', 'remote_fee'] as $field) {
                $table->decimal($field, 20, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('applicant_contract', fn (Blueprint $table) => $table->dropColumn(['application_fee', 'visa_fee', 'booking_fee', 'service_fee', 'remote_fee']));
        Schema::table('applicants', fn (Blueprint $table) => $table->dropColumn('backup_phone'));
        Schema::table('academy_applicant_details', fn (Blueprint $table) => $table->dropConstrainedForeignId('class_id'));
        Schema::table('scholarship_applicant_details', fn (Blueprint $table) => $table->dropConstrainedForeignId('scholarship_id'));
        Schema::dropIfExists('private_applicant_details');
        Schema::dropIfExists('academy_classes');
        Schema::dropIfExists('educational_scholarships');
    }
};
