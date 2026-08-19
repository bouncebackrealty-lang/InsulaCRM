@extends('layouts.app')

@section('title', __('Lenders'))
@section('page-title', __('Lenders'))

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ __('Lenders') }}</h3>
        <div class="card-actions">
            <a href="{{ route('lenders.create') }}" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                {{ __('Add Lender') }}
            </a>
        </div>
    </div>
    <div class="card-body border-bottom py-3">
        <form method="GET" action="{{ route('lenders.index') }}" class="row g-2">
            <div class="col-md-10">
                <label for="lender-search" class="visually-hidden">{{ __('Search') }}</label>
                <input type="text" id="lender-search" name="search" class="form-control" placeholder="{{ __('Search name, company, email, phone, area...') }}" value="{{ request('search') }}">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-outline-primary w-100">{{ __('Filter') }}</button>
            </div>
            @if(request()->has('search'))
            <div class="col-md-1">
                <a href="{{ route('lenders.index') }}" class="btn btn-outline-secondary w-100">{{ __('Clear') }}</a>
            </div>
            @endif
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Company') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Service Area') }}</th>
                    <th>{{ __('Programs') }}</th>
                    <th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($lenders as $lender)
                <tr>
                    <td><a href="{{ route('lenders.show', $lender) }}">{{ $lender->name }}</a></td>
                    <td class="text-secondary">{{ $lender->company ?? '-' }}</td>
                    <td class="text-secondary">@if($lender->phone)<a href="tel:{{ $lender->phone }}" class="text-reset text-decoration-none">{{ $lender->phone }}</a>@else - @endif</td>
                    <td class="text-secondary">@if($lender->email)<a href="mailto:{{ $lender->email }}" class="text-reset text-decoration-none">{{ $lender->email }}</a>@else - @endif</td>
                    <td class="text-secondary">{{ $lender->service_area ?? '-' }}</td>
                    <td><span class="badge bg-blue-lt">{{ $lender->loan_programs_count }}</span></td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-ghost-secondary btn-icon" data-bs-toggle="dropdown" aria-label="{{ __('Actions for') }} {{ $lender->name }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/><circle cx="12" cy="5" r="1"/></svg>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="{{ route('lenders.show', $lender) }}">{{ __('View') }}</a>
                                <a class="dropdown-item" href="{{ route('lenders.edit', $lender) }}">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('lenders.destroy', $lender) }}" onsubmit="return confirm('{{ __('Delete this lender?') }}')">
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
                    <td colspan="7" class="text-center text-secondary py-4">
                        @if(request()->has('search'))
                            <p class="mb-2">{{ __('No lenders match your filters.') }}</p>
                            <a href="{{ route('lenders.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Clear Filters') }}</a>
                        @else
                            <p class="mb-2">{{ __('No lenders yet. Start building your lender database!') }}</p>
                            <a href="{{ route('lenders.create') }}" class="btn btn-primary btn-sm">{{ __('Add Lender') }}</a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex align-items-center">
        <p class="m-0 text-secondary">{{ __('Showing') }} <span>{{ $lenders->firstItem() ?? 0 }}</span> {{ __('to') }} <span>{{ $lenders->lastItem() ?? 0 }}</span> {{ __('of') }} <span>{{ $lenders->total() }}</span> {{ __('entries') }}</p>
        <div class="ms-auto">
            {{ $lenders->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
