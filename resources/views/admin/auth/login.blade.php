@extends('layouts.admin-guest')

@section('title', 'Sign in')

@section('content')
<h1 class="text-2xl">Sign in</h1>
<p class="mt-1 text-sm text-ink-muted">For Aisa and authorised responders only.</p>

<form method="POST" action="{{ route('admin.login.attempt') }}" class="mt-6 space-y-5">
    @csrf
    <div>
        <label for="email" class="field-label">Email</label>
        <input id="email" name="email" type="email" required autofocus autocomplete="username" class="field" value="{{ old('email') }}">
        @error('email') <p class="field-error" role="alert">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password" class="field-label">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password" class="field">
    </div>
    <button type="submit" class="btn-primary w-full">Continue</button>
</form>
<p class="mt-5 text-center text-sm"><a href="{{ route('admin.password.request') }}">Forgot your password?</a></p>
@endsection
