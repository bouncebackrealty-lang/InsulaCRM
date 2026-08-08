@extends('layouts.app')
@section('title', $titleCompany->exists ? __('Edit Title Company') : __('Add Title Company'))
@section('page-title', $titleCompany->exists ? __('Edit Title Company') : __('Add Title Company'))
@section('content')
    <div class="card">
        <form method="POST"
            action="{{ $titleCompany->exists ? route('title-companies.update', $titleCompany) : route('title-companies.store') }}">
            @csrf @if ($titleCompany->exists)
                @method('PUT')
            @endif
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label required">{{ __('Company Name') }}</label><input required
                            name="name" class="form-control" value="{{ old('name', $titleCompany->name) }}"></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Closing Attorney') }}</label><input
                            name="closing_attorney" class="form-control"
                            value="{{ old('closing_attorney', $titleCompany->closing_attorney) }}"></div>
                    <div class="col-12"><label class="form-label">{{ __('Address') }}</label><input name="address"
                            class="form-control" value="{{ old('address', $titleCompany->address) }}"></div>
                    <div class="col-md-5"><label class="form-label">{{ __('City') }}</label><input name="city"
                            class="form-control" value="{{ old('city', $titleCompany->city) }}"></div>
                    <div class="col-md-3"><label class="form-label">{{ __('State') }}</label><input name="state"
                            class="form-control" value="{{ old('state', $titleCompany->state) }}"></div>
                    <div class="col-md-4"><label class="form-label">{{ __('Zip Code') }}</label><input name="zip_code"
                            class="form-control" value="{{ old('zip_code', $titleCompany->zip_code) }}"></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Phone') }}</label><input name="phone"
                            class="form-control" value="{{ old('phone', $titleCompany->phone) }}"></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Email') }}</label><input type="email"
                            name="email" class="form-control" value="{{ old('email', $titleCompany->email) }}"></div>
                    <div class="col-12"><label class="form-label">{{ __('Notes') }}</label>
                        <textarea name="notes" class="form-control">{{ old('notes', $titleCompany->notes) }}</textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end"><a class="btn btn-outline-secondary"
                    href="{{ route('title-companies.index') }}">{{ __('Cancel') }}</a><button
                    class="btn btn-primary">{{ __('Save Title Company') }}</button></div>
        </form>
    </div>
@endsection
