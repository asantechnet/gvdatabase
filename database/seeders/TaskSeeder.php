<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Task;
use App\Models\User;
use App\Support\BuiltInService;
use App\Support\PaymentStatus;
use App\Support\ServiceBoard;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $progressByService = [
            BuiltInService::SCHOLARSHIP => 100,
            BuiltInService::PRIVATE => 60,
            BuiltInService::TEST_OFFER => 20,
            BuiltInService::ACADEMY => 40,
        ];

        $orders = [];

        // Delivery work mirrors ContractTaskService: one task per primary
        // contract, bound to the applicant and its service line, on the
        // matching service board.
        Contract::query()
            ->where('contract_role', Contract::ROLE_PRIMARY)
            ->with(['applicant', 'applicantContracts'])
            ->orderBy('id')
            ->get()
            ->each(function (Contract $contract) use (&$orders, $progressByService): void {
                $applicant = $contract->applicant;
                $line = $contract->applicantContracts->first();
                if (! $applicant || ! $line) {
                    return;
                }

                $board = ServiceBoard::firstOrCreateFor(ServiceBoard::titleFor($contract->service));
                $order = $orders[$board->title] ??= 0;

                $task = Task::withTrashed()->firstOrNew([
                    'board_id' => $board->id,
                    'title' => "Deliver {$line->serviceLabel()} — {$applicant->name}",
                ]);
                $task->fill([
                    'applicant_id' => $applicant->id,
                    'applicant_contract_id' => $line->id,
                    'description' => implode(' · ', array_filter([
                        'Contract '.($contract->contract_number ?? "#{$contract->id}"),
                        'Fee: '.number_format((float) $line->fee, 2),
                        'Payment: '.PaymentStatus::label($line->payment_status),
                    ])),
                    'priority' => 'high',
                    'progress' => $progressByService[$contract->service] ?? 0,
                    'start_date' => $contract->start_date?->toDateString(),
                    'due_date' => now()->addDays(7)->toDateString(),
                    'order' => $order,
                ]);
                $task->deleted_at = null;
                $task->save();
                $orders[$board->title] = $order + 1;
            });

        // Operational work lives on the operations manager's *private*
        // personal board, demonstrating that personal work is not shared.
        $personalOwner = User::query()
            ->whereHas('employee.position', fn ($query) => $query->whereIn('system_key', ['manager', 'document_officer']))
            ->orderBy('id')
            ->first();
        $personal = $personalOwner ? ServiceBoard::personalFor($personalOwner) : null;
        $personalTasks = [
            ['title' => 'Update the HQ asset register', 'priority' => 'medium', 'progress' => 0, 'due_in_days' => 5],
            ['title' => 'Prepare the monthly attendance summary', 'priority' => 'medium', 'progress' => 30, 'due_in_days' => 3],
            ['title' => 'Review the visitor intake form', 'priority' => 'low', 'progress' => 0, 'due_in_days' => 10],
            ['title' => 'Archive last quarter payslips', 'priority' => 'low', 'progress' => 100, 'due_in_days' => -2],
        ];
        foreach ($personalTasks as $index => $data) {
            if (! $personal) {
                break;
            }
            $task = Task::withTrashed()->firstOrNew(['board_id' => $personal->id, 'title' => $data['title']]);
            $task->fill([
                'description' => 'Routine operations task.',
                'priority' => $data['priority'],
                'progress' => $data['progress'],
                'start_date' => now()->subDays(3)->toDateString(),
                'due_date' => now()->addDays($data['due_in_days'])->toDateString(),
                'order' => $index,
            ]);
            $task->deleted_at = null;
            $task->save();
        }
    }
}
