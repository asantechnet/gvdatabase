<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Cross-module search for agency staff (Implementation Plan §30).
 *
 * Finds applicants, customers and contracts by the identifiers staff
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
            throw \Illuminate\Validation\ValidationException::withMessages(['q' => ['Search for an ID, or type at least 2 characters.']]);
        }
        $limit = (int) ($data['limit'] ?? 8);
        $user = $request->user();
        $result = ['query' => $q, 'groups' => []];

        if ($user->hasPermission('view_applicants')) {
            $result['groups']['applicants'] = $this->searchApplicants($q, $limit, $user);
        }
        if ($user->hasPermission('view_customers')) {
            $result['groups']['customers'] = $this->searchCustomers($q, $limit);
        }
        if ($user->hasPermission('view_contracts')) {
            $result['groups']['contracts'] = $this->searchContracts($q, $limit);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    private function searchApplicants(string $q, int $limit, User $user): array
    {
        return Applicant::query()
            ->with(['customer', 'currentStage'])
            ->when(! $user->isSuperAdmin() && ! $user->hasPermission('view_all_applicants'), fn ($query) => $query->whereHas('responsibleEmployee', fn ($employee) => $employee->where('user_id', $user->id)))
            ->where(function ($query) use ($q) {
                $query->where('notes', 'like', "%{$q}%");
                if (ctype_digit($q)) {
                    $query->orWhere('id', (int) $q)->orWhere('customer_id', (int) $q);
                }
                $query->orWhereHas('customer', fn ($cq) => $cq
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('contact', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%"));
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Applicant $a) => [
                'type' => 'applicant',
                'id' => $a->id,
                'title' => $a->customer?->name ?? "Applicant #{$a->id}",
                'subtitle' => trim(($a->currentStage?->name ?? 'No stage').' · '.$a->status.($a->customer?->contact ? ' · '.$a->customer->contact : '')),
                'route' => ['name' => 'applicant-case', 'params' => ['id' => $a->id]],
            ])
            ->all();
    }

    private function searchCustomers(string $q, int $limit): array
    {
        return Customer::query()
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('contact', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('company', 'like', "%{$q}%"))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Customer $c) => [
                'type' => 'customer',
                'id' => $c->id,
                'title' => $c->name,
                'subtitle' => trim(collect([$c->contact, $c->email])->filter()->join(' · ')),
                'route' => ['name' => 'all-customers'],
            ])
            ->all();
    }

    private function searchContracts(string $q, int $limit): array
    {
        return Contract::query()
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
                'route' => ['name' => 'contracts'],
            ])
            ->all();
    }
}
