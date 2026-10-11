<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_policies', function (Blueprint $table) {
            $table->string('service', 30)->nullable()->index();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contract_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
            $table->dropColumn('service');
        });
    }
};
