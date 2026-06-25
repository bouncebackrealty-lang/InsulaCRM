@extends('layouts.app')

@section('title', __('Edit Contractor'))
@section('page-title', __('Edit Contractor'))

@section('breadcrumbs')
<li class="breadcrumb-item"><a href="{{ route('contractors.index') }}">{{ __('Contractors') }}</a></li>
<li class="breadcrumb-item"><a href="{{ route('contractors.show', $contractor) }}">{{ $contractor->name }}</a></li>
<li class="breadcrumb-item active" aria-current="page">{{ __('Edit') }}</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ __('Edit Contractor:') }} {{ $contractor->name }}</h3>
    </div>
    <div class="card-body">
        <form action="{{ route('contractors.update', $contractor) }}" method="POST">
            @csrf
            @method('PUT')
            @php $selectedTrades = old('specialty', $contractor->specialty ?? []); @endphp
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label required">{{ __('Name') }}</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $contractor->name) }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Service Area') }}</label>
                    <input type="text" name="service_area" class="form-control @error('service_area') is-invalid @enderror" value="{{ old('service_area', $contractor->service_area) }}" placeholder="{{ __('e.g., Atlanta Metro, GA') }}">
                    @error('service_area') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Phone') }}</label>
                    <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $contractor->phone) }}">
                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Email') }}</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $contractor->email) }}">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Specialty / Trade Categories') }}</label>
                <div class="row">
                    @foreach(\App\Models\Contractor::TRADE_CATEGORIES as $val => $label)
                    <div class="col-md-3">
                        <label class="form-check">
                            <input type="checkbox" name="specialty[]" value="{{ $val }}" class="form-check-input" {{ in_array($val, $selectedTrades) ? 'checked' : '' }}>
                            <span class="form-check-label">{{ __($label) }}</span>
                        </label>
                    </div>
                    @endforeach
                </div>
                @error('specialty') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label required">{{ __('Priority') }}</label>
                    <select name="priority" class="form-select @error('priority') is-invalid @enderror">
                        @foreach(\App\Models\Contractor::PRIORITIES as $val => $label)
                        <option value="{{ $val }}" {{ old('priority', $contractor->priority) === $val ? 'selected' : '' }}>{{ __($label) }}</option>
                        @endforeach
                    </select>
                    @error('priority') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label required">{{ __('Status') }}</label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror">
                        @foreach(\App\Models\Contractor::STATUSES as $val => $label)
                        <option value="{{ $val }}" {{ old('status', $contractor->status) === $val ? 'selected' : '' }}>{{ __($label) }}</option>
                        @endforeach
                    </select>
                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Referral / Source') }}</label>
                <input type="text" name="referral_source" class="form-control @error('referral_source') is-invalid @enderror" value="{{ old('referral_source', $contractor->referral_source) }}" placeholder="{{ __('e.g., Referred by John, Facebook group') }}">
                @error('referral_source') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Notes') }}</label>
                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="4">{{ old('notes', $contractor->notes) }}</textarea>
                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="card-footer text-end">
                <a href="{{ route('contractors.show', $contractor) }}" class="btn btn-outline-secondary me-2">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('Update Contractor') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
