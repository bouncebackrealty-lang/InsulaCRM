@extends('layouts.app')

@section('title', __('Edit Lender'))
@section('page-title', __('Edit Lender'))

@section('breadcrumbs')
<li class="breadcrumb-item"><a href="{{ route('lenders.index') }}">{{ __('Lenders') }}</a></li>
<li class="breadcrumb-item"><a href="{{ route('lenders.show', $lender) }}">{{ $lender->name }}</a></li>
<li class="breadcrumb-item active" aria-current="page">{{ __('Edit') }}</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ __('Edit Lender:') }} {{ $lender->name }}</h3>
    </div>
    <div class="card-body">
        <form action="{{ route('lenders.update', $lender) }}" method="POST">
            @csrf
            @method('PUT')
            @include('lenders.partials.form')
            <div class="card-footer text-end">
                <a href="{{ route('lenders.show', $lender) }}" class="btn btn-outline-secondary me-2">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('Update Lender') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
