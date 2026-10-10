@extends('layouts.admin-guest')

@section('title', 'Recovery codes')

@section('content')
<h1 class="text-2xl">Save your recovery codes</h1>
<p class="mt-2 text-sm text-ink-soft">If you lose your phone, each of these codes can be used once instead of an authenticator code. They are shown only now. Store them somewhere safe and private, such as a password manager.</p>
<ul class="mt-5 grid grid-cols-2 gap-2 rounded-xl bg-sand p-4 font-mono text-sm font-semibold">
    @foreach ($codes as $code)
        <li>{{ $code }}</li>
    @endforeach
</ul>
<a href="{{ route('admin.dashboard') }}" class="btn-primary mt-6 w-full">I have saved them</a>
@endsection
