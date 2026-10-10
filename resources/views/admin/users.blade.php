@extends('layouts.admin')

@section('title', 'Staff accounts')

@section('content')
<h1 class="text-3xl">Staff accounts</h1>
<p class="mt-1 max-w-3xl text-ink-muted">Responders can read and reply to conversations and review safety flags. Admins can also manage contacts, settings, accounts and the audit log. Every account must use two-factor sign-in.</p>

<div class="card mt-6 overflow-x-auto p-0">
    <table class="table-admin min-w-[760px]">
        <thead><tr><th scope="col">Name</th><th scope="col">Role</th><th scope="col">Two-factor</th><th scope="col">Last sign-in</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
        <tbody>
            @foreach ($users as $user)
                <tr>
                    <td><span class="font-semibold">{{ $user->name }}</span><br><span class="text-xs text-ink-muted">{{ $user->email }}</span></td>
                    <td>{{ ucfirst($user->role) }}</td>
                    <td>{{ $user->hasTwoFactorEnabled() ? 'On' : 'Not set up' }}</td>
                    <td>{{ $user->last_login_at?->timezone(config('aisa.display_timezone'))->format('j M Y, H:i') ?? 'Never' }}</td>
                    <td>{{ $user->is_active ? 'Active' : 'Deactivated' }}</td>
                    <td>
                        @unless ($user->is(auth()->user()))
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('admin.users.toggle', $user) }}" data-confirm="{{ $user->is_active ? 'Deactivate' : 'Activate' }} {{ $user->name }}?">
                                    @csrf
                                    <button type="submit" class="btn-secondary btn-sm">{{ $user->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                                @if ($user->hasTwoFactorEnabled())
                                    <form method="POST" action="{{ route('admin.users.reset-two-factor', $user) }}" data-confirm="Reset two-factor sign-in for {{ $user->name }}? Only do this after confirming their identity.">
                                        @csrf
                                        <button type="submit" class="btn-quiet btn-sm">Reset 2FA</button>
                                    </form>
                                @endif
                            </div>
                        @endunless
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<form method="POST" action="{{ route('admin.users.store') }}" class="card mt-8 max-w-3xl space-y-4">
    @csrf
    <h2 class="text-xl">Add an account</h2>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label for="name" class="field-label text-sm">Name</label><input id="name" name="name" type="text" required maxlength="120" class="field py-2" value="{{ old('name') }}"></div>
        <div><label for="email" class="field-label text-sm">Email</label><input id="email" name="email" type="email" required class="field py-2" value="{{ old('email') }}"></div>
        <div>
            <label for="role" class="field-label text-sm">Role</label>
            <select id="role" name="role" class="field py-2">
                <option value="responder">Responder</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <div></div>
        <div><label for="password" class="field-label text-sm">Initial password</label><input id="password" name="password" type="password" required autocomplete="new-password" class="field py-2"></div>
        <div><label for="password_confirmation" class="field-label text-sm">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="field py-2"></div>
    </div>
    <p class="text-xs text-ink-muted">Share the password privately. They should change it after their first sign-in. Only add people who are trained and have signed a confidentiality agreement.</p>
    <button type="submit" class="btn-primary btn-sm">Create account</button>
</form>
@endsection
