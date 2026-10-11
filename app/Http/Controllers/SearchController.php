<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\User;
use App\Support\BuiltInService;
use App\Support\ContractAccess;
use App\Support\EmployeeScope;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Cross-module search for agency staff (Implementation Plan §30).
 *
 * Finds applicants and contracts by the identifiers staff
 * actually remember — names, phone numbers, emails, record IDs and
 * contract numbers — and links each hit to where it can be worked on.
 * Every group is permission-gated so search never leaks records the
 * user is not allowed to open.
 */
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $q = trim($data['q']);
        // Short numeric queries are record IDs; short text would scan
        // almost every row, so it still needs at least 2 characters.
        if ($q === '' || (! ctype_digit($q) && mb_strlen($q) < 2)) {
            throw ValidationException::withMessages(['q' => ['Search for an ID, or type at least 2 characters.']]);
        }
        $limit = (int) ($data['limit'] ?? 8);
        $user = $request->user();
        $result = ['query' => $q, 'groups' => []];

        if ($user->hasPermission('view_applicants')) {
            $result['groups']['applicants'] = $this->searchApplicants($q, $limit, $user);
        }
        if ($user->hasPermission('view_contracts')) {
            $result['groups']['contracts'] = $this->searchContracts($q, $limit, $user);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    private function searchApplicants(string $q, int $limit, User $user): array
    {
        return Applicant::query()
            ->with(['currentStage'])
            ->when(! $user->isSuperAdmin() && ! $user->hasPermission('view_all_applicants'), fn ($query) => $query->whereIn('responsible_employee_id', app(EmployeeScope::class)->visibleEmployeeIds($user)))
            ->where(function ($query) use ($q) {
                $query->where('notes', 'like', "%{$q}%");
                if (ctype_digit($q)) {
                    $query->orWhere('id', (int) $q);
                }
                $query->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('contact', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Applicant $a) => [
                'type' => 'applicant',
                'id' => $a->id,
                'title' => $a->name ?: "Applicant #{$a->id}",
                'subtitle' => trim(($a->currentStage?->name ?? 'No stage').' · '.$a->status.($a->contact ? ' · '.$a->contact : '')),
                'route' => ['name' => 'applicant-case', 'params' => ['id' => $a->id]],
            ])
            ->all();
    }

    private function searchContracts(string $q, int $limit, User $user): array
    {
        return ContractAccess::scopeVisibleTo(Contract::query()->with('contractType'), $user)
            ->where(fn ($query) => $query->whereIn('service', BuiltInService::ALL)
                ->orWhereHas('contractType', fn ($type) => $type->whereIn('slug', ['employment', 'other'])->orWhere('is_system', false)))
            ->where(fn ($query) => $query
                ->where('contract_number', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('counterparty', 'like', "%{$q}%")
                ->orWhere('counterparty_contact', 'like', "%{$q}%"))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Contract $c) => [
                'type' => 'contract',
                'id' => $c->id,
                'title' => $c->contract_number ?? $c->name ?? "Contract #{$c->id}",
                'subtitle' => trim(($c->status ?? 'draft').($c->counterparty ? ' · '.$c->counterparty : '')),
                'route' => [
                    'name' => $c->service ? str_replace('_', '-', $c->service).'-contracts'
                        : ($c->contractType?->slug === 'employment' ? 'employee-contracts' : 'other-contracts'),
                    'query' => ['contractId' => $c->id],
                ],
            ])
            ->all();
    }
}
