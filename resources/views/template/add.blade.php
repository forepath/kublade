@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row mb-3">
        <div class="col-md-12">
            <a href="{{ route('template.index', ['type' => request()->type ?? 'application']) }}" class="btn btn-sm btn-secondary text-white">
                <i class="bi bi-arrow-left"></i>
            </a>
        </div>
    </div>
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card border border-secondary">
                <div class="card-header">{{ __('Add template') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('template.add.action') }}">
                        @csrf

                        <input type="hidden" name="type" value="{{ request()->type ?? 'application' }}">

                        <div class="row mb-3">
                            <label for="name" class="col-md-4 col-form-label text-md-end">{{ __('Name') }}</label>

                            <div class="col-md-6">
                                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autofocus>

                                @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        @if (!request()->type || request()->type == 'application')
                            <div class="row mb-3">
                                <label for="netpol" class="col-md-4 col-form-label text-md-end">{{ __('Enable network policy') }}</label>

                                <div class="col-md-6 d-flex align-items-center">
                                    <input id="netpol" type="checkbox" class="form-check-input @error('netpol') is-invalid @enderror" name="netpol" value="1" {{ old('netpol') ? 'checked' : '' }}>
                                </div>
                            </div>
                        @endif

                        <div class="row mb-0">
                            <div class="col-md-8 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Submit') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
