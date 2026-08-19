<div class="row mb-3">
    <div class="col-md-6">
        <label class="form-label required">{{ __('Name') }}</label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $lender->name ?? '') }}" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">{{ __('Company') }}</label>
        <input type="text" name="company" class="form-control @error('company') is-invalid @enderror" value="{{ old('company', $lender->company ?? '') }}">
        @error('company') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
<div class="row mb-3">
    <div class="col-md-4">
        <label class="form-label">{{ __('Phone') }}</label>
        <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $lender->phone ?? '') }}">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('Email') }}</label>
        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $lender->email ?? '') }}">
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('Service Area') }}</label>
        <input type="text" name="service_area" class="form-control @error('service_area') is-invalid @enderror" value="{{ old('service_area', $lender->service_area ?? '') }}" placeholder="{{ __('e.g., Atlanta Metro, GA') }}">
        @error('service_area') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
<div class="mb-3">
    <label class="form-label">{{ __('Notes') }}</label>
    <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="4">{{ old('notes', $lender->notes ?? '') }}</textarea>
    @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
