<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('contracts', fn(Blueprint $t) => $t->foreignId('contract_template_version_id')->nullable()->after('template_id')->constrained('contract_template_versions')->nullOnDelete()); } public function down(): void { Schema::table('contracts', fn(Blueprint $t) => $t->dropConstrainedForeignId('contract_template_version_id')); } };
