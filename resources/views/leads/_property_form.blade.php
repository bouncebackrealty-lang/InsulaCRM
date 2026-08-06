@php
    $directEntryValue = static function ($value) {
        return in_array((float) $value, [0.0, 0.01], true) ? '' : $value;
    };
@endphp
<div class="card mb-3" id="property-section">
    <div class="card-header">
        <h3 class="card-title">{{ __('Property Details') }}</h3>
        <div class="card-actions">
            <a class="btn btn-ghost-secondary btn-sm" data-bs-toggle="collapse" href="#section-property" aria-expanded="true" aria-label="{{ __('Toggle section') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><polyline points="6 9 12 15 18 9"/></svg>
            </a>
        </div>
    </div>
    <div class="card-body collapse show" id="section-property">
        @if($lead->property)
        <div class="mb-3 p-3 rounded" style="background: #f6f8fb;">
            <div class="row">
                <div class="col-md-2">
                    <small class="text-secondary d-block">{{ __('Condition') }}</small>
                    <strong>{{ __(ucfirst($lead->property->condition ?? '-')) }}</strong>
                </div>
                <div class="col-md-2">
                    <small class="text-secondary d-block">{{ Fmt::areaUnit() }}</small>
                    <strong>{{ $lead->property->square_footage ? Fmt::area($lead->property->square_footage) : '-' }}</strong>
                </div>
                <div class="col-md-2">
                    <small class="text-secondary d-block">{{ __('Year Built') }}</small>
                    <strong>{{ $lead->property->year_built ?? '-' }}</strong>
                </div>
                @if(($businessMode ?? 'wholesale') === 'wholesale')
                <div class="col-md-2">
                    <small class="text-secondary d-block">{{ __('MAO') }} ({{ $lead->property->mao_percentage ?? 70 }}%)</small>
                    <strong class="{{ ($lead->property->mao ?? 0) >= 0 ? 'text-green' : 'text-red' }}">
                        {{ Fmt::currency($lead->property->mao ?? 0, 0) }}
                    </strong>
                </div>
                <div class="col-md-2">
                    <small class="text-secondary d-block">{{ __('Distress Markers') }}</small>
                    @if(!empty($lead->property->distress_markers) && is_array($lead->property->distress_markers))
                        @foreach($lead->property->distress_markers as $marker)
                            <span class="badge bg-orange-lt me-1">{{ __(ucwords(str_replace('_', ' ', $marker))) }}</span>
                        @endforeach
                    @else
                        <span class="text-secondary">{{ __('None') }}</span>
                    @endif
                </div>
                @else
                <div class="col-md-2">
                    <small class="text-secondary d-block">{{ __('List Price') }}</small>
                    <strong>{{ $lead->property->list_price ? Fmt::currency($lead->property->list_price, 0) : '-' }}</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-secondary d-block">{{ __('Listing Status') }}</small>
                    @php $ls = $lead->property->listing_status; @endphp
                    @if($ls)
                        <span class="badge bg-{{ $ls === 'active' ? 'green' : ($ls === 'pending' ? 'yellow' : ($ls === 'sold' ? 'blue' : 'secondary')) }}-lt">{{ __(ucfirst($ls)) }}</span>
                    @else
                        <span class="text-secondary">-</span>
                    @endif
                </div>
                @endif
            </div>
            @if(auth()->user()->tenant->ai_enabled)
            <div class="mt-2 d-flex gap-2 flex-wrap">
                <a href="{{ route('properties.show', $lead->property) }}" class="btn btn-outline-primary btn-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-calculator" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="4" y="3" width="16" height="18" rx="2"/><rect x="8" y="7" width="8" height="3" rx="1"/><line x1="8" y1="14" x2="8" y2="14.01"/><line x1="12" y1="14" x2="12" y2="14.01"/><line x1="16" y1="14" x2="16" y2="14.01"/><line x1="8" y1="17" x2="8" y2="17.01"/><line x1="12" y1="17" x2="12" y2="17.01"/><line x1="16" y1="17" x2="16" y2="17.01"/></svg>
                    {{ __('View ARV / Comps') }}
                </a>
                <button type="button" class="btn btn-outline-purple btn-sm ai-prop-btn" data-action="offer-strategy" data-prop-id="{{ $lead->property->id }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-sparkles" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M16 18a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm0 -12a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm-7 12a6 6 0 0 1 6 -6a6 6 0 0 1 -6 -6a6 6 0 0 1 -6 6a6 6 0 0 1 6 6z"/></svg>
                    {{ __('AI Offer Strategy') }}
                </button>
                <button type="button" class="btn btn-outline-purple btn-sm ai-prop-btn" data-action="property-description" data-prop-id="{{ $lead->property->id }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-sparkles" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M16 18a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm0 -12a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm-7 12a6 6 0 0 1 6 -6a6 6 0 0 1 -6 -6a6 6 0 0 1 -6 6a6 6 0 0 1 6 6z"/></svg>
                    {{ __('AI Property Description') }}
                </button>
                <button type="button" class="btn btn-outline-purple btn-sm ai-prop-btn" data-action="comparable-sales" data-prop-id="{{ $lead->property->id }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-sparkles" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M16 18a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm0 -12a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm-7 12a6 6 0 0 1 6 -6a6 6 0 0 1 -6 -6a6 6 0 0 1 -6 6a6 6 0 0 1 6 6z"/></svg>
                    {{ __('AI Comparable Sales') }}
                </button>
            </div>
            @else
            <div class="mt-2">
                <a href="{{ route('properties.show', $lead->property) }}" class="btn btn-outline-primary btn-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-calculator" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="4" y="3" width="16" height="18" rx="2"/><rect x="8" y="7" width="8" height="3" rx="1"/><line x1="8" y1="14" x2="8" y2="14.01"/><line x1="12" y1="14" x2="12" y2="14.01"/><line x1="16" y1="14" x2="16" y2="14.01"/><line x1="8" y1="17" x2="8" y2="17.01"/><line x1="12" y1="17" x2="12" y2="17.01"/><line x1="16" y1="17" x2="16" y2="17.01"/></svg>
                    {{ __('View ARV / Comps') }}
                </a>
            </div>
            @endif
        </div>
        @endif
        <form action="{{ route('leads.property.store', $lead) }}" method="POST" id="property-form">
            @csrf
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label required">{{ __('Address') }}</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address', $lead->property->address ?? '') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label required">{{ __('City') }}</label>
                    <input type="text" name="city" class="form-control" value="{{ old('city', $lead->property->city ?? '') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label required">{{ Fmt::stateLabel() }}</label>
                    <input type="text" name="state" class="form-control" maxlength="{{ Fmt::stateMaxLength() }}" value="{{ old('state', $lead->property->state ?? '') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label required">{{ Fmt::postalCodeLabel() }}</label>
                    <input type="text" name="zip_code" class="form-control" maxlength="{{ Fmt::postalCodeMaxLength() }}" value="{{ old('zip_code', $lead->property->zip_code ?? '') }}" required>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Parcel ID / Legal Description') }}</label>
                    <input type="text" name="parcel_id" class="form-control" value="{{ old('parcel_id', $lead->property->parcel_id ?? '') }}">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-2">
                    <label class="form-label required">{{ __('Property Type') }}</label>
                    <select name="property_type" class="form-select" required>
                        @foreach(\App\Services\CustomFieldService::getOptions('property_type') as $val => $label)
                            <option value="{{ $val }}" {{ old('property_type', $lead->property->property_type ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Deal Type') }}</label>
                    <select name="deal_type" class="form-select">
                        <option value="">{{ __('Not Set') }}</option>
                        @foreach(\App\Models\Deal::dealTypes() as $typeKey => $typeLabel)
                            <option value="{{ $typeKey }}" {{ old('deal_type', $lead->property->deal_type ?? '') === $typeKey ? 'selected' : '' }}>{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Condition') }}</label>
                    <select name="condition" class="form-select">
                        <option value="">{{ __('Select...') }}</option>
                        @foreach(\App\Services\CustomFieldService::getOptions('property_condition') as $val => $label)
                            <option value="{{ $val }}" {{ old('condition', $lead->property->condition ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Bedrooms') }}</label>
                    <input type="text" inputmode="numeric" name="bedrooms" class="form-control" value="{{ old('bedrooms', $directEntryValue($lead->property->bedrooms ?? null)) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Bathrooms') }}</label>
                    <input type="text" inputmode="numeric" name="bathrooms" class="form-control" value="{{ old('bathrooms', $directEntryValue($lead->property->bathrooms ?? null)) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Year Built') }}</label>
                    <input type="number" name="year_built" class="form-control" min="1800" max="{{ date('Y') }}" value="{{ old('year_built', $lead->property->year_built ?? '') }}">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">{{ Fmt::areaLabel() }}</label>
                    <input type="number" name="square_footage" class="form-control" min="0" value="{{ old('square_footage', $lead->property->square_footage ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ Fmt::lotSizeLabel() }}</label>
                    <input type="number" name="lot_size" class="form-control" step="0.01" min="0" value="{{ old('lot_size', $directEntryValue($lead->property->lot_size ?? null)) }}">
                </div>
                @if(($businessMode ?? 'wholesale') === 'wholesale')
                <div class="col-md-4">
                    <label class="form-label">{{ __('Distress Markers') }}</label>
                    @php $currentMarkers = old('distress_markers', $lead->property->distress_markers ?? []); @endphp
                    <div class="dropdown" id="distress-markers-picker">
                        <button class="btn btn-outline-secondary w-100 text-start d-flex justify-content-between align-items-center" type="button" id="distress-markers-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <span id="distress-markers-label">{{ __('Select distress markers') }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="dropdown-menu w-100 p-1" aria-labelledby="distress-markers-toggle">
                            @foreach(\App\Services\CustomFieldService::getOptions('distress_markers') as $val => $label)
                                <button type="button" class="dropdown-item distress-marker-option" data-value="{{ $val }}" data-label="{{ $label }}">
                                    <span class="form-check m-0">
                                        <input class="form-check-input distress-marker-checkbox" type="checkbox" value="{{ $val }}" style="pointer-events:none" {{ is_array($currentMarkers) && in_array($val, $currentMarkers) ? 'checked' : '' }}>
                                        <span class="form-check-label">{{ $label }}</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div id="distress-markers-inputs"></div>
                    <small class="text-secondary">{{ __('Select one marker at a time; the menu closes after each selection.') }}</small>
                </div>
                @endif
            </div>
            @if(($businessMode ?? 'wholesale') === 'wholesale')
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">{{ __('Estimated Value') }} ({{ Fmt::currencySymbol() }})</label>
                    <input type="number" name="estimated_value" class="form-control" step="0.01" min="0" value="{{ old('estimated_value', $directEntryValue($lead->property->estimated_value ?? null)) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('After Repair Value') }} ({{ Fmt::currencySymbol() }})</label>
                    <input type="number" name="after_repair_value" id="after_repair_value" class="form-control calc-field" step="0.01" min="0" value="{{ old('after_repair_value', $directEntryValue($lead->property->after_repair_value ?? null)) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('Repair Estimate') }} ({{ Fmt::currencySymbol() }})</label>
                    <input type="number" name="repair_estimate" id="repair_estimate" class="form-control calc-field" step="0.01" min="0" value="{{ old('repair_estimate', $directEntryValue($lead->property->repair_estimate ?? null)) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('MAO Percentage') }}</label>
                    @php $selectedMaoPercentage = (int) old('mao_percentage', $lead->property->mao_percentage ?? 70); @endphp
                    <select name="mao_percentage" id="mao_percentage" class="form-select calc-field">
                        @foreach([70, 72, 75] as $percentage)
                            <option value="{{ $percentage }}" {{ $selectedMaoPercentage === $percentage ? 'selected' : '' }}>{{ $percentage }}%</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('MAO') }}</label>
                    <div class="form-control-plaintext">
                        <strong id="mao-display" class="h4">$0.00</strong>
                        <br><small class="text-secondary" id="mao-formula">{{ __('(ARV x 70%) - Repairs') }}</small>
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('Asking Price') }} ({{ Fmt::currencySymbol() }})</label>
                    <input type="number" name="asking_price" class="form-control" step="0.01" min="0" value="{{ old('asking_price', $directEntryValue($lead->property->asking_price ?? null)) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Our Offer') }} ({{ Fmt::currencySymbol() }})</label>
                    <input type="number" name="our_offer" id="our_offer" class="form-control calc-field" step="0.01" min="0" value="{{ old('our_offer', $directEntryValue($lead->property->our_offer ?? null)) }}">
                </div>
                <div class="col-md-4" id="assignment-fee-field" {{ old('deal_type', $lead->property->deal_type ?? '') === 'wholesale' ? '' : 'hidden' }}>
                    <label class="form-label">{{ __('Assignment Fee') }}</label>
                    <div class="form-control-plaintext">
                        <strong id="assignment-fee" class="h4">$0.00</strong>
                    </div>
                </div>
            </div>
            @else
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('List Price') }} ({{ Fmt::currencySymbol() }})</label>
                    <input type="number" name="list_price" class="form-control" step="0.01" min="0" value="{{ old('list_price', $directEntryValue($lead->property->list_price ?? null)) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Listing Status') }}</label>
                    <select name="listing_status" class="form-select">
                        <option value="">{{ __('Select...') }}</option>
                        @foreach(['active' => __('Active'), 'pending' => __('Pending'), 'sold' => __('Sold'), 'withdrawn' => __('Withdrawn'), 'expired' => __('Expired')] as $val => $label)
                            <option value="{{ $val }}" {{ old('listing_status', $lead->property->listing_status ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Asking Price') }} ({{ Fmt::currencySymbol() }})</label>
                    <input type="number" name="asking_price" class="form-control" step="0.01" min="0" value="{{ old('asking_price', $directEntryValue($lead->property->asking_price ?? null)) }}">
                </div>
            </div>
            @endif
            <div class="mb-3">
                <label class="form-label">{{ __('Notes') }}</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes', $lead->property->notes ?? '') }}</textarea>
            </div>
            <div class="text-end">
                <button type="submit" class="btn btn-primary">{{ __('Save Property') }}</button>
            </div>
        </form>
    </div>
</div>

@if(($businessMode ?? 'wholesale') === 'wholesale')
@push('scripts')
<script>
function calculateMAO() {
    const arv = parseFloat(document.getElementById('after_repair_value').value) || 0;
    const repair = parseFloat(document.getElementById('repair_estimate').value) || 0;
    const percentage = parseFloat(document.getElementById('mao_percentage').value) || 70;
    const maoEl = document.getElementById('mao-display');
    document.getElementById('mao-formula').textContent = '(ARV x ' + percentage + '%) - Repairs';

    if (arv && repair) {
        const mao = (arv * (percentage / 100)) - repair;
        maoEl.textContent = '{{ Fmt::currencySymbol() }}' + mao.toLocaleString('{{ Fmt::jsLocale() }}', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        maoEl.className = mao >= 0 ? 'h4 text-green' : 'h4 text-red';
    } else {
        maoEl.textContent = '{{ Fmt::currencySymbol() }}0.00';
        maoEl.className = 'h4';
    }
}

function calculateAssignmentFee() {
    const dealType = document.querySelector('[name="deal_type"]')?.value;
    const feeField = document.getElementById('assignment-fee-field');
    if (feeField) {
        feeField.hidden = dealType !== 'wholesale';
    }

    if (dealType !== 'wholesale') {
        return;
    }

    const ourOffer = parseFloat(document.getElementById('our_offer').value) || 0;
    const arv = parseFloat(document.getElementById('after_repair_value').value) || 0;
    const repair = parseFloat(document.getElementById('repair_estimate').value) || 0;
    const percentage = parseFloat(document.getElementById('mao_percentage').value) || 70;

    if (ourOffer && arv && repair) {
        const mao = (arv * (percentage / 100)) - repair;
        const fee = mao - ourOffer;
        const feeEl = document.getElementById('assignment-fee');
        feeEl.textContent = '{{ Fmt::currencySymbol() }}' + fee.toLocaleString('{{ Fmt::jsLocale() }}', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        feeEl.className = fee >= 0 ? 'h4 text-green' : 'h4 text-red';
    } else {
        document.getElementById('assignment-fee').textContent = '{{ Fmt::currencySymbol() }}0.00';
        document.getElementById('assignment-fee').className = 'h4';
    }
}

document.querySelectorAll('.calc-field').forEach(function(input) {
    input.addEventListener('input', function() {
        calculateMAO();
        calculateAssignmentFee();
    });
});

document.querySelector('[name="deal_type"]')?.addEventListener('change', calculateAssignmentFee);

(function initialiseDistressMarkerPicker() {
    const picker = document.getElementById('distress-markers-picker');
    const inputs = document.getElementById('distress-markers-inputs');
    const label = document.getElementById('distress-markers-label');
    const toggle = document.getElementById('distress-markers-toggle');
    if (!picker || !inputs || !label || !toggle) return;

    const update = function () {
        const selected = Array.from(picker.querySelectorAll('.distress-marker-checkbox:checked'));
        inputs.innerHTML = selected.map(function (checkbox) {
            return '<input type="hidden" name="distress_markers[]" value="' + checkbox.value + '">';
        }).join('');
        label.textContent = selected.length
            ? selected.map(function (checkbox) { return checkbox.closest('.distress-marker-option').dataset.label; }).join(', ')
            : '{{ __('Select distress markers') }}';
    };

    picker.querySelectorAll('.distress-marker-option').forEach(function (option) {
        option.addEventListener('click', function (event) {
            event.preventDefault();
            const checkbox = option.querySelector('.distress-marker-checkbox');
            checkbox.checked = !checkbox.checked;
            update();
            bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        });
    });

    update();
})();

calculateMAO();
calculateAssignmentFee();
</script>
@endpush
@endif
