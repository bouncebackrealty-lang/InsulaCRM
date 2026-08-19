@extends('layouts.app')
@section('title', $titleCompany->name)
@section('page-title', $titleCompany->name)
@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ __('Title Company / Closing Attorney') }}</h3>
            <div class="card-actions"><a class="btn btn-outline-primary"
                    href="{{ route('title-companies.edit', $titleCompany) }}">{{ __('Edit') }}</a></div>
        </div>
        <div class="card-body">
            <div class="datagrid">
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('Company') }}</div>
                    <div class="datagrid-content">{{ $titleCompany->name }}</div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('Closing Attorney') }}</div>
                    <div class="datagrid-content">{{ $titleCompany->closing_attorney ?: '-' }}</div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('Address') }}</div>
                    <div class="datagrid-content">{{ $titleCompany->full_address ?: '-' }}</div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('Contact') }}</div>
                    <div class="datagrid-content">{{ $titleCompany->phone ?: '-' }}
                        {{ $titleCompany->email ? ' · ' . $titleCompany->email : '' }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
