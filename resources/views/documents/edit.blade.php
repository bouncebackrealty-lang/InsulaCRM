@extends('layouts.app')

@section('title', __('Edit Document'))
@section('page-title', __('Edit Document'))

@section('breadcrumbs')
<li class="breadcrumb-item"><a href="{{ route('documents.show', $document) }}">{{ $document->name }}</a></li>
<li class="breadcrumb-item active" aria-current="page">{{ __('Edit') }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-9">
        <form method="POST" action="{{ route('documents.update', $document) }}">
            @csrf
            @method('PUT')
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('Edit generated document') }}</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required" for="document-name">{{ __('Document Name') }}</label>
                        <input id="document-name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $document->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="document-content">{{ __('Document HTML / wording') }}</label>
                        <textarea id="document-content" name="content" rows="26" class="form-control font-monospace @error('content') is-invalid @enderror" required>{{ old('content', $document->content) }}</textarea>
                        <div class="form-hint">{{ __('Changes apply only to this generated document. The master template is not changed.') }}</div>
                        @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="card-footer text-end">
                    <a href="{{ route('documents.show', $document) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
