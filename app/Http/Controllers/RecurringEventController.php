<?php

namespace App\Http\Controllers;

use App\Http\Resources\RecurringEventOccurrenceResource;
use App\Http\Resources\RecurringEventResource;
use App\Models\RecurringEvent;
use App\Models\RecurringEventOccurrence;
use App\Models\Board;
use App\Services\RecurringEvents\RecurringEventService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurringEventController extends Controller
{
    public function index(Request $request)
    {
        return RecurringEventResource::collection(RecurringEvent::with('owner')->latest()->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertTargetPermission($request, $data['event_type']);
        $data['owner_id'] = $request->user()->id;
        return (new RecurringEventResource(RecurringEvent::create($data)->load('owner')))->response()->setStatusCode(201);
    }

    public function show(RecurringEvent $recurringEvent)
    {
        return new RecurringEventResource($recurringEvent->load(['owner', 'occurrences' => fn ($query) => $query->latest('occurrence_date')]));
    }

    public function update(Request $request, RecurringEvent $recurringEvent)
    {
        $data = $this->validated($request, true);
        $this->assertTargetPermission($request, $data['event_type'] ?? $recurringEvent->event_type);
        $recurringEvent->update($data);
        return new RecurringEventResource($recurringEvent->fresh()->load('owner'));
    }

    public function destroy(RecurringEvent $recurringEvent)
    {
        $recurringEvent->delete();
        return response()->noContent();
    }

    public function pause(RecurringEvent $recurringEvent)
    {
        $recurringEvent->update(['status' => 'paused']);
        return new RecurringEventResource($recurringEvent);
    }

    public function resume(RecurringEvent $recurringEvent)
    {
        $recurringEvent->update(['status' => 'active']);
        return new RecurringEventResource($recurringEvent);
    }

    public function skip(RecurringEventOccurrence $occurrence, RecurringEventService $service)
    {
        return new RecurringEventOccurrenceResource($service->skip($occurrence));
    }

    public function execute(Request $request, RecurringEventOccurrence $occurrence, RecurringEventService $service)
    {
        return new RecurringEventOccurrenceResource($service->execute($occurrence, $request->user()));
    }

    private function validated(Request $request, bool $updating = false): array
    {
        $rules = [
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'], 'event_type' => [$updating ? 'sometimes' : 'required', Rule::in(['income', 'expense', 'task'])],
            'status' => ['sometimes', Rule::in(['active', 'paused'])], 'frequency' => [$updating ? 'sometimes' : 'required', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'interval' => [$updating ? 'sometimes' : 'required', 'integer', 'min:1', 'max:365'],
            'start_date' => [$updating ? 'sometimes' : 'required', 'date'], 'next_occurrence_date' => [$updating ? 'sometimes' : 'required', 'date', 'after_or_equal:start_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'], 'payload' => [$updating ? 'sometimes' : 'required', 'array'],
            'payload.amount' => ['required_if:event_type,income,expense', 'numeric', 'gt:0'], 'payload.currency_id' => ['required_if:event_type,income,expense', 'exists:currencies,id'],
            'payload.type' => ['required_if:event_type,income,expense', 'string', 'max:255'], 'payload.board_id' => ['required_if:event_type,task', 'exists:boards,id'],
            'payload.title' => ['required_if:event_type,task', 'string', 'max:255'],
            'payload.customer_id' => ['nullable', 'exists:customers,id'],
            'payload.company_id' => ['nullable', 'exists:companies,id'],
            'payload.project_id' => ['nullable', 'exists:projects,id'],
        ];
        return $request->validate($rules);
    }

    private function assertTargetPermission(Request $request, string $type): void
    {
        $permission = match ($type) { 'income' => 'create_recurring_incomes', 'expense' => 'create_recurring_expenses', 'task' => 'create_recurring_tasks' };
        abort_unless($request->user()->hasPermission($permission), 403, 'auth.forbidden');

        if ($type === 'task' && $request->has('payload.board_id')) {
            $boardId = $request->input('payload.board_id');
            $canUseBoard = $request->user()->hasPermission('view_all_boards')
                || Board::query()->whereKey($boardId)->whereHas('users', fn ($users) => $users->whereKey($request->user()->id))->exists();

            abort_unless($canUseBoard, 403, 'auth.forbidden');
        }
    }
}
