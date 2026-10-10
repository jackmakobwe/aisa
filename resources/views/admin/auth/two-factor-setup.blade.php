@extends('layouts.admin-guest')

@section('title', 'Set up two-factor sign-in')

@section('content')
<h1 class="text-2xl">Set up two-factor sign-in</h1>
<p class="mt-2 text-sm text-ink-soft">The dashboard holds private conversations, so every account needs an authenticator app (for example Google Authenticator, Microsoft Authenticator or Aegis).</p>

<ol class="mt-5 list-decimal space-y-2 pl-5 text-sm text-ink-soft">
    <li>Open your authenticator app and add a new account.</li>
    <li>Scan this QR code, or type the key below.</li>
    <li>Enter the 6-digit code the app shows.</li>
</ol>

<div class="mt-5 flex justify-center rounded-xl border border-line bg-white p-4" aria-hidden="true">{!! $qr !!}</div>
<p class="mt-3 text-center text-xs text-ink-muted">Setup key</p>
<p class="text-center font-mono text-sm font-semibold tracking-wider break-all">{{ trim(chunk_split($secret, 4, ' ')) }}</p>

<form method="POST" action="{{ route('admin.two-factor.setup.confirm') }}" class="mt-6 space-y-4">
    @csrf
    <div>
        <label for="one_time_password" class="field-label">6-digit code</label>
        <input id="one_time_password" name="one_time_password" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required class="field text-center font-mono text-xl tracking-[0.3em]">
        @error('one_time_password') <p class="field-error" role="alert">{{ $message }}</p> @enderror
    </div>
    <button type="submit" class="btn-primary w-full">Turn on two-factor sign-in</button>
</form>

<form method="POST" action="{{ route('admin.logout') }}" class="mt-4 text-center">
    @csrf
    <button type="submit" class="text-sm text-forest underline">Sign out</button>
</form>
@endsection
