@extends('layouts.app')

@section('title', __('Pipeline'))
@section('page-title', __('Pipeline'))

@php
    $formatMoney = fn ($value) => $value !== null && $value !== '' ? Fmt::currency($value, 0) : '-';
    $stageColors = [
        'prospecting' => 'secondary',
        'contacting' => 'cyan',
        'engaging' => 'blue',
        'offer_presented' => 'orange',
        'under_contract' => 'primary',
        'inspection' => 'green',
        'under_inspection' => 'green',
        'dispositions' => 'purple',
        'assigned' => 'indigo',
        'closing' => 'yellow',
        'closed_won' => 'green',
        'closed_lost' => 'red',
    ];
    $buildViewUrl = fn ($view) => request()->fullUrlWithQuery(['view' => $view]);
@endphp

@push('styles')
<style>
    .pipeline-shell {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .pipeline-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }

    .pipeline-title {
        margin: 0;
        font-size: 1.45rem;
        font-weight: 700;
        color: #101828;
    }

    .pipeline-subtitle {
        margin: 4px 0 0;
        color: #667085;
        font-size: 0.9rem;
    }

    .pipeline-summary-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(150px, 1fr));
        gap: 12px;
    }

    .pipeline-summary-card {
        border: 1px solid #e6e7e9;
        border-radius: 7px;
        background: #fff;
        padding: 14px;
        min-height: 86px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .summary-icon {
        width: 42px;
        height: 42px;
        border-radius: 7px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        flex: 0 0 auto;
    }

    .summary-icon.blue { background: #206bc4; }
    .summary-icon.green { background: #2fb344; }
    .summary-icon.orange { background: #f59f00; }
    .summary-icon.purple { background: #ae3ec9; }
    .summary-icon.teal { background: #0ca678; }

    .summary-value {
        font-size: 1.3rem;
        line-height: 1;
        font-weight: 700;
        color: #101828;
    }

    .summary-label {
        margin-top: 4px;
        color: #344054;
        font-size: 0.82rem;
    }

    .summary-money {
        color: #667085;
        font-size: 0.78rem;
        margin-top: 2px;
    }

    .pipeline-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .pipeline-filter-form {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
        flex: 1 1 auto;
    }

    .pipeline-filter-form .form-control,
    .pipeline-filter-form .form-select {
        min-width: 165px;
        max-width: 230px;
    }

    .pipeline-view-toggle {
        display: inline-flex;
        gap: 8px;
    }

    .pipeline-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 14px;
    }

    .pipeline-deal-card {
        position: relative;
        border: 1px solid #e6e7e9;
        border-radius: 7px;
        background: #fff;
        transition: box-shadow 0.15s, transform 0.15s;
    }

    .pipeline-deal-card:hover {
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.1);
        transform: translateY(-1px);
    }

    /* Raise the card above its neighbours while its stage menu is open so the
       dropdown is not painted underneath the following cards. */
    .pipeline-deal-card.stage-menu-open {
        z-index: 30;
    }

    .pipeline-card-photo {
        position: relative;
        height: 150px;
        background: #eef2f7;
        border-top-left-radius: 7px;
        border-top-right-radius: 7px;
        overflow: hidden;
    }

    .pipeline-card-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .pipeline-photo-fallback {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #667085;
        background: linear-gradient(135deg, #eef2f7, #dce5ef);
    }

    .pipeline-stage-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        color: #fff;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.16);
    }

    .priority-star {
        position: absolute;
        top: 9px;
        right: 9px;
        width: 32px;
        height: 32px;
        border: 1px solid rgba(255,255,255,0.75);
        border-radius: 50%;
        background: rgba(15, 23, 42, 0.38);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 2;
    }

    .priority-star.is-priority {
        background: #f59f00;
        border-color: #f59f00;
        color: #fff;
    }

    .pipeline-card-body {
        padding: 12px;
    }

    .pipeline-card-title-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 2px;
    }

    .pipeline-card-title {
        color: #101828;
        font-weight: 700;
        line-height: 1.25;
        text-decoration: none;
    }

    .pipeline-card-title:hover {
        color: #206bc4;
        text-decoration: none;
    }

    .pipeline-card-price {
        color: #101828;
        font-weight: 700;
        text-align: right;
        white-space: nowrap;
    }

    .pipeline-card-address {
        color: #475467;
        font-size: 0.82rem;
        margin-bottom: 12px;
    }

    .pipeline-number-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0;
        border-top: 1px solid #edf0f3;
        border-bottom: 1px solid #edf0f3;
        margin-bottom: 12px;
    }

    .pipeline-number {
        padding: 9px 8px;
        border-right: 1px solid #edf0f3;
        min-width: 0;
    }

    .pipeline-number:last-child {
        border-right: 0;
    }

    .pipeline-number-label,
    .pipeline-meta-label {
        color: #667085;
        font-size: 0.7rem;
        line-height: 1.2;
        margin-bottom: 4px;
    }

    .pipeline-number-value,
    .pipeline-meta-value {
        color: #101828;
        font-size: 0.82rem;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .pipeline-number-value.mao {
        color: #087f5b;
    }

    .pipeline-meta-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-bottom: 12px;
    }

    .pipeline-card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        color: #475467;
        font-size: 0.78rem;
        border-top: 1px solid #edf0f3;
        padding-top: 10px;
    }

    .pipeline-list-card {
        border: 1px solid #e6e7e9;
        border-radius: 7px;
        background: #fff;
        overflow: hidden;
    }

    .pipeline-table {
        margin-bottom: 0;
        min-width: 980px;
    }

    .pipeline-table th {
        color: #667085;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0;
    }

    .pipeline-empty {
        border: 1px dashed #cfd7df;
        border-radius: 7px;
        padding: 30px;
        text-align: center;
        color: #667085;
        background: #fff;
    }

    @media (max-width: 1200px) {
        .pipeline-summary-grid {
            grid-template-columns: repeat(3, minmax(150px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .pipeline-header {
            flex-direction: column;
            align-items: stretch;
        }

        .pipeline-header .btn {
            width: 100%;
        }

        .pipeline-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pipeline-filter-form,
        .pipeline-filter-form .form-control,
        .pipeline-filter-form .form-select,
        .pipeline-view-toggle,
        .pipeline-view-toggle .btn {
            width: 100%;
            max-width: none;
        }

        .pipeline-view-toggle {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 560px) {
        .pipeline-summary-grid,
        .pipeline-card-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="pipeline-shell">
    <div class="pipeline-header">
        <div>
            <h1 class="pipeline-title">{{ __('Pipeline') }}</h1>
            <p class="pipeline-subtitle">{{ __('Track and manage your deals') }}</p>
        </div>
        <a href="{{ route('leads.create') }}" class="btn btn-primary" data-testid="pipeline-add-deal">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            {{ __('Add Deal') }}
        </a>
    </div>

    <div class="pipeline-summary-grid" data-testid="pipeline-summary-bar">
        <div class="pipeline-summary-card">
            <span class="summary-icon blue">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="22" height="22" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/></svg>
            </span>
            <div>
                <div class="summary-value">{{ $summary['total'] }}</div>
                <div class="summary-label">{{ __('Total Deals') }}</div>
            </div>
        </div>
        <div class="pipeline-summary-card">
            <span class="summary-icon green">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="22" height="22" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M9 11l3 3l8 -8"/><path d="M20 12v6a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h9"/></svg>
            </span>
            <div>
                <div class="summary-value">{{ $summary['in_contract'] }}</div>
                <div class="summary-label">{{ __('In Contract') }}</div>
                <div class="summary-money">{{ $formatMoney($summary['in_contract_value']) }}</div>
            </div>
        </div>
        <div class="pipeline-summary-card">
            <span class="summary-icon orange">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="22" height="22" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M21 21l-6 -6"/></svg>
            </span>
            <div>
                <div class="summary-value">{{ $summary['mid_stage'] }}</div>
                <div class="summary-label">{{ $summary['mid_stage_label'] }}</div>
                <div class="summary-money">{{ $formatMoney($summary['mid_stage_value']) }}</div>
            </div>
        </div>
        <div class="pipeline-summary-card">
            <span class="summary-icon purple">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="22" height="22" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 8v4l3 3"/><path d="M3.05 11a9 9 0 1 1 .5 4"/></svg>
            </span>
            <div>
                <div class="summary-value">{{ $summary['pending_close'] }}</div>
                <div class="summary-label">{{ __('Pending Close') }}</div>
                <div class="summary-money">{{ $formatMoney($summary['pending_close_value']) }}</div>
            </div>
        </div>
        <div class="pipeline-summary-card">
            <span class="summary-icon teal">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="22" height="22" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 3l8 4.5v9l-8 4.5l-8 -4.5v-9z"/><path d="M12 12l8 -4.5"/><path d="M12 12v9"/><path d="M12 12l-8 -4.5"/></svg>
            </span>
            <div>
                <div class="summary-value">{{ $summary['closed_month'] }}</div>
                <div class="summary-label">{{ __('Closed This Month') }}</div>
                <div class="summary-money">{{ $formatMoney($summary['closed_month_value']) }}</div>
            </div>
        </div>
    </div>

    <div class="pipeline-toolbar">
        <form method="GET" action="{{ route('pipeline') }}" class="pipeline-filter-form" data-testid="pipeline-filter-form">
            <input type="hidden" name="view" value="{{ $viewMode }}">
            <label for="pipeline-stage-filter" class="visually-hidden">{{ __('Stage') }}</label>
            <select id="pipeline-stage-filter" name="stage" class="form-select" data-testid="pipeline-stage-filter" onchange="this.form.submit()">
                <option value="">{{ __('All Stages') }}</option>
                @foreach($stages as $stageKey => $stageLabel)
                    <option value="{{ $stageKey }}" @selected(request('stage') === $stageKey)>{{ $stageLabel }}</option>
                @endforeach
            </select>

            <label for="pipeline-deal-type-filter" class="visually-hidden">{{ __('Deal Type') }}</label>
            <select id="pipeline-deal-type-filter" name="deal_type" class="form-select" data-testid="pipeline-deal-type-filter" onchange="this.form.submit()">
                <option value="">{{ __('All Deal Types') }}</option>
                @foreach($dealTypes as $typeKey => $typeLabel)
                    <option value="{{ $typeKey }}" @selected(request('deal_type') === $typeKey)>{{ $typeLabel }}</option>
                @endforeach
            </select>

            <label for="pipeline-lender-filter" class="visually-hidden">{{ __('Lender') }}</label>
            <select id="pipeline-lender-filter" name="lender" class="form-select" data-testid="pipeline-lender-filter" onchange="this.form.submit()">
                <option value="">{{ __('All Lenders') }}</option>
                @foreach($lenders as $lender)
                    <option value="{{ $lender->id }}" @selected((string) request('lender') === (string) $lender->id)>{{ $lender->company ?: $lender->name }}</option>
                @endforeach
            </select>

            <div class="input-icon">
                <span class="input-icon-addon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M21 21l-6 -6"/></svg>
                </span>
                <label for="pipeline-search" class="visually-hidden">{{ __('Search') }}</label>
                <input id="pipeline-search" type="search" name="search" class="form-control" placeholder="{{ __('Search address, seller, deal...') }}" value="{{ request('search') }}" data-testid="pipeline-search">
            </div>

            <button type="submit" class="btn btn-outline-primary">{{ __('Search') }}</button>
            @if(request()->hasAny(['stage', 'deal_type', 'lender', 'search']))
                <a href="{{ route('pipeline', ['view' => $viewMode]) }}" class="btn btn-ghost-secondary">{{ __('Clear') }}</a>
            @endif
        </form>

        <div class="pipeline-view-toggle" data-testid="pipeline-view-toggle">
            <a href="{{ $buildViewUrl('card') }}" class="btn {{ $viewMode === 'card' ? 'btn-primary' : 'btn-outline-secondary' }}" data-testid="pipeline-card-view">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M4 4h6v6h-6z"/><path d="M14 4h6v6h-6z"/><path d="M4 14h6v6h-6z"/><path d="M14 14h6v6h-6z"/></svg>
                {{ __('Card View') }}
            </a>
            <a href="{{ $buildViewUrl('list') }}" class="btn {{ $viewMode === 'list' ? 'btn-primary' : 'btn-outline-secondary' }}" data-testid="pipeline-list-view">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M5 6h.01"/><path d="M5 12h.01"/><path d="M5 18h.01"/></svg>
                {{ __('List View') }}
            </a>
        </div>
    </div>

    @if($deals->isEmpty())
        <div class="pipeline-empty" data-testid="pipeline-empty">{{ __('No deals match the selected filters.') }}</div>
    @elseif($viewMode === 'list')
        <div class="pipeline-list-card table-responsive" data-testid="pipeline-list-results">
            <table class="table table-vcenter pipeline-table">
                <thead>
                    <tr>
                        <th>{{ __('Priority') }}</th>
                        <th>{{ __('Property') }}</th>
                        <th>{{ __('Stage') }}</th>
                        <th>{{ __('Asking') }}</th>
                        <th>{{ __('Contract') }}</th>
                        <th>{{ __('ARV') }}</th>
                        <th>{{ __('Max Offer / MAO') }}</th>
                        <th>{{ __('Lender') }}</th>
                        <th>{{ __('Deal Type') }}</th>
                        <th>{{ __('Days in Stage') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($deals as $deal)
                        @php
                            $property = $deal->lead?->property;
                            $daysInStage = $deal->stage_changed_at ? (int) now()->diffInDays($deal->stage_changed_at, true) : 0;
                            $stageColor = $stageColors[$deal->stage] ?? 'secondary';
                            $lenderName = $deal->lenders->first()?->lender?->company ?: $deal->lenders->first()?->lender?->name;
                        @endphp
                        <tr data-testid="pipeline-list-row">
                            <td>
                                <button type="button" class="priority-star position-static {{ $deal->is_priority ? 'is-priority' : '' }}" data-deal-id="{{ $deal->id }}" data-priority-url="{{ route('deals.togglePriority', $deal) }}" aria-label="{{ __('Toggle priority') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="17" height="17" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="{{ $deal->is_priority ? 'currentColor' : 'none' }}"><path d="M12 17.75l-6.172 3.245l1.179 -6.873l-4.993 -4.867l6.9 -1.002l3.086 -6.253l3.086 6.253l6.9 1.002l-4.993 4.867l1.179 6.873z"/></svg>
                                </button>
                            </td>
                            <td>
                                <a href="{{ route('deals.show', $deal) }}" class="fw-bold text-reset" title="{{ $deal->title }}">{{ $property?->address ?? $deal->lead?->full_name ?? $deal->title }}</a>
                                <div class="text-secondary small">{{ trim(($property?->city ? $property->city . ', ' : '') . ($property?->state ?? '') . ' ' . ($property?->zip_code ?? '')) }}</div>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <a href="#" class="badge bg-{{ $stageColor }} text-decoration-none" data-bs-toggle="dropdown" title="{{ __('Move to stage') }}">{{ $stages[$deal->stage] ?? $deal->stage }}</a>
                                    <div class="dropdown-menu">
                                        <span class="dropdown-header">{{ __('Move to stage') }}</span>
                                        @foreach($stages as $moveStageKey => $moveStageLabel)
                                            @if($moveStageKey !== $deal->stage)
                                                <a href="#" class="dropdown-item move-stage-btn" data-stage="{{ $moveStageKey }}" data-stage-url="{{ route('deals.updateStage', $deal) }}">{{ $moveStageLabel }}</a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                            <td>{{ $formatMoney($property?->asking_price) }}</td>
                            <td>{{ $formatMoney($deal->contract_price) }}</td>
                            <td>{{ $formatMoney($property?->after_repair_value) }}</td>
                            <td class="text-success fw-bold">{{ $formatMoney($property?->mao ?? $property?->maximum_allowable_offer) }}</td>
                            <td>{{ $lenderName ?: '-' }}</td>
                            <td>{{ $deal->deal_type_label }}</td>
                            <td>{{ $daysInStage }} {{ __('days') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="pipeline-card-grid" data-testid="pipeline-card-results">
            @foreach($deals as $deal)
                @php
                    $property = $deal->lead?->property;
                    $photo = $deal->lead?->photos?->sortBy('created_at')->first();
                    $daysInStage = $deal->stage_changed_at ? (int) now()->diffInDays($deal->stage_changed_at, true) : 0;
                    $stageColor = $stageColors[$deal->stage] ?? 'secondary';
                    $lenderName = $deal->lenders->first()?->lender?->company ?: $deal->lenders->first()?->lender?->name;
                    $displayDateLabel = $deal->stage === 'closed_won' ? __('Closed') : ($deal->contract_date ? __('Contract') : __('Added'));
                    $displayDate = $deal->stage === 'closed_won'
                        ? ($deal->closing_date ?: $deal->stage_changed_at)
                        : ($deal->contract_date ?: $deal->created_at);
                @endphp
                <article class="pipeline-deal-card" data-testid="pipeline-deal-card" data-deal-id="{{ $deal->id }}">
                    <div class="pipeline-card-photo">
                        @if($photo)
                            <img src="{{ $photo->thumbnail_url }}" alt="{{ $photo->caption ?: $photo->original_name }}">
                        @else
                            <div class="pipeline-photo-fallback" data-testid="pipeline-photo-fallback">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="42" height="42" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" fill="none"><path d="M3 21h18"/><path d="M5 21v-14l8 -4v18"/><path d="M19 21v-10l-6 -4"/><path d="M9 9v.01"/><path d="M9 12v.01"/><path d="M9 15v.01"/></svg>
                            </div>
                        @endif
                        <span class="badge bg-{{ $stageColor }} pipeline-stage-badge" data-testid="pipeline-stage-badge">{{ $stages[$deal->stage] ?? $deal->stage }}</span>
                        <button type="button" class="priority-star {{ $deal->is_priority ? 'is-priority' : '' }}" data-testid="pipeline-priority-star" data-deal-id="{{ $deal->id }}" data-priority-url="{{ route('deals.togglePriority', $deal) }}" aria-label="{{ __('Toggle priority') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="17" height="17" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="{{ $deal->is_priority ? 'currentColor' : 'none' }}"><path d="M12 17.75l-6.172 3.245l1.179 -6.873l-4.993 -4.867l6.9 -1.002l3.086 -6.253l3.086 6.253l6.9 1.002l-4.993 4.867l1.179 6.873z"/></svg>
                        </button>
                    </div>
                    <div class="pipeline-card-body">
                        <div class="pipeline-card-title-row">
                            <div>
                                <a href="{{ route('deals.show', $deal) }}" class="pipeline-card-title" title="{{ $deal->title }}">{{ $property?->address ?? $deal->lead?->full_name ?? $deal->title }}</a>
                                <div class="pipeline-card-address">{{ trim(($property?->city ? $property->city . ', ' : '') . ($property?->state ?? '') . ' ' . ($property?->zip_code ?? '')) }}</div>
                            </div>
                            <div class="pipeline-card-price">
                                {{ $formatMoney($property?->asking_price) }}
                                <div class="pipeline-number-label">{{ __('Asking Price') }}</div>
                            </div>
                        </div>

                        <div class="pipeline-number-grid">
                            <div class="pipeline-number">
                                <div class="pipeline-number-label">{{ __('Contract Price') }}</div>
                                <div class="pipeline-number-value">{{ $formatMoney($deal->contract_price) }}</div>
                            </div>
                            <div class="pipeline-number">
                                <div class="pipeline-number-label">{{ __('ARV') }}</div>
                                <div class="pipeline-number-value">{{ $formatMoney($property?->after_repair_value) }}</div>
                            </div>
                            <div class="pipeline-number">
                                <div class="pipeline-number-label">{{ __('Max Offer / MAO') }}</div>
                                <div class="pipeline-number-value mao">{{ $formatMoney($property?->mao ?? $property?->maximum_allowable_offer) }}</div>
                            </div>
                        </div>

                        <div class="pipeline-meta-grid">
                            <div>
                                <div class="pipeline-meta-label">{{ __('Lender') }}</div>
                                <div class="pipeline-meta-value">{{ $lenderName ?: '-' }}</div>
                            </div>
                            <div>
                                <div class="pipeline-meta-label">{{ __('Deal Type') }}</div>
                                <div class="pipeline-meta-value">{{ $deal->deal_type_label }}</div>
                            </div>
                        </div>

                        <div class="pipeline-card-footer">
                            <span>{{ $displayDateLabel }}: {{ $displayDate ? Fmt::date($displayDate) : '-' }}</span>
                            <span>{{ $daysInStage }} {{ __('Days in Stage') }}</span>
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-ghost-secondary btn-icon" data-bs-toggle="dropdown" aria-label="{{ __('Move to stage') }}" data-testid="pipeline-move-stage">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M12 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M12 19m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M12 5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/></svg>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <span class="dropdown-header">{{ __('Move to stage') }}</span>
                                    @foreach($stages as $moveStageKey => $moveStageLabel)
                                        @if($moveStageKey !== $deal->stage)
                                            <a href="#" class="dropdown-item move-stage-btn" data-stage="{{ $moveStageKey }}" data-stage-url="{{ route('deals.updateStage', $deal) }}">{{ $moveStageLabel }}</a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    document.querySelectorAll('.priority-star').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            var url = button.dataset.priorityUrl;
            if (!url) return;

            button.disabled = true;
            fetch(url, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({})
            })
            .then(function (response) {
                if (!response.ok) throw new Error('Priority update failed');
                return response.json();
            })
            .then(function (data) {
                var matching = document.querySelectorAll('.priority-star[data-deal-id="' + button.dataset.dealId + '"]');
                matching.forEach(function (star) {
                    star.classList.toggle('is-priority', !!data.is_priority);
                    var icon = star.querySelector('svg');
                    if (icon) {
                        icon.setAttribute('fill', data.is_priority ? 'currentColor' : 'none');
                    }
                });
            })
            .catch(function () {
                if (window.showToast) {
                    window.showToast('{{ __('Unable to update priority. Please try again.') }}', 'error');
                }
            })
            .finally(function () {
                button.disabled = false;
            });
        });
    });

    document.querySelectorAll('.pipeline-deal-card .dropdown').forEach(function (dropdown) {
        var toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
        var card = dropdown.closest('.pipeline-deal-card');
        if (!toggle || !card) return;

        toggle.addEventListener('show.bs.dropdown', function () {
            card.classList.add('stage-menu-open');
        });
        toggle.addEventListener('hide.bs.dropdown', function () {
            card.classList.remove('stage-menu-open');
        });
    });

    // List view lives inside a horizontally-scrollable table wrapper, which
    // clips absolutely-positioned menus. Use Popper's fixed strategy so the
    // stage dropdown floats above the table instead of being cut off.
    if (window.bootstrap && bootstrap.Dropdown) {
        document.querySelectorAll('.pipeline-table [data-bs-toggle="dropdown"]').forEach(function (toggle) {
            bootstrap.Dropdown.getOrCreateInstance(toggle, {
                popperConfig: function (defaultConfig) {
                    return Object.assign({}, defaultConfig, { strategy: 'fixed' });
                }
            });
        });
    }

    document.querySelectorAll('.move-stage-btn').forEach(function (item) {
        item.addEventListener('click', function (event) {
            event.preventDefault();

            fetch(item.dataset.stageUrl, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ stage: item.dataset.stage })
            })
            .then(function (response) {
                if (!response.ok) throw new Error('Stage update failed');
                window.location.reload();
            })
            .catch(function () {
                if (window.showToast) {
                    window.showToast('{{ __('Unable to move deal. Please try again.') }}', 'error');
                }
            });
        });
    });
});
</script>
@endpush
