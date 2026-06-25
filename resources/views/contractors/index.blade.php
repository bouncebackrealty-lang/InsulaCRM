@extends('layouts.app')

@section('title', __('Contractors'))
@section('page-title', __('Contractors'))

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ __('Contractors') }}</h3>
        <div class="card-actions">
            <a href="{{ route('contractors.export', request()->query()) }}" class="btn btn-outline-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/><polyline points="7 11 12 16 17 11"/><line x1="12" y1="4" x2="12" y2="16"/></svg>
                {{ __('Export CSV') }}
            </a>
            <a href="{{ route('contractors.create') }}" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                {{ __('Add Contractor') }}
            </a>
        </div>
    </div>
    <div class="card-body border-bottom py-2" id="bulk-actions-bar" style="display: none;">
        <form method="POST" action="{{ route('contractors.bulkAction') }}" id="bulk-form">
            @csrf
            <div class="d-flex align-items-center gap-2">
                <span id="selected-count" class="fw-bold text-muted">0</span>
                <span class="text-muted">{{ __('selected') }}</span>
                <div class="ms-3 d-flex gap-2">
                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('{{ __('Delete selected contractors?') }}')">{{ __('Delete') }}</button>
                </div>
            </div>
        </form>
    </div>
    <div class="card-body border-bottom py-3">
        <form method="GET" action="{{ route('contractors.index') }}" class="row g-2">
            <div class="col-md-4">
                <label for="contractor-search" class="visually-hidden">{{ __('Search') }}</label>
                <input type="text" id="contractor-search" name="search" class="form-control" placeholder="{{ __('Search name, email, phone, area...') }}" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="specialty" class="form-select">
                    <option value="">{{ __('All Specialties') }}</option>
                    @foreach(\App\Models\Contractor::TRADE_CATEGORIES as $val => $label)
                    <option value="{{ $val }}" {{ request('specialty') === $val ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="priority" class="form-select">
                    <option value="">{{ __('All Priorities') }}</option>
                    @foreach(\App\Models\Contractor::PRIORITIES as $val => $label)
                    <option value="{{ $val }}" {{ request('priority') === $val ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">{{ __('All Statuses') }}</option>
                    @foreach(\App\Models\Contractor::STATUSES as $val => $label)
                    <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-outline-primary w-100">{{ __('Filter') }}</button>
            </div>
            @if(request()->hasAny(['search', 'specialty', 'priority', 'status']))
            <div class="col-md-1">
                <a href="{{ route('contractors.index') }}" class="btn btn-outline-secondary w-100">{{ __('Clear') }}</a>
            </div>
            @endif
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th class="w-1"><input type="checkbox" id="select-all" class="form-check-input" aria-label="{{ __('Select all contractors') }}"></th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Specialty') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Service Area') }}</th>
                    <th>{{ __('Priority') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($contractors as $contractor)
                <tr>
                    <td><input type="checkbox" class="form-check-input contractor-checkbox" value="{{ $contractor->id }}" aria-label="{{ __('Select') }} {{ $contractor->name }}"></td>
                    <td><a href="{{ route('contractors.show', $contractor) }}">{{ $contractor->name }}</a></td>
                    <td>
                        @forelse($contractor->specialty ?? [] as $trade)
                            <span class="badge bg-blue-lt me-1">{{ __(\App\Models\Contractor::TRADE_CATEGORIES[$trade] ?? $trade) }}</span>
                        @empty
                            <span class="text-secondary">-</span>
                        @endforelse
                    </td>
                    <td class="text-secondary">@if($contractor->phone)<a href="tel:{{ $contractor->phone }}" class="text-reset text-decoration-none">{{ $contractor->phone }}</a>@else - @endif</td>
                    <td class="text-secondary">@if($contractor->email)<a href="mailto:{{ $contractor->email }}" class="text-reset text-decoration-none">{{ $contractor->email }}</a>@else - @endif</td>
                    <td class="text-secondary">{{ $contractor->service_area ?? '-' }}</td>
                    <td>
                        @php
                            $prio = $contractor->priority;
                            $prioColor = $prio === 'high' ? 'bg-red-lt' : ($prio === 'medium' ? 'bg-yellow-lt' : 'bg-secondary-lt');
                        @endphp
                        <span class="badge {{ $prioColor }}">{{ __(\App\Models\Contractor::PRIORITIES[$prio] ?? $prio) }}</span>
                    </td>
                    <td>
                        @php
                            $status = $contractor->status;
                            $statusColor = match($status) {
                                'hired' => 'bg-green-lt',
                                'completed' => 'bg-teal-lt',
                                'bid_submitted' => 'bg-blue-lt',
                                default => 'bg-secondary-lt',
                            };
                        @endphp
                        <span class="badge {{ $statusColor }}">{{ __(\App\Models\Contractor::STATUSES[$status] ?? $status) }}</span>
                    </td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-ghost-secondary btn-icon" data-bs-toggle="dropdown" aria-label="{{ __('Actions for') }} {{ $contractor->name }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/><circle cx="12" cy="5" r="1"/></svg>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="{{ route('contractors.show', $contractor) }}">{{ __('View') }}</a>
                                <a class="dropdown-item" href="{{ route('contractors.edit', $contractor) }}">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('contractors.destroy', $contractor) }}" onsubmit="return confirm('{{ __('Delete this contractor?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-secondary py-4">
                        @if(request()->hasAny(['search', 'specialty', 'priority', 'status']))
                            <p class="mb-2">{{ __('No contractors match your filters.') }}</p>
                            <a href="{{ route('contractors.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Clear Filters') }}</a>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg text-secondary mb-2" width="40" height="40" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21h18"/><path d="M5 21v-14l8 -4v18"/><path d="M19 21v-10l-6 -4"/><path d="M9 9v0"/><path d="M9 12v0"/><path d="M9 15v0"/><path d="M9 18v0"/></svg>
                            <p class="mb-2">{{ __('No contractors yet. Start building your contractor database!') }}</p>
                            <a href="{{ route('contractors.create') }}" class="btn btn-primary btn-sm">{{ __('Add Contractor') }}</a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex align-items-center">
        <p class="m-0 text-secondary">{{ __('Showing') }} <span>{{ $contractors->firstItem() ?? 0 }}</span> {{ __('to') }} <span>{{ $contractors->lastItem() ?? 0 }}</span> {{ __('of') }} <span>{{ $contractors->total() }}</span> {{ __('entries') }}</p>
        <div class="ms-auto">
            {{ $contractors->withQueryString()->links() }}
        </div>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('select-all');
    const bulkBar = document.getElementById('bulk-actions-bar');
    const bulkForm = document.getElementById('bulk-form');
    const countSpan = document.getElementById('selected-count');

    function updateBulkBar() {
        const checked = document.querySelectorAll('.contractor-checkbox:checked');
        countSpan.textContent = checked.length;
        bulkBar.style.display = checked.length > 0 ? '' : 'none';

        bulkForm.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            bulkForm.appendChild(input);
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.contractor-checkbox').forEach(cb => cb.checked = this.checked);
            updateBulkBar();
        });
    }

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('contractor-checkbox')) {
            updateBulkBar();
            if (!e.target.checked && selectAll) selectAll.checked = false;
        }
    });
});
</script>
@endpush
@endsection
