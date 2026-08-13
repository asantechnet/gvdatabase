<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('guarantor_phone', 30)->nullable()->after('guarantor_id_number');
            $table->dropColumn('bonus');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('bonus')->default(false)->after('salary');
            $table->dropColumn('guarantor_phone');
        });
    }
};
