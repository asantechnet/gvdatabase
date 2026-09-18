<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A visa case routinely spans several university applications in possibly
 * different countries, each with its own outcome. The single
 * applicants.university_id can only name the primary choice, so this
 * pivot tracks every application: where, for which intake, and what
 * happened. Fully additive; existing primary-university data is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_universities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('university_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->string('intake')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['applicant_id', 'university_id', 'intake']);
            $table->index(['university_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_universities');
    }
};
