<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('contract_types')->where('slug', 'company')->update(['name' => 'Other', 'slug' => 'other']);
        DB::table('contracts')->where('type', 'Company')->update(['type' => 'Other']);
        DB::table('contract_templates')->where('type', 'Company')->update(['type' => 'Other']);

        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('service_id')->nullable()->after('contract_type_id')->constrained()->nullOnDelete();
            $table->index('service_id');
        });
        Schema::table('contract_templates', function (Blueprint $table): void {
            $table->foreignId('service_id')->nullable()->after('contract_type_id')->constrained()->nullOnDelete();
            $table->index('service_id');
        });
        Schema::create('contract_document_designs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('letterhead')->nullable();
            $table->string('cover_design')->nullable();
            $table->decimal('safe_x', 5, 2)->default(8.50);
            $table->decimal('safe_y', 5, 2)->default(15.00);
            $table->decimal('safe_width', 5, 2)->default(83.00);
            $table->decimal('safe_height', 5, 2)->default(70.00);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['contract_type_id', 'service_id'], 'contract_design_type_service_unique');
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->string('document_path')->nullable()->after('description');
            $table->string('document_name')->nullable()->after('document_path');
            $table->string('document_mime', 120)->nullable()->after('document_name');
            $table->unsignedBigInteger('document_size')->nullable()->after('document_mime');
        });
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('document_path')->nullable()->after('avatar');
            $table->string('document_name')->nullable()->after('document_path');
            $table->string('document_mime', 120)->nullable()->after('document_name');
            $table->unsignedBigInteger('document_size')->nullable()->after('document_mime');
        });
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn(['document_path', 'document_name', 'document_mime', 'document_size']));
        Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn(['document_path', 'document_name', 'document_mime', 'document_size']));
        Schema::dropIfExists('contract_document_designs');
        Schema::table('contract_templates', fn (Blueprint $table) => $table->dropConstrainedForeignId('service_id'));
        Schema::table('contracts', fn (Blueprint $table) => $table->dropConstrainedForeignId('service_id'));
    }
};
