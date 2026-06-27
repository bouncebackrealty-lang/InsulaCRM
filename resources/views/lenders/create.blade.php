@extends('layouts.app')

@section('title', __('Add Lender'))
@section('page-title', __('Add Lender'))

@section('breadcrumbs')
<li class="breadcrumb-item"><a href="{{ route('lenders.index') }}">{{ __('Lenders') }}</a></li>
<li class="breadcrumb-item active" aria-current="page">{{ __('Add Lender') }}</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ __('Lender Information') }}</h3>
    </div>
    <div class="card-body">
        <form action="{{ route('lenders.store') }}" method="POST">
            @csrf
            @include('lenders.partials.form')
            <div class="card-footer text-end">
                <a href="{{ route('lenders.index') }}" class="btn btn-outline-secondary me-2">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('Create Lender') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
