<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('applicant_attachments', function (Blueprint $table) {
            $table->id(); $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label'); $table->string('category')->nullable(); $table->string('disk')->default('local');
            $table->string('path'); $table->string('original_name'); $table->string('mime_type', 120)->nullable(); $table->unsignedBigInteger('size')->default(0);
            $table->timestamps(); $table->softDeletes(); $table->index(['applicant_id', 'label']);
        });
        Schema::table('applicants', fn (Blueprint $table) => $table->dropColumn('company'));
    }
    public function down(): void {
        Schema::table('applicants', fn (Blueprint $table) => $table->string('company')->nullable());
        Schema::dropIfExists('applicant_attachments');
    }
};
