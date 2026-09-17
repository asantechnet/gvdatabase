<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Terminal stages were unreachable from the linear seed, so cancellations,
 * refusals, withdrawals and appeals could never be recorded. This migration
 * adds exit edges (cancel/withdraw from any active stage, reject from the
 * decision stages) and appeal edges back into the pipeline, without touching
 * the happy-path order.
 */
return new class extends Migration
{
    private function linear(): array
    {
        return [
            'new' => ['consultation'],
            'consultation' => ['documents_required'],
            'documents_required' => ['documents_complete'],
            'documents_complete' => ['university_selection'],
            'university_selection' => ['university_application'],
            'university_application' => ['admission_pending'],
            'admission_pending' => ['admission_received'],
            'admission_received' => ['contract_service_active'],
            'contract_service_active' => ['visa_preparation'],
            'visa_preparation' => ['visa_submitted'],
            'visa_submitted' => ['visa_processing'],
            'visa_processing' => ['visa_approved'],
            'visa_approved' => ['completed'],
            'completed' => [],
            'cancelled' => [],
            'rejected' => [],
            'withdrawn' => [],
        ];
    }

    private function withExits(): array
    {
        $map = $this->linear();
        $exits = ['cancelled', 'withdrawn'];

        foreach ($map as $key => $next) {
            if (in_array($key, ['completed', 'cancelled', 'rejected', 'withdrawn'], true)) {
                continue;
            }
            $map[$key] = array_values(array_unique([...$next, ...$exits]));
        }

        // Refusals happen at the decision stages.
        $map['admission_pending'][] = 'rejected';
        $map['visa_submitted'][] = 'rejected';
        $map['visa_processing'][] = 'rejected';

        // Appeals / re-engagement back into the pipeline.
        $map['rejected'] = ['university_selection', 'visa_preparation'];
        $map['withdrawn'] = ['consultation'];

        return $map;
    }

    private function apply(array $map): void
    {
        foreach ($map as $key => $next) {
            DB::table('applicant_stages')->where('key', $key)->update(['allowed_next_keys' => json_encode(array_values($next)), 'updated_at' => now()]);
        }
    }

    public function up(): void
    {
        $this->apply($this->withExits());
    }

    public function down(): void
    {
        $this->apply($this->linear());
    }
};
