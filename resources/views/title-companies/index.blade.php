@extends('layouts.app')
@section('title', __('Title Companies'))
@section('page-title', __('Title Companies'))
@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ __('Title Companies / Closing Attorneys') }}</h3>
            <div class="card-actions"><a class="btn btn-primary"
                    href="{{ route('title-companies.create') }}">{{ __('Add Title Company') }}</a></div>
        </div>
        <div class="card-body border-bottom">
            <form class="d-flex gap-2"><input class="form-control" name="search" value="{{ request('search') }}"
                    placeholder="{{ __('Search name, attorney, or email...') }}"><button
                    class="btn btn-outline-primary">{{ __('Search') }}</button></form>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr>
                        <th>{{ __('Company') }}</th>
                        <th>{{ __('Closing Attorney') }}</th>
                        <th>{{ __('Address') }}</th>
                        <th>{{ __('Contact') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($titleCompanies as $titleCompany)
                        <tr>
                            <td><a class="fw-bold"
                                    href="{{ route('title-companies.show', $titleCompany) }}">{{ $titleCompany->name }}</a>
                            </td>
                            <td>{{ $titleCompany->closing_attorney ?: '-' }}</td>
                            <td>{{ $titleCompany->full_address ?: '-' }}</td>
                            <td>{{ $titleCompany->phone ?: $titleCompany->email ?: '-' }}</td>
                            <td><a class="btn btn-sm btn-outline-primary"
                                    href="{{ route('title-companies.edit', $titleCompany) }}">{{ __('Edit') }}</a></td>
                    </tr>@empty<tr>
                            <td colspan="5" class="text-center text-secondary py-4">{{ __('No title companies yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $titleCompanies->links() }}</div>
    </div>
@endsection
