<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $stage = DB::table('applicant_stages')->where('key', 'new')
            ->whereNull('country_id')->whereNull('university_id')->first();
        $defaults = ['is_active' => true, 'is_terminal' => false, 'deleted_at' => null, 'updated_at' => now()];
        if (! $stage) {
            $id = DB::table('applicant_stages')->insertGetId($defaults + [
                'key' => 'new', 'name' => 'New', 'sort_order' => 10,
                'allowed_next_keys' => json_encode(['consultation', 'cancelled', 'withdrawn']), 'created_at' => now(),
            ]);
        } else {
            $id = $stage->id;
            if (! json_decode($stage->allowed_next_keys ?? '[]', true)) {
                $defaults['allowed_next_keys'] = json_encode(['consultation', 'cancelled', 'withdrawn']);
            }
            DB::table('applicant_stages')->where('id', $id)->update($defaults);
        }

        DB::table('applicants')->whereNull('current_stage_id')->update(['current_stage_id' => $id]);
    }

    public function down(): void
    {
        // Preserve real stages and repaired applicant history on rollback.
    }
};
