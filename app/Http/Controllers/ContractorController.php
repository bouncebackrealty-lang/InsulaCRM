<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContractorRequest;
use App\Models\AuditLog;
use App\Models\Contractor;
use Illuminate\Http\Request;

class ContractorController extends Controller
{
    /**
     * Display a paginated list of contractors with search and filters.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Contractor::class);

        $query = Contractor::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('service_area', 'like', "%{$search}%");
            });
        }

        if ($request->filled('specialty')) {
            $query->whereJsonContains('specialty', $request->specialty);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $contractors = $query->latest()->paginate(25);

        return view('contractors.index', compact('contractors'));
    }

    /**
     * Bulk action on selected contractors.
     */
    public function bulkAction(Request $request)
    {
        $this->authorize('bulkDelete', Contractor::class);

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'action' => 'required|in:delete',
        ]);

        $contractors = Contractor::whereIn('id', $request->ids)->get();
        $count = $contractors->count();

        foreach ($contractors as $contractor) {
            AuditLog::log('contractor.deleted', $contractor);
            $contractor->delete();
        }

        return redirect()->route('contractors.index')->with('success', "{$count} contractor(s) deleted.");
    }

    /**
     * Show the form for creating a new contractor.
     */
    public function create()
    {
        $this->authorize('create', Contractor::class);

        return view('contractors.create');
    }

    /**
     * Store a newly created contractor.
     */
    public function store(ContractorRequest $request)
    {
        $this->authorize('create', Contractor::class);

        $data = $request->validated();
        $data['tenant_id'] = auth()->user()->tenant_id;

        $contractor = Contractor::create($data);

        AuditLog::log('contractor.created', $contractor);

        return redirect()->route('contractors.show', $contractor)->with('success', __('Contractor created successfully.'));
    }

    /**
     * Display the specified contractor with attached deals.
     */
    public function show(Contractor $contractor)
    {
        $this->authorize('view', $contractor);

        $contractor->load(['dealBids.deal.lead']);

        return view('contractors.show', compact('contractor'));
    }

    /**
     * Show the form for editing the specified contractor.
     */
    public function edit(Contractor $contractor)
    {
        $this->authorize('update', $contractor);

        return view('contractors.edit', compact('contractor'));
    }

    /**
     * Update the specified contractor.
     */
    public function update(ContractorRequest $request, Contractor $contractor)
    {
        $this->authorize('update', $contractor);

        $data = $request->validated();
        $data['specialty'] = $data['specialty'] ?? [];

        $contractor->update($data);

        AuditLog::log('contractor.updated', $contractor);

        return redirect()->route('contractors.show', $contractor)->with('success', __('Contractor updated successfully.'));
    }

    /**
     * Remove the specified contractor.
     */
    public function destroy(Contractor $contractor)
    {
        $this->authorize('delete', $contractor);

        AuditLog::log('contractor.deleted', $contractor);

        $contractor->delete();

        return redirect()->route('contractors.index')->with('success', __('Contractor deleted successfully.'));
    }

    /**
     * Export contractors as CSV.
     */
    public function export(Request $request)
    {
        $this->authorize('export', Contractor::class);

        $query = Contractor::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('service_area', 'like', "%{$search}%");
            });
        }

        if ($request->filled('specialty')) {
            $query->whereJsonContains('specialty', $request->specialty);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $contractors = $query->latest()->get();

        return response()->streamDownload(function () use ($contractors) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                __('Name'), __('Phone'), __('Email'), __('Specialty'),
                __('Service Area'), __('Priority'), __('Referral Source'), __('Status'), __('Notes'),
            ]);
            foreach ($contractors as $contractor) {
                $specialties = collect($contractor->specialty ?? [])
                    ->map(fn ($s) => Contractor::TRADE_CATEGORIES[$s] ?? $s)
                    ->implode(', ');
                fputcsv($handle, [
                    $contractor->name,
                    $contractor->phone,
                    $contractor->email,
                    $specialties,
                    $contractor->service_area,
                    Contractor::PRIORITIES[$contractor->priority] ?? $contractor->priority,
                    $contractor->referral_source,
                    Contractor::STATUSES[$contractor->status] ?? $contractor->status,
                    $contractor->notes,
                ]);
            }
            fclose($handle);
        }, 'contractors-export-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
