<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) { $table->string('code', 80)->nullable()->unique()->after('type'); });
        Schema::create('service_variants', function (Blueprint $table) {
            $table->id(); $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('service_variants')->cascadeOnDelete();
            $table->string('name'); $table->string('code', 80); $table->decimal('fee', 15, 2)->nullable();
            $table->timestamps(); $table->softDeletes();
            $table->unique(['service_id', 'code']); $table->index(['service_id', 'parent_id']);
        });
        Schema::create('contract_template_services', function (Blueprint $table) {
            $table->id(); $table->foreignId('contract_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_variant_id')->nullable()->constrained('service_variants')->restrictOnDelete();
            $table->timestamps(); $table->unique(['contract_template_id', 'service_id', 'service_variant_id'], 'template_service_variant_unique');
        });
        Schema::table('contract_service', function (Blueprint $table) { $table->foreignId('service_variant_id')->nullable()->after('service_id')->constrained('service_variants')->nullOnDelete(); });
        DB::table('contract_templates')->whereNotNull('service_id')->orderBy('id')->each(function ($template) {
            DB::table('contract_template_services')->insert(['contract_template_id' => $template->id, 'service_id' => $template->service_id, 'created_at' => now(), 'updated_at' => now()]);
        });
    }
    public function down(): void
    {
        Schema::table('contract_service', fn (Blueprint $table) => $table->dropConstrainedForeignId('service_variant_id'));
        Schema::dropIfExists('contract_template_services'); Schema::dropIfExists('service_variants');
        Schema::table('services', function (Blueprint $table) { $table->dropUnique(['code']); $table->dropColumn('code'); });
    }
};
