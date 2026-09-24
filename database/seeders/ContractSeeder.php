<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\ContractTemplate;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    public function run(): void
    {
        $templates = ContractTemplate::all();

        $contracts = [
            [
                'contract' => 'Service Agreement',
                'start_date' => '2023-01-01',
                'status' => 'active',
                'counterparty' => 'Acme Corp',
                'is_cancelled' => false
            ],
            [
                'contract' => 'Maintenance Contract',
                'start_date' => '2023-03-15',
                'status' => 'completed',
                'counterparty' => 'Globex',
                'is_cancelled' => true
            ],
            [
                'contract' => 'Consulting Agreement',
                'start_date' => '2023-05-20',
                'status' => 'draft',
                'counterparty' => 'Initech',
                'is_cancelled' => false
            ],
        ];

        foreach ($contracts as $contractData) {
            $template = $templates->firstWhere('name', $contractData['contract']);
            if ($template) {
                Contract::create([
                    'template_id' => $template->id,
                    'status' => $contractData['status'],
                    'start_date' => $contractData['start_date'],
                    'counterparty' => $contractData['counterparty'],
                    'is_cancelled' => $contractData['is_cancelled'],
                ]);
            }
        }
    }
}
