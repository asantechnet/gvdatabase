<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment can target one contracted service line; standalone income remains valid.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->foreignId('applicant_contract_id')
                ->nullable()
                ->after('applicant_id')
                ->constrained('applicant_contract')
                ->nullOnDelete();
        });

    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('incomes', function (Blueprint $table) {
                $table->dropForeign(['applicant_contract_id']);
                $table->dropColumn('applicant_contract_id');
            });
        });
    }
};
