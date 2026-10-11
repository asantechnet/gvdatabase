<?php

use App\Support\BuiltInService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->enum('service', BuiltInService::ALL)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->dropUnique(['service']);
                $table->dropColumn('service');
            });
        });
    }
};
