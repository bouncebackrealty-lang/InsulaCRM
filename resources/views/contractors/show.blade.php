@extends('layouts.app')

@section('title', $contractor->name)
@section('page-title', $contractor->name)

@section('breadcrumbs')
<li class="breadcrumb-item"><a href="{{ route('contractors.index') }}">{{ __('Contractors') }}</a></li>
<li class="breadcrumb-item active" aria-current="page">{{ $contractor->name }}</li>
@endsection

@section('content')
<div class="row row-deck row-cards">
    {{-- Contractor Information --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ __('Contractor Details') }}</h3>
                <div class="card-actions">
                    <a href="{{ route('contractors.edit', $contractor) }}" class="btn btn-outline-primary btn-sm">{{ __('Edit') }}</a>
                </div>
            </div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">{{ __('Name') }}</div>
                        <div class="datagrid-content">{{ $contractor->name }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">{{ __('Phone') }}</div>
                        <div class="datagrid-content">@if($contractor->phone)<a href="tel:{{ $contractor->phone }}" class="text-reset">{{ $contractor->phone }}</a>@else - @endif</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">{{ __('Email') }}</div>
                        <div class="datagrid-content">@if($contractor->email)<a href="mailto:{{ $contractor->email }}" class="text-reset">{{ $contractor->email }}</a>@else - @endif</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">{{ __('Service Area') }}</div>
                        <div class="datagrid-content">{{ $contractor->service_area ?? '-' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">{{ __('Specialty') }}</div>
                        <div class="datagrid-content">
                            @forelse($contractor->specialty ?? [] as $trade)
                                <span class="badge bg-blue-lt me-1">{{ __(\App\Models\Contractor::TRADE_CATEGORIES[$trade] ?? $trade) }}</span>
                            @empty
                                <span class="text-secondary">-</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">{{ __('Priority') }}</div>
                        <div class="datagrid-content">
                            @php $prioColor = $contractor->priority === 'high' ? 'bg-red-lt' : ($contractor->priority === 'medium' ? 'bg-yellow-lt' : 'bg-secondary-lt'); @endphp
                            <span class="badge {{ $prioColor }}">{{ __(\App\Models\Contractor::PRIORITIES[$contractor->priority] ?? $contractor->priority) }}</span>
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">{{ __('Status') }}</div>
                        <div class="datagrid-content">
                            @php
                                $statusColor = match($contractor->status) {
                                    'hired' => 'bg-green-lt',
                                    'completed' => 'bg-teal-lt',
                                    'bid_submitted' => 'bg-blue-lt',
                                    default => 'bg-secondary-lt',
                                };
                            @endphp
                            <span class="badge {{ $statusColor }}">{{ __(\App\Models\Contractor::STATUSES[$contractor->status] ?? $contractor->status) }}</span>
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">{{ __('Referral / Source') }}</div>
                        <div class="datagrid-content">{{ $contractor->referral_source ?? '-' }}</div>
                    </div>
                </div>
                @if($contractor->notes)
                    <div class="mt-3 pt-3 border-top">
                        <div class="datagrid-title mb-2">{{ __('Notes') }}</div>
                        <div class="text-secondary" style="white-space: pre-wrap;">{{ $contractor->notes }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Attached Deals / Bids --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ __('Attached Deals & Bids') }}</h3>
                @if($contractor->dealBids->count())
                <div class="card-actions">
                    <span class="badge bg-blue-lt">{{ $contractor->dealBids->count() }}</span>
                </div>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>{{ __('Deal') }}</th>
                            <th>{{ __('Stage') }}</th>
                            <th>{{ __('Quoted') }}</th>
                            <th>{{ __('Accepted') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contractor->dealBids as $bid)
                        <tr>
                            <td>
                                @if($bid->deal)
                                <a href="{{ route('deals.show', $bid->deal_id) }}" class="fw-bold">{{ $bid->deal->title ?? __('Deal') . ' #' . $bid->deal_id }}</a>
                                @if($bid->deal->lead)
                                <div class="text-secondary small">{{ $bid->deal->lead->full_name }}</div>
                                @endif
                                @else
                                {{ __('Deal') }} #{{ $bid->deal_id }}
                                @endif
                            </td>
                            <td>@if($bid->deal)<span class="badge bg-primary-lt">{{ \App\Models\Deal::stageLabel($bid->deal->stage) }}</span>@else - @endif</td>
                            <td>{{ $bid->quoted_amount !== null ? Fmt::currency($bid->quoted_amount) : '-' }}</td>
                            <td>{{ $bid->accepted_amount !== null ? Fmt::currency($bid->accepted_amount) : '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-secondary py-3">{{ __('Not attached to any deals yet.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
