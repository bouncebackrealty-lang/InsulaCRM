<?php

namespace App\Http\Controllers;

use App\Http\Requests\TitleCompanyRequest;
use App\Models\AuditLog;
use App\Models\TitleCompany;
use Illuminate\Http\Request;

class TitleCompanyController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', TitleCompany::class);
        $query = TitleCompany::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('closing_attorney', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }
        return view('title-companies.index', ['titleCompanies' => $query->latest()->paginate(25)]);
    }

    public function create()
    {
        $this->authorize('create', TitleCompany::class);
        return view('title-companies.form', ['titleCompany' => new TitleCompany]);
    }

    public function store(TitleCompanyRequest $request)
    {
        $this->authorize('create', TitleCompany::class);
        $titleCompany = TitleCompany::create($request->validated() + ['tenant_id' => auth()->user()->tenant_id]);
        AuditLog::log('title_company.created', $titleCompany);
        return redirect()->route('title-companies.show', $titleCompany)->with('success', __('Title company created successfully.'));
    }

    public function show(TitleCompany $titleCompany)
    {
        $this->authorize('view', $titleCompany);
        $titleCompany->load('deals.lead');
        return view('title-companies.show', compact('titleCompany'));
    }

    public function edit(TitleCompany $titleCompany)
    {
        $this->authorize('update', $titleCompany);
        return view('title-companies.form', compact('titleCompany'));
    }

    public function update(TitleCompanyRequest $request, TitleCompany $titleCompany)
    {
        $this->authorize('update', $titleCompany);
        $titleCompany->update($request->validated());
        AuditLog::log('title_company.updated', $titleCompany);
        return redirect()->route('title-companies.show', $titleCompany)->with('success', __('Title company updated successfully.'));
    }

    public function destroy(TitleCompany $titleCompany)
    {
        $this->authorize('delete', $titleCompany);
        AuditLog::log('title_company.deleted', $titleCompany);
        $titleCompany->delete();
        return redirect()->route('title-companies.index')->with('success', __('Title company deleted.'));
    }
}
