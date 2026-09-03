<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'Top Performer' => 'Performance',
            'Project Completion' => 'Task Completion',
            'Project Milestone' => 'Task Completion',
            'Spot Bonus' => 'Manager Recognition',
            'Retention' => 'Work Anniversary',
            'Client Praise' => 'Customer Appreciation',
        ] as $legacy => $category) {
            DB::table('worthy_employees')->where('selected_type', $legacy)->update(['selected_type' => $category]);
        }

        if (Schema::hasColumn('worthy_employees', 'eligibility_reason')) {
            Schema::table('worthy_employees', function (Blueprint $table) {
                $table->dropColumn('eligibility_reason');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('worthy_employees', 'eligibility_reason')) {
            Schema::table('worthy_employees', function (Blueprint $table) {
                $table->string('eligibility_reason')->nullable()->after('performance_score');
            });
        }
    }
};
