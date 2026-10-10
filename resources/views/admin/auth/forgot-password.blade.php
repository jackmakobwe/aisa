@extends('layouts.admin-guest')

@section('title', 'Reset password')

@section('content')
<h1 class="text-2xl">Reset your password</h1>
<p class="mt-1 text-sm text-ink-muted">We will email a reset link if the address belongs to an account.</p>
<form method="POST" action="{{ route('admin.password.email') }}" class="mt-6 space-y-5">
    @csrf
    <div>
        <label for="email" class="field-label">Email</label>
        <input id="email" name="email" type="email" required autocomplete="username" class="field" value="{{ old('email') }}">
        @error('email') <p class="field-error">{{ $message }}</p> @enderror
    </div>
    <button type="submit" class="btn-primary w-full">Send reset link</button>
</form>
<p class="mt-5 text-center text-sm"><a href="{{ route('admin.login') }}">Back to sign in</a></p>
@endsection
