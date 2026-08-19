<?php

namespace App\Http\Controllers;

use App\Http\Requests\LenderLoanProgramRequest;
use App\Http\Requests\LenderRequest;
use App\Models\AuditLog;
use App\Models\Lender;
use App\Models\LenderLoanProgram;
use Illuminate\Http\Request;

class LenderController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Lender::class);

        $query = Lender::withCount('loanPrograms');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('service_area', 'like', "%{$search}%");
            });
        }

        $lenders = $query->latest()->paginate(25);

        return view('lenders.index', compact('lenders'));
    }

    public function create()
    {
        $this->authorize('create', Lender::class);

        return view('lenders.create');
    }

    public function store(LenderRequest $request)
    {
        $this->authorize('create', Lender::class);

        $data = $request->validated();
        $data['tenant_id'] = auth()->user()->tenant_id;

        $lender = Lender::create($data);

        AuditLog::log('lender.created', $lender);

        return redirect()->route('lenders.show', $lender)->with('success', __('Lender created successfully.'));
    }

    public function show(Lender $lender)
    {
        $this->authorize('view', $lender);

        $lender->load(['loanPrograms.dealFundings.deal.lead', 'dealFundings.deal.lead', 'dealFundings.loanProgram']);

        return view('lenders.show', compact('lender'));
    }

    public function edit(Lender $lender)
    {
        $this->authorize('update', $lender);

        return view('lenders.edit', compact('lender'));
    }

    public function update(LenderRequest $request, Lender $lender)
    {
        $this->authorize('update', $lender);

        $lender->update($request->validated());

        AuditLog::log('lender.updated', $lender);

        return redirect()->route('lenders.show', $lender)->with('success', __('Lender updated successfully.'));
    }

    public function destroy(Lender $lender)
    {
        $this->authorize('delete', $lender);

        AuditLog::log('lender.deleted', $lender);

        $lender->delete();

        return redirect()->route('lenders.index')->with('success', __('Lender deleted successfully.'));
    }

    public function storeProgram(LenderLoanProgramRequest $request, Lender $lender)
    {
        $this->authorize('update', $lender);

        $data = $request->validated();
        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['lender_id'] = $lender->id;

        $program = LenderLoanProgram::create($data);

        AuditLog::log('lender_program.created', $program);

        return redirect()->route('lenders.show', $lender)->with('success', __('Loan program added.'));
    }

    public function updateProgram(LenderLoanProgramRequest $request, LenderLoanProgram $program)
    {
        $lender = $program->lender;
        $this->authorize('update', $lender);

        $program->update($request->validated());

        AuditLog::log('lender_program.updated', $program);

        return redirect()->route('lenders.show', $lender)->with('success', __('Loan program updated.'));
    }

    public function destroyProgram(LenderLoanProgram $program)
    {
        $lender = $program->lender;
        $this->authorize('update', $lender);

        AuditLog::log('lender_program.deleted', $program);

        $program->delete();

        return redirect()->route('lenders.show', $lender)->with('success', __('Loan program deleted.'));
    }
}
