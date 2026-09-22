<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', fn (Blueprint $table) => $table->integer('duration')->nullable()->change());
        Schema::table('leads', function (Blueprint $table): void {
            $table->foreignId('applicant_id')->nullable()->unique()->after('customer_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropConstrainedForeignId('applicant_id'));
        Schema::table('services', fn (Blueprint $table) => $table->integer('duration')->nullable(false)->change());
    }
};
