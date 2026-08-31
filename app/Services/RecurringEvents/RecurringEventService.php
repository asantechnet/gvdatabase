<?php

namespace App\Services\RecurringEvents;

use App\Models\Currency;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Notification;
use App\Models\RecurringEvent;
use App\Models\RecurringEventOccurrence;
use App\Models\Task;
use App\Models\User;
use App\Services\CurrencyConversionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringEventService
{
    public function __construct(private readonly CurrencyConversionService $currencies) {}
    public function processDueEvents(): int
    {
        $processed = 0;
        RecurringEvent::query()->where('status', 'active')->whereDate('next_occurrence_date', '<=', today())
            ->orderBy('id')->eachById(function (RecurringEvent $event) use (&$processed): void {
                if ($this->notifyDueEvent($event)) $processed++;
            });
        return $processed;
    }

    public function notifyDueEvent(RecurringEvent $event): bool
    {
        return DB::transaction(function () use ($event): bool {
            $event = RecurringEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($event->status !== 'active' || $event->next_occurrence_date->isFuture()) return false;
            $date = $event->next_occurrence_date->toDateString();
            $occurrence = RecurringEventOccurrence::query()->firstOrCreate(
                ['recurring_event_id' => $event->id, 'occurrence_date' => $date],
                ['status' => 'pending'],
            );
            if (!$occurrence->notification_id) {
                $notification = Notification::create([
                    'type' => 'finance',
                    'notification_key' => 'recurring_event_due',
                    'title' => "{$event->name} is due",
                    'message' => "Your recurring {$event->event_type} event is ready for review.",
                    'data' => ['action' => 'execute_recurring_event', 'recurring_event_id' => $event->id, 'occurrence_id' => $occurrence->id, 'target_type' => $event->event_type],
                    'date_time' => now(),
                ]);
                $notification->deliverTo([$event->owner_id]);
                $occurrence->update(['status' => 'notified', 'notification_id' => $notification->id, 'notified_at' => now()]);
            }
            $event->update(['last_occurrence_date' => $date, 'next_occurrence_date' => $this->nextDate($event, $event->next_occurrence_date)]);
            if ($event->end_date && $event->next_occurrence_date->gt($event->end_date)) $event->update(['status' => 'completed']);
            return true;
        });
    }

    public function skip(RecurringEventOccurrence $occurrence): RecurringEventOccurrence
    {
        return DB::transaction(function () use ($occurrence) {
            $occurrence = RecurringEventOccurrence::query()->lockForUpdate()->findOrFail($occurrence->id);
            if (in_array($occurrence->status, ['completed', 'skipped'], true)) return $occurrence;
            $occurrence->update(['status' => 'skipped', 'skipped_at' => now()]);
            return $occurrence;
        });
    }

    public function execute(RecurringEventOccurrence $occurrence, User $user): RecurringEventOccurrence
    {
        return DB::transaction(function () use ($occurrence, $user) {
            $occurrence = RecurringEventOccurrence::query()->with('recurringEvent')->lockForUpdate()->findOrFail($occurrence->id);
            if ($occurrence->status === 'completed') return $occurrence;
            if ($occurrence->status === 'skipped') throw ValidationException::withMessages(['occurrence' => ['This occurrence was skipped.']]);
            $event = $occurrence->recurringEvent;
            if ($event->status !== 'active') throw ValidationException::withMessages(['event' => ['This recurring event is not active.']]);
            $this->assertExecutionPermission($event, $user);
            $result = match ($event->event_type) {
                'income' => $this->createIncome($event, $occurrence),
                'expense' => $this->createExpense($event, $occurrence),
                'task' => $this->createTask($event, $occurrence, $user),
                default => throw ValidationException::withMessages(['event_type' => ['Unsupported recurring event type.']]),
            };
            $occurrence->update(['status' => 'completed', 'completed_at' => now(), 'result_type' => $result::class, 'result_id' => $result->getKey()]);
            return $occurrence->fresh(['recurringEvent', 'result']);
        });
    }

    public function nextDate(RecurringEvent $event, Carbon $from): Carbon
    {
        return match ($event->frequency) {
            'daily' => $from->copy()->addDays($event->interval),
            'weekly' => $from->copy()->addWeeks($event->interval),
            'monthly' => $from->copy()->addMonthsNoOverflow($event->interval),
            'yearly' => $from->copy()->addYearsNoOverflow($event->interval),
        };
    }

    private function assertExecutionPermission(RecurringEvent $event, User $user): void
    {
        abort_unless($user->hasPermission('execute_recurring_events'), 403, 'auth.forbidden');
        $permission = match ($event->event_type) {
            'income' => 'create_incomes', 'expense' => 'create_expenses', 'task' => 'create_tasks',
        };
        abort_unless($user->hasPermission($permission), 403, 'auth.forbidden');
    }

    private function createIncome(RecurringEvent $event, RecurringEventOccurrence $occurrence): Income
    {
        $data = $event->payload;
        $currency = Currency::findOrFail($data['currency_id']);
        $snapshot = $this->currencies->snapshot($currency);
        unset($data['rate'], $data['base_amount'], $data['base_currency_id']);
        return Income::create([...$data, 'date' => $occurrence->occurrence_date, 'quantity' => $data['quantity'] ?? 1, ...$snapshot]);
    }

    private function createExpense(RecurringEvent $event, RecurringEventOccurrence $occurrence): Expense
    {
        $data = $event->payload;
        $currency = Currency::findOrFail($data['currency_id']);
        $snapshot = $this->currencies->snapshot($currency);
        unset($data['rate'], $data['base_amount'], $data['base_currency_id']);
        return Expense::create([...$data, 'date' => $occurrence->occurrence_date, 'quantity' => $data['quantity'] ?? 1, ...$snapshot]);
    }

    private function createTask(RecurringEvent $event, RecurringEventOccurrence $occurrence, User $user): Task
    {
        $data = $event->payload;
        $task = Task::create([...$data, 'start_date' => $occurrence->occurrence_date, 'due_date' => $data['due_date'] ?? $occurrence->occurrence_date, 'order' => (Task::where('board_id', $data['board_id'])->max('order') ?? -1) + 1]);
        $task->users()->sync($data['assignees'] ?? [$user->id]);
        return $task;
    }
}
