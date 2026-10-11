<?php

use App\Support\BuiltInService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->enum('service', BuiltInService::ALL)->nullable();
            $table->index(['applicant_id', 'service', 'contract_role'], 'contracts_builtin_primary_index');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex('contracts_builtin_primary_index');
            $table->dropColumn('service');
        });
    }
};
