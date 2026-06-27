@extends('layouts.app')

@section('title', $lender->name)
@section('page-title', $lender->name)

@section('breadcrumbs')
<li class="breadcrumb-item"><a href="{{ route('lenders.index') }}">{{ __('Lenders') }}</a></li>
<li class="breadcrumb-item active" aria-current="page">{{ $lender->name }}</li>
@endsection

@section('content')
<div class="row row-deck row-cards">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ __('Lender Details') }}</h3>
                <div class="card-actions">
                    <a href="{{ route('lenders.edit', $lender) }}" class="btn btn-outline-primary btn-sm">{{ __('Edit') }}</a>
                </div>
            </div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">{{ __('Name') }}</div>
                        <div class="datagrid-content">{{ $lender->name }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">{{ __('Company') }}</div>
                        <div class="datagrid-content">{{ $lender->company ?? '-' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">{{ __('Phone') }}</div>
                        <div class="datagrid-content">@if($lender->phone)<a href="tel:{{ $lender->phone }}" class="text-reset">{{ $lender->phone }}</a>@else - @endif</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">{{ __('Email') }}</div>
                        <div class="datagrid-content">@if($lender->email)<a href="mailto:{{ $lender->email }}" class="text-reset">{{ $lender->email }}</a>@else - @endif</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">{{ __('Service Area') }}</div>
                        <div class="datagrid-content">{{ $lender->service_area ?? '-' }}</div>
                    </div>
                </div>
                @if($lender->notes)
                    <div class="mt-3 pt-3 border-top">
                        <div class="datagrid-title mb-2">{{ __('Notes') }}</div>
                        <div class="text-secondary" style="white-space: pre-wrap;">{{ $lender->notes }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ __('Loan Programs') }}</h3>
                @if($lender->loanPrograms->count())
                <div class="card-actions">
                    <span class="badge bg-blue-lt">{{ $lender->loanPrograms->count() }}</span>
                </div>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>{{ __('Program') }}</th>
                            <th>{{ __('Rate') }}</th>
                            <th>{{ __('Points') }}</th>
                            <th>{{ __('Max LTC') }}</th>
                            <th>{{ __('Max LTV') }}</th>
                            <th>{{ __('Term') }}</th>
                            <th>{{ __('Closing Cost') }}</th>
                            <th>{{ __('Builder Risk') }}</th>
                            <th>{{ __('Notes') }}</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lender->loanPrograms as $program)
                        <tr>
                            @php $programFormId = 'program-update-' . $program->id; @endphp
                            <td>
                                <form id="{{ $programFormId }}" method="POST" action="{{ route('lenders.programs.update', $program) }}" class="d-none">
                                    @csrf
                                    @method('PUT')
                                </form>
                                <input form="{{ $programFormId }}" type="text" name="program_name" class="form-control form-control-sm" value="{{ old('program_name', $program->program_name) }}" required>
                            </td>
                            <td><input form="{{ $programFormId }}" type="number" name="interest_rate" class="form-control form-control-sm" value="{{ $program->interest_rate }}" step="0.01" min="0" max="100"></td>
                            <td><input form="{{ $programFormId }}" type="number" name="points" class="form-control form-control-sm" value="{{ $program->points }}" step="0.01" min="0" max="100"></td>
                            <td><input form="{{ $programFormId }}" type="number" name="max_ltc" class="form-control form-control-sm" value="{{ $program->max_ltc }}" step="0.01" min="0" max="100"></td>
                            <td><input form="{{ $programFormId }}" type="number" name="max_ltv" class="form-control form-control-sm" value="{{ $program->max_ltv }}" step="0.01" min="0" max="100"></td>
                            <td><input form="{{ $programFormId }}" type="text" name="term_length" class="form-control form-control-sm" value="{{ $program->term_length }}"></td>
                            <td><input form="{{ $programFormId }}" type="number" name="purchase_closing_cost_percent" class="form-control form-control-sm" value="{{ $program->purchase_closing_cost_percent }}" step="0.01" min="0" max="100"></td>
                            <td>
                                <label class="form-check form-switch m-0">
                                    <input form="{{ $programFormId }}" type="checkbox" name="builders_risk_insurance" value="1" class="form-check-input" {{ $program->builders_risk_insurance ? 'checked' : '' }}>
                                </label>
                            </td>
                            <td><input form="{{ $programFormId }}" type="text" name="notes" class="form-control form-control-sm" value="{{ $program->notes }}"></td>
                            <td class="text-nowrap">
                                <button form="{{ $programFormId }}" type="submit" class="btn btn-sm btn-outline-primary">{{ __('Save') }}</button>
                                <form method="POST" action="{{ route('lenders.programs.destroy', $program) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this loan program?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-secondary py-3">{{ __('No loan programs added yet.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top">
                <form method="POST" action="{{ route('lenders.programs.store', $lender) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label required">{{ __('Program Name') }}</label>
                        <input type="text" name="program_name" class="form-control @error('program_name') is-invalid @enderror" value="{{ old('program_name') }}" required>
                        @error('program_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">{{ __('Rate %') }}</label>
                        <input type="number" name="interest_rate" class="form-control" value="{{ old('interest_rate') }}" step="0.01" min="0" max="100">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">{{ __('Points') }}</label>
                        <input type="number" name="points" class="form-control" value="{{ old('points') }}" step="0.01" min="0" max="100">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">{{ __('LTC %') }}</label>
                        <input type="number" name="max_ltc" class="form-control" value="{{ old('max_ltc') }}" step="0.01" min="0" max="100">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">{{ __('LTV %') }}</label>
                        <input type="number" name="max_ltv" class="form-control" value="{{ old('max_ltv') }}" step="0.01" min="0" max="100">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('Term Length') }}</label>
                        <input type="text" name="term_length" class="form-control" value="{{ old('term_length') }}" placeholder="{{ __('e.g., 12 months') }}">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">{{ __('Closing %') }}</label>
                        <input type="number" name="purchase_closing_cost_percent" class="form-control" value="{{ old('purchase_closing_cost_percent') }}" step="0.01" min="0" max="100">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">{{ __('Builder Risk') }}</label>
                        <label class="form-check form-switch">
                            <input type="checkbox" name="builders_risk_insurance" value="1" class="form-check-input" {{ old('builders_risk_insurance') ? 'checked' : '' }}>
                        </label>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">{{ __('Add') }}</button>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('Program Notes') }}</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ __('Attached Deals') }}</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>{{ __('Deal') }}</th>
                            <th>{{ __('Program') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lender->dealFundings as $funding)
                        <tr>
                            <td>
                                @if($funding->deal)
                                <a href="{{ route('deals.show', $funding->deal_id) }}" class="fw-bold">{{ $funding->deal->title ?? __('Deal') . ' #' . $funding->deal_id }}</a>
                                @if($funding->deal->lead)<div class="text-secondary small">{{ $funding->deal->lead->full_name }}</div>@endif
                                @else
                                {{ __('Deal') }} #{{ $funding->deal_id }}
                                @endif
                            </td>
                            <td>{{ $funding->loanProgram->program_name ?? '-' }}</td>
                            <td><span class="badge bg-blue-lt">{{ __(\App\Models\DealLender::STATUSES[$funding->status] ?? $funding->status) }}</span></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-secondary py-3">{{ __('Not attached to any deals yet.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
