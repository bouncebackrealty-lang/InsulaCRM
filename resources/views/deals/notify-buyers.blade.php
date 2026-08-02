@extends('layouts.app')

@section('title', __('Notify Buyers'))
@section('page-title', __('Notify Buyers'))

@section('breadcrumbs')
<li class="breadcrumb-item"><a href="{{ route('pipeline') }}">{{ __('Pipeline') }}</a></li>
<li class="breadcrumb-item"><a href="{{ route('deals.show', $deal) }}">{{ $deal->lead->full_name ?? $deal->title }}</a></li>
<li class="breadcrumb-item active" aria-current="page">{{ __('Notify Buyers') }}</li>
@endsection

@section('content')
@php
    $property = $deal->lead?->property;
    $matchedBuyers = $deal->buyerMatches->filter(fn ($match) => filter_var($match->buyer?->email, FILTER_VALIDATE_EMAIL));
    $selectedBuyerIds = collect(old('buyer_ids', $selectedBuyerId ? [$selectedBuyerId] : []))->map(fn ($id) => (int) $id)->all();
    $selectedTarget = old('recipient_target', $selectedBuyerId ? 'manual' : ($matchedBuyers->isNotEmpty() ? 'matched' : 'manual'));
@endphp

<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="alert alert-info">
            <strong>{{ __('Deal notification') }}</strong><br>
            {{ __('This notification sends immediately to the selected recipients.') }}
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-secondary small">{{ __('Deal') }}</div>
                        <div class="fw-bold">{{ $deal->title }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-secondary small">{{ __('Property') }}</div>
                        <div class="fw-bold">{{ $property?->full_address ?: __('No property details entered') }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-secondary small">{{ __('Deal Type') }}</div>
                        <div>{{ $deal->deal_type_label }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-secondary small">{{ __('Email template') }}</div>
                        <div>{{ __('Transactional template') }} #{{ config('services.brevo.transactional_template_id') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('deals.notifyBuyers.store', $deal) }}" id="buyer-notify-form">
            @csrf
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('Choose recipients') }}</h3>
                </div>
                <div class="card-body">
                    <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column gap-2">
                        <label class="form-selectgroup-item flex-fill">
                            <input type="radio" name="recipient_target" value="matched" class="form-selectgroup-input" {{ $selectedTarget === 'matched' ? 'checked' : '' }} {{ $matchedBuyers->isEmpty() ? 'disabled' : '' }}>
                            <span class="form-selectgroup-label d-flex align-items-center p-3">
                                <span class="me-3"><span class="form-selectgroup-check"></span></span>
                                <span>
                                    <span class="form-selectgroup-title fw-bold">{{ __('CRM-matched buyers') }}</span>
                                    <span class="d-block text-secondary">{{ __('Use the current CRM matching rules.') }} {{ $matchedBuyers->count() }} {{ __('buyer(s) with an email address') }}.</span>
                                </span>
                            </span>
                        </label>

                        <label class="form-selectgroup-item flex-fill">
                            <input type="radio" name="recipient_target" value="manual" class="form-selectgroup-input" {{ $selectedTarget === 'manual' ? 'checked' : '' }}>
                            <span class="form-selectgroup-label d-flex align-items-center p-3">
                                <span class="me-3"><span class="form-selectgroup-check"></span></span>
                                <span>
                                    <span class="form-selectgroup-title fw-bold">{{ __('Manual CRM Buyer Database selection') }}</span>
                                    <span class="d-block text-secondary">{{ __('Select All includes the live CRM buyer list. You can deselect individual buyers before sending.') }}</span>
                                </span>
                            </span>
                        </label>

                        <div class="border rounded p-3 ms-md-4" id="manual-buyer-panel">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <span class="fw-bold">{{ __('CRM buyers') }}</span>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="select-all-buyers">{{ __('Select All') }}</button>
                            </div>
                            @if($buyers->isEmpty())
                                <p class="text-secondary mb-0">{{ __('There are no buyers in the CRM Buyer Database yet.') }}</p>
                            @else
                                <div class="list-group list-group-flush border rounded" style="max-height: 320px; overflow-y: auto;">
                                    @foreach($buyers as $buyer)
                                        @php($hasEmail = filter_var($buyer->email, FILTER_VALIDATE_EMAIL))
                                        <label class="list-group-item d-flex align-items-center gap-2 {{ $hasEmail ? '' : 'text-secondary' }}">
                                            <input class="form-check-input m-0 buyer-selection" type="checkbox" name="buyer_ids[]" value="{{ $buyer->id }}" {{ in_array($buyer->id, $selectedBuyerIds, true) ? 'checked' : '' }} {{ $hasEmail ? '' : 'disabled' }}>
                                            <span class="flex-fill">
                                                <span class="fw-medium">{{ $buyer->company ?: $buyer->full_name }}</span>
                                                <span class="d-block small">{{ $buyer->email ?: __('No usable email - excluded') }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <label class="form-selectgroup-item flex-fill">
                            <input type="radio" name="recipient_target" value="brevo_city" class="form-selectgroup-input" {{ $selectedTarget === 'brevo_city' ? 'checked' : '' }} {{ blank($property?->city) ? 'disabled' : '' }}>
                            <span class="form-selectgroup-label d-flex align-items-center p-3">
                                <span class="me-3"><span class="form-selectgroup-check"></span></span>
                                <span>
                                    <span class="form-selectgroup-title fw-bold">{{ __('City audience') }}</span>
                                    <span class="d-block text-secondary">{{ __('Use contacts where City matches') }}: {{ $property?->city ?: __('City required') }}.</span>
                                </span>
                            </span>
                        </label>

                        <label class="form-selectgroup-item flex-fill">
                            <input type="radio" name="recipient_target" value="brevo_deal_type" class="form-selectgroup-input" {{ $selectedTarget === 'brevo_deal_type' ? 'checked' : '' }} {{ $deal->deal_type ? '' : 'disabled' }}>
                            <span class="form-selectgroup-label d-flex align-items-center p-3">
                                <span class="me-3"><span class="form-selectgroup-check"></span></span>
                                <span>
                                    <span class="form-selectgroup-title fw-bold">{{ __('Deal Type audience') }}</span>
                                    <span class="d-block text-secondary">{{ __('Use contacts where Deal Type matches') }}: {{ $deal->deal_type_label }}.</span>
                                </span>
                            </span>
                        </label>

                        <label class="form-selectgroup-item flex-fill">
                            <input type="radio" name="recipient_target" value="brevo_city_and_deal_type" class="form-selectgroup-input" {{ $selectedTarget === 'brevo_city_and_deal_type' ? 'checked' : '' }} {{ blank($property?->city) || ! $deal->deal_type ? 'disabled' : '' }}>
                            <span class="form-selectgroup-label d-flex align-items-center p-3">
                                <span class="me-3"><span class="form-selectgroup-check"></span></span>
                                <span>
                                    <span class="form-selectgroup-title fw-bold">{{ __('City + Deal Type audience') }}</span>
                                    <span class="d-block text-secondary">{{ __('Use contacts that match both current deal values.') }}</span>
                                </span>
                            </span>
                        </label>
                    </div>

                    @error('recipient_target')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    @error('buyer_ids')<div class="text-danger small mt-2">{{ $message }}</div>@enderror

                    <label class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="confirm_send" value="1" {{ old('confirm_send') ? 'checked' : '' }} required>
                        <span class="form-check-label">{{ __('I confirm that the selected recipients should receive this deal notification now.') }}</span>
                    </label>
                    @error('confirm_send')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </div>
                <div class="card-footer d-flex justify-content-between gap-2">
                    <a href="{{ route('deals.show', $deal) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-success">{{ __('Send notification') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const manualTarget = document.querySelector('input[name="recipient_target"][value="manual"]');
    const selectAllButton = document.getElementById('select-all-buyers');
    const checkboxes = Array.from(document.querySelectorAll('.buyer-selection:not(:disabled)'));

    selectAllButton?.addEventListener('click', function () {
        manualTarget.checked = true;
        const select = checkboxes.some(function (checkbox) { return !checkbox.checked; });
        checkboxes.forEach(function (checkbox) { checkbox.checked = select; });
        selectAllButton.textContent = select ? '{{ __('Deselect All') }}' : '{{ __('Select All') }}';
    });
});
</script>
@endpush
