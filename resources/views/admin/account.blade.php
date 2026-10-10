@extends('layouts.admin')

@section('title', 'My account')

@section('content')
<h1 class="text-3xl">My account</h1>
<p class="mt-1 text-ink-muted">{{ $user->name }} · {{ $user->email }} · {{ $user->role }}</p>

<div class="mt-8 grid gap-8 lg:grid-cols-2">
    <form method="POST" action="{{ route('admin.account.password') }}" class="card space-y-4">
        @csrf
        <h2 class="text-xl">Change password</h2>
        <div><label for="current_password" class="field-label">Current password</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="field"></div>
        <div><label for="password" class="field-label">New password</label><input id="password" name="password" type="password" required autocomplete="new-password" class="field">
            <p class="field-hint">At least 12 characters, upper and lower case and a number.</p></div>
        <div><label for="password_confirmation" class="field-label">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="field"></div>
        <button type="submit" class="btn-primary btn-sm">Change password</button>
    </form>

    <form method="POST" action="{{ route('admin.account.recovery-codes') }}" class="card space-y-4">
        @csrf
        <h2 class="text-xl">Two-factor recovery codes</h2>
        <p class="text-sm text-ink-soft">Two-factor sign-in is on. You have {{ $recoveryLeft }} unused recovery code(s). Making new codes cancels the old ones.</p>
        <div><label for="rc_password" class="field-label">Current password</label><input id="rc_password" name="current_password" type="password" required autocomplete="current-password" class="field"></div>
        <button type="submit" class="btn-secondary btn-sm">Make new recovery codes</button>
    </form>
</div>
@endsection
