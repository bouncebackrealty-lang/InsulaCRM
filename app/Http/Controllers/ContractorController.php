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

    /**
     * Download a sample CSV template for bulk import.
     */
    public function importTemplate()
    {
        $this->authorize('import', Contractor::class);

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'name', 'phone', 'email', 'specialty', 'service_area',
                'priority', 'referral_source', 'status', 'notes',
            ]);
            // Example rows showing the expected format.
            fputcsv($handle, [
                'Acme Roofing', '555-0100', 'acme@example.com', 'Roofing, HVAC', 'Atlanta Metro, GA',
                'High', 'Referred by John', 'Bid Submitted', 'Reliable crew, fast turnaround',
            ]);
            fputcsv($handle, [
                'Bright Electric', '555-0111', 'bright@example.com', 'Electrical', 'Fulton County, GA',
                'Medium', 'Facebook group', 'Contacted', '',
            ]);
            fclose($handle);
        }, 'contractor-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Bulk-import contractors from a CSV file.
     */
    public function import(Request $request)
    {
        $this->authorize('import', Contractor::class);

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if ($handle === false) {
            return redirect()->route('contractors.index')->with('error', __('Unable to read the CSV file.'));
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return redirect()->route('contractors.index')->with('error', __('The CSV file is empty or invalid.'));
        }

        // Normalize headers: lowercase, trim, spaces -> underscores, strip BOM.
        $header = array_map(function ($col) {
            $col = preg_replace('/^\xEF\xBB\xBF/', '', (string) $col);
            return str_replace(' ', '_', strtolower(trim($col)));
        }, $header);

        $columns = ['name', 'phone', 'email', 'specialty', 'service_area', 'priority', 'referral_source', 'status', 'notes'];
        $map = [];
        foreach ($columns as $col) {
            $index = array_search($col, $header, true);
            $map[$col] = $index === false ? null : $index;
        }

        if ($map['name'] === null) {
            fclose($handle);
            return redirect()->route('contractors.index')->with('error', __('CSV must contain a "name" column.'));
        }

        $tenantId = auth()->user()->tenant_id;
        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            // Skip fully empty rows.
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $name = trim((string) ($row[$map['name']] ?? ''));
            if ($name === '') {
                $skipped++;
                continue;
            }

            $get = fn ($col) => $map[$col] !== null ? trim((string) ($row[$map[$col]] ?? '')) : null;

            $specialty = [];
            if ($map['specialty'] !== null) {
                foreach (preg_split('/[,;|]/', (string) $row[$map['specialty']]) as $part) {
                    $key = $this->resolveOption($part, Contractor::TRADE_CATEGORIES);
                    if ($key !== null && !in_array($key, $specialty, true)) {
                        $specialty[] = $key;
                    }
                }
            }

            Contractor::create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'phone' => $get('phone') ?: null,
                'email' => $get('email') ?: null,
                'specialty' => $specialty,
                'service_area' => $get('service_area') ?: null,
                'priority' => $this->resolveOption($get('priority'), Contractor::PRIORITIES) ?? 'medium',
                'referral_source' => $get('referral_source') ?: null,
                'status' => $this->resolveOption($get('status'), Contractor::STATUSES) ?? 'contacted',
                'notes' => $get('notes') ?: null,
            ]);

            $imported++;
        }

        fclose($handle);

        $message = __(':count contractor(s) imported successfully.', ['count' => $imported]);
        if ($skipped > 0) {
            $message .= ' ' . __(':count row(s) skipped (missing name).', ['count' => $skipped]);
        }

        return redirect()->route('contractors.index')->with('success', $message);
    }

    /**
     * Resolve a free-text CSV value to a valid option key.
     * Matches against the internal key or the display label (case-insensitive).
     */
    private function resolveOption(?string $value, array $options): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $needle = strtolower($value);
        foreach ($options as $key => $label) {
            if ($needle === strtolower($key) || $needle === strtolower($label)) {
                return $key;
            }
        }

        // Also match a slugified label, e.g. "general contractor" -> "general_contractor".
        $slug = str_replace(' ', '_', $needle);
        return array_key_exists($slug, $options) ? $slug : null;
    }
}
