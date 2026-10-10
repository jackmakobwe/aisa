@extends('layouts.admin-guest')

@section('title', 'Two-factor sign-in')

@section('content')
<h1 class="text-2xl">Enter your code</h1>
<p class="mt-1 text-sm text-ink-muted">Open your authenticator app and enter the current 6-digit code.</p>

<form method="POST" action="{{ route('admin.two-factor.challenge.verify') }}" class="mt-6 space-y-4">
    @csrf
    <div>
        <label for="one_time_password" class="field-label">6-digit code</label>
        <input id="one_time_password" name="one_time_password" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" autofocus class="field text-center font-mono text-xl tracking-[0.3em]">
        @error('one_time_password') <p class="field-error" role="alert">{{ $message }}</p> @enderror
    </div>
    <button type="submit" class="btn-primary w-full">Verify</button>
</form>

<details class="mt-6">
    <summary class="cursor-pointer text-sm text-forest">Use a recovery code instead</summary>
    <form method="POST" action="{{ route('admin.two-factor.challenge.verify') }}" class="mt-3 space-y-3">
        @csrf
        <label for="recovery_code" class="field-label">Recovery code</label>
        <input id="recovery_code" name="recovery_code" type="text" autocomplete="off" class="field font-mono">
        <button type="submit" class="btn-secondary w-full">Use recovery code</button>
    </form>
</details>

<form method="POST" action="{{ route('admin.logout') }}" class="mt-4 text-center">
    @csrf
    <button type="submit" class="text-sm text-forest underline">Sign out</button>
</form>
@endsection
