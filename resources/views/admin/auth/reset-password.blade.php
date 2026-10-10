@extends('layouts.admin-guest')

@section('title', 'Choose a new password')

@section('content')
<h1 class="text-2xl">Choose a new password</h1>
<form method="POST" action="{{ route('admin.password.update') }}" class="mt-6 space-y-5">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div>
        <label for="email" class="field-label">Email</label>
        <input id="email" name="email" type="email" required autocomplete="username" class="field" value="{{ old('email', $email) }}">
        @error('email') <p class="field-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password" class="field-label">New password</label>
        <input id="password" name="password" type="password" required autocomplete="new-password" class="field">
        <p class="field-hint">At least 12 characters with upper and lower case letters and a number. Passwords found in known data breaches are refused.</p>
        @error('password') <p class="field-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password_confirmation" class="field-label">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="field">
    </div>
    <button type="submit" class="btn-primary w-full">Save password</button>
</form>
@endsection
